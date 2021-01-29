<?php

namespace MediaSearchSignalTest\Jobs;

use mysqli;

class QueryResponseParser {
    public function parseQueryResponse( array $queryResponse, string $featuresetName ) :
    array {
        $scores = [];
        foreach ( $queryResponse['hits']['hits'] as $hit ) {
            $score = [];
            $fields = $hit['fields']['_ltrlog'][0][ $featuresetName ];
            foreach ( $fields as $field ) {
                $score[$field['name']] = $field['value'] ?? 0;
            }
            ksort( $score );
            $scores[$hit['_source']['title']] = $score;
        }

        return $scores;
    }
}

class GenerateRanklibFile {

    private $ch;
    private $log;
    private $queryDir;
    private $featuresetName;
    /** @var QueryResponseParser  */
    private $queryResponseParser;
    private $db;

    public function __construct( array $config ) {
        $this->db = new mysqli( $config['db']['host'], $config['client']['user'],
            $config['client']['password'], $config['db']['dbname'] );
        if ( $this->db->connect_error ) {
            die('DB connection Error (' . $this->db->connect_errno . ') '
                . $this->db->connect_error);
        }

        $this->ch = curl_init();
        curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $this->ch, CURLOPT_POST, true );
        curl_setopt( $this->ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ] );
        curl_setopt( $this->ch, CURLOPT_URL, 'http://localhost:9200/commonswiki_file/_search' );

        $this->log = fopen(
            __DIR__ . '/../' . $config['log']['generateRanklibFile'],
            'a'
        );
        $this->queryDir = __DIR__ . '/../' . $config['queryDir'] . '/';
        $this->queryResponseParser = new QueryResponseParser();
        $this->featuresetName = $config['featuresetName'];
    }

    public function __destruct() {
        fclose( $this->log );
    }

    public function run() {
        $this->log( 'Begin' . "\n" );
        $queryFiles = array_diff( scandir( $this->queryDir) , [ '..', '.' ] );
        foreach ( $queryFiles as $index => $queryFile ) {
            $this->log( 'Sending query ' . $queryFile );
            curl_setopt(
                $this->ch,
                CURLOPT_POSTFIELDS,
                file_get_contents( $this->queryDir . $queryFile )
            );
            $result = curl_exec( $this->ch );
            if ( curl_errno( $this->ch ) ) {
                $this->log( curl_error( $this->ch ) . ':' . curl_errno( $this->ch ) );
                die( 'Exiting because of curl error, see log for details.' );
            }
            $scores = $this->queryResponseParser->parseQueryResponse( json_decode( $result,
                true ), $this->featuresetName );

        }
        $this->log( 'End' . "\n" );
    }

    private function getRatingsForFiles( $fileNames ) {
        $fileNamesToFilePages = [];
        foreach( $fileNames as $fileName ) {
            $fileNamesToFilePages[ $fileName ] =
        }

        $ratings = $this->db->query(
            'select file_page,rating from results_by_component where ' .
            'term ="' . $this->db->real_escape_string( $searchTermsRow[0] ) . '" and ' .
            'rating is not null'
        );
    }

    public function log( string $msg ) {
        fwrite( $this->log, date( 'Y-m-d H:i:s' ) . ': ' . $msg . "\n" );
    }
}

$options = getopt( '', [ 'queryDir:', 'featuresetName:' ] );
$config = array_merge(
    parse_ini_file( __DIR__ . '/../config.ini', true ),
    parse_ini_file( __DIR__ . '/../replica.my.cnf', true ),
    $options
);
$job = new GenerateRanklibFile( $config );
$job->run();