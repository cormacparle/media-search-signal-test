<?php

namespace MediaSearchSignalTest\Jobs;

class GenerateSearchTermsWithEntitiesFile {

    private $entitySearchBaseUrl;
    private $searchTerms;
    private $ch;
    private $log;
    private $out;

    public function __construct( array $config ) {
        $this->entitySearchBaseUrl = $config['search']['entitySearchBaseUrl'];
        $this->searchTerms =
            file( __DIR__ . '/../' . $config['search']['searchTermsFile'] );

        $this->ch = curl_init();
        curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, true );

        $this->log = fopen(
            __DIR__ . '/../' . $config['log']['generateSearchTermsWithEntitiesFile'],
            'a'
        );
        $this->out = fopen(
            __DIR__ . '/../' . $config['search']['searchTermsWithEntitiesFile'],
            'a'
        );
    }

    public function __destruct() {
        curl_close( $this->ch );
        fclose( $this->log );
    }

    public function run() {
        $this->log( 'Begin' . "\n" );
        foreach ( $this->searchTerms as $searchTerm ) {
            $searchTerm = trim( $searchTerm );
            $this->log( 'Getting entities for ' . $searchTerm );
            $entities = array_pad(
                $this->getEntities( $searchTerm ),
                50,
                'NO_ENTITY'
            );
            fwrite(
                $this->out,
                $searchTerm . "," . implode( ",", $entities ) . "\n"
            );
        }
        $this->log( 'End' . "\n" );
    }

    public function log( string $msg ) {
        fwrite( $this->log, date( 'Y-m-d H:i:s' ) . ': ' . $msg . "\n" );
    }

    private function getEntities( string $searchTerm ) : array {
        curl_setopt( $this->ch, CURLOPT_URL, $this->getGetEntitiesUrl( $searchTerm ) );
        $result = curl_exec( $this->ch );
        if ( curl_errno( $this->ch ) ) {
            $this->log( curl_error( $this->ch ) . ':' . curl_errno( $this->ch ) );
            die( 'Exiting because of curl error, see log for details.' );
        }
        $response = json_decode( $result, true );
        $transformedResponse = [];
        foreach ( $response['query']['search'] ?? [] as $index => $result ) {
            list( $title, $score ) = $this->transformResult( $result, $index );
            $transformedResponse[ $title ] = floatval( $score );
        }

        arsort( $transformedResponse );
        return array_keys( $transformedResponse );
    }

    private function getGetEntitiesUrl( string $searchTerm ) : string {
        $params = [
            'format' => 'json',
            'action' => 'query',
            'list' => 'search',
            'srsearch' => $searchTerm,
            'srnamespace' => 0,
            'srlimit' => 50,
            'srqiprofile' => 'wikibase',
            'srprop' => 'snippet|titlesnippet|extensiondata',
            'uselang' => 'en',
        ];

        return $this->entitySearchBaseUrl . '?' . http_build_query( $params );
    }

    protected function transformResult( array $result, int $index ) : array {
        // unfortunately, the search API doesn't return an actual score
        // (for relevancy of the match), which means that we have no way
        // of telling which results are awesome matches and which are only
        // somewhat relevant
        // since we can't rely on the order to tell us much about how
        // relevant a result is (except for relative to one another), and
        // we don't know the actual score of these results, we'll try to
        // approximate a term frequency - it won't be great, but at least
        // we'll be able to tell which of "cat" and "Pirates of Catalonia"
        // most resemble "cat"
        // the highlight will either be in extensiondata (in the case
        // of a matching alias), snippet (for descriptions), or
        // titlesnippet (for labels)
        $snippets = [
            $result['snippet'],
            $result['titlesnippet'],
            $result['extensiondata']['wikibase']['extrasnippet'] ?? ''
        ];

        $maxTermFrequency = 0;
        foreach ( $snippets as $snippet ) {
            // let's figure out how much of the snippet actually matched
            // the search term based on the highlight
            $source = preg_replace( '/<span class="searchmatch">(.*?)<\/span>/', '$1', $snippet );
            $omitted = preg_replace( '/<span class="searchmatch">.*?<\/span>/', '', $snippet );
            $termFrequency = $source === '' ? 0 : 1 - mb_strlen( $omitted ) / mb_strlen( $source );
            $maxTermFrequency = max( $maxTermFrequency, $termFrequency );
        }

        // average the order in which results were returned (because that
        // takes into account additional factors such as popularity of
        // the page) and the naive term frequency to calculate how relevant
        // the results are relative to one another
        $relativeOrder = 1 / ( $index + 1 );

        return [ $result['title'], ( $relativeOrder + $maxTermFrequency ) / 2 ];
    }
}

$config = array_merge(
    parse_ini_file( __DIR__ . '/../config.ini', true ),
    parse_ini_file( __DIR__ . '/../replica.my.cnf', true )
);
$job = new GenerateSearchTermsWithEntitiesFile( $config );
$job->run();