<?php

namespace MediaSearchSignalTest\Jobs;

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'GenericJob.php';

class QueryResponseParser {
    public function parseQueryResponse( array $queryResponse, string $featuresetName, int $queryId
    ) :
    array {
        $scores = [];
        if ( !isset( $queryResponse['hits']['hits'] ) ) {
            var_dump( $queryResponse );
            die( "Weird. No hits in the query response for query id " . $queryId . ".\n" );
        }
        foreach ( $queryResponse['hits']['hits'] as $hit ) {
            $score = [];
            $fields = $hit['fields']['_ltrlog'][0][ $featuresetName ];
            foreach ( $fields as $index => $field ) {
                $score[ $index + 1 ] = $field['value'] ?? 0;
            }
            $scores[$hit['_source']['title']] = $score;
        }

        return $scores;
    }
}

class GenerateRanklibFile extends GenericJob {

    private $ch;
    private $out;
    private $queryDir;
    private $featuresetName;
    /** @var QueryResponseParser  */
    private $queryResponseParser;
    private $searchTermsFilename;

    public function __construct( array $config ) {
        parent::__construct( $config );
        $this->setLogFileHandle( __DIR__ . '/../' . $this->config['log']['generateRanklibFile'] );

        $this->ch = curl_init();
        curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $this->ch, CURLOPT_POST, true );
        curl_setopt( $this->ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ] );
        curl_setopt( $this->ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $this->ch, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt( $this->ch, CURLOPT_URL, 'https://127.0.0.1:9243/commonswiki_file/_search' );

        $this->out = fopen(
            __DIR__ . '/../' . $this->config['ltr']['ranklibOutputDir'] .
            $this->config['featuresetName'] . '.tsv',
            'w'
        );

        $this->queryDir = __DIR__ . '/../' . $this->config['queryDir'] . '/';
        $this->queryResponseParser = new QueryResponseParser();
        $this->featuresetName = $this->config['featuresetName'];
        $this->searchTermsFilename =
            __DIR__ . '/../' . $this->config['searchTermsWithEntitiesFile'];
    }

    public function __destruct() {
        parent::__destruct();
        curl_close( $this->ch );
        fclose( $this->out );
        mysqli_close( $this->db );
    }

    public function run() {
        $this->log( 'Begin' . "\n" );
        $queryFiles = array_diff( scandir( $this->queryDir) , [ '..', '.' ] );
        foreach ( $queryFiles as $index => $queryFile ) {
            if ( $queryFile === '.gitignore' ) {
                continue;
            }
            $this->log( 'Sending query ' . $queryFile );
            if ( preg_match( '/_([0-9]+)\.json$/', $queryFile, $matches ) ) {
                $queryId = $matches[1];
            } else {
                die( "Something up with query filename structure.\n" );
            }
            curl_setopt(
                $this->ch,
                CURLOPT_POSTFIELDS,
                file_get_contents( $this->queryDir . $queryFile )
            );
            $result = curl_exec( $this->ch );
            if ( curl_errno( $this->ch ) ) {
                $this->log( curl_error( $this->ch ) . ':' . curl_errno( $this->ch ) );
                die( "Exiting because of curl error, see log for details.\n" );
            }
            $scores = $this->queryResponseParser->parseQueryResponse( json_decode( $result,
                true ), $this->featuresetName, $queryId );
            $this->writeRanklibData( $queryId, $scores );
        }
        $this->log( 'End' . "\n" );
    }

    private function getRatingsByFile() : array {
        static $ratingsByFile;
        if ( is_null( $ratingsByFile ) ) {
            $ratingsByFile = [];
            $ratings =
                $this->db->query(
                    'select result, searchTerm, language, rating from ratedSearchResult where rating is not null'
                );
            while ( $rating = $ratings->fetch_object() ) {
                $filename = $this->stripTitleNamespace( $rating->result );
                $ratingsByFile[$rating->language][strtolower( $rating->searchTerm )][$filename] =
                    $rating->rating;
            }
        }
        return $ratingsByFile;
    }

    private function getSearchTerms() : array {
        static $searchTerms;
        if ( is_null( $searchTerms ) ) {
            $searchTermsLines =  file( $this->searchTermsFilename );
            foreach ( $searchTermsLines as $searchTermsLine ) {
                $searchTermsElements = explode( ',', $searchTermsLine );
                $searchTerms[ $searchTermsElements[0] ] = [
                    'term' => $searchTermsElements[1],
                    'language' => $searchTermsElements[2],
                ];
            }
        }
        return $searchTerms;
    }

    private function writeRanklibData( int $queryId, array $scores ) {
        $ratingsByFile = $this->getRatingsByFile();
        $searchTerm = $this->getSearchTerms()[$queryId];
        foreach ( $scores as $file => $scoreArray ) {
            $rating =
                $ratingsByFile[ $searchTerm['language'] ][ $searchTerm['term'] ][ $file ]
                ?? null;
            if ( is_null( $rating ) ) {
                var_dump(
                    $queryId,
                    $ratingsByFile[ $searchTerm['language'] ][ $searchTerm['term'] ],
                    $file,
                    $scoreArray
                );
                die( "Rating for " . $file . " for " . $searchTerm['language'] . " not found.\n" );
            }
            $ranklibLine =
                $rating . "\t" .
                "qid:" . $queryId . "\t";
            foreach ( $scoreArray as $index => $score ) {
                $ranklibLine .= $index . ":" . $score . "\t";
            }
            $ranklibLine .= "# " . $file . "\t" . $searchTerm['language'] . '|' .
                $searchTerm['term'] . "\n";
            fwrite( $this->out, $ranklibLine );
        }
    }

    private function stripTitleNamespace( string $titleString ) : string {
        return preg_replace( '/.+:/', '', trim( $titleString ) );
    }
}

$options = getopt( '', [ 'queryDir:', 'featuresetName:', 'searchTermsWithEntitiesFile:' ] );
$job = new GenerateRanklibFile( $options );
$job->run();
