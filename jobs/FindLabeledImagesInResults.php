<?php

namespace MediaSearchSignalTest\Jobs;

use mysqli;

class FindLabeledImagesInResults {

    private $db;
    private $searchUrl;
    private $ch;
    private $log;
    private $description;

    public function __construct( array $config, string $description ) {
        $this->db = new mysqli(
            $config['db']['host'],
            $config['client']['user'],
            $config['client']['password'], $config['db']['dbname']
        );
        if ( $this->db->connect_error ) {
            die('DB connection Error (' . $this->db->connect_errno . ') '
                . $this->db->connect_error);
        }

        $this->searchUrl = $config['search']['baseUrl'];

        $this->ch = curl_init();
        curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, true );

        $this->log = fopen(
            __DIR__ . '/../' . $config['log']['findLabeledImages'],
            'a'
        );

        $this->description = $description;
        if ( $this->description == '' ) {
            $this->description = 'Search of ' . $this->searchUrl . ' on ' . date('Y-m-d H:i:s');
        }
    }

    public function __destruct() {
        curl_close( $this->ch );
        fclose( $this->log );
    }

    public function run() {
        $this->log( 'Begin ' . $this->description );
        $searchId = $this->createSearchRecord();
        foreach ( $this->getSearchTerms() as $searchTerm ) {
            $this->log( 'Searching ' . $searchTerm );
            $searchTerm = trim( $searchTerm );
            $this->processResults( $searchTerm, $this->search( $searchTerm ), $searchId );
        }
        $this->log( 'End ' . $this->description );
        return $searchId;
    }

    public function log( string $msg ) {
        fwrite( $this->log, date( 'Y-m-d H:i:s' ) . ': ' . $msg . "\n" );
    }

    private function createSearchRecord() {
        $this->db->query(
            'insert into search set ' .
            'description="' . $this->db->real_escape_string( $this->description ). '"'
        );
        return $this->db->insert_id;
    }

    private function getSearchTerms() : array {
        $searchTerms = [];
        $searchTermResults = $this->db->query(
            'select distinct(term) as term from results_by_component where rating is not null'
        );
        while ( $searchTerm = $searchTermResults->fetch_assoc() ) {
            $searchTerms[] = $searchTerm['term'];
        }
        return $searchTerms;
    }

    private function search( string $searchTerm ) : array {
        curl_setopt( $this->ch, CURLOPT_URL, $this->getSearchUrl( $searchTerm ) );
        $result = curl_exec( $this->ch );
        if ( curl_errno( $this->ch ) ) {
            $this->log( curl_error( $this->ch ) . ':' . curl_errno( $this->ch ) );
            die( 'Exiting because of curl error, see log for details.' );
        }
        $array = json_decode( $result, true );
        return $array;
    }

    private function getSearchUrl( string $searchTerm ) : string {
        return sprintf(
            $this->searchUrl . '/w/index.php?search=%s+filetype:bitmap&ns6=1&' .
            'cirrusDumpResult&mediasearch=1&limit=100',
            urlencode( $searchTerm )
        );
    }

    private function processResults( string $searchTerm, array $searchResults, int $searchId ) {
        $labeledData = $this->getLabeledData( $searchTerm );

        $titles = [];
        if ( isset( $searchResults['__main__']['result']['hits']['hits'] ) ) {
            foreach ( $searchResults['__main__']['result']['hits']['hits'] as $index => $result ) {
                $titles[] = $this->extractTitle( $result['_source'] );
            }
        }
        $this->log( 'Found ' . count( $titles ) . ' results' );
        if ( count( $titles ) > 0) {
            $this->db->query(
            'insert into resultset set ' .
                'searchId=' . intval( $searchId ) . ', ' .
                'term="' .  $this->db->real_escape_string( $searchTerm ) . '", ' .
                'resultCount=' . intval( count( $titles ) )
            );
            $resultsetId = $this->db->insert_id;
            $labeledImageCount = 0;
            foreach ( $titles as $index => $title ) {
                if ( isset( $labeledData[$title] ) ) {
                    $this->db->query(
                        'insert into labeledResult set ' .
                        'resultsetId=' . intval( $resultsetId ) . ', ' .
                        'filePage="' .  $this->db->real_escape_string( $title ). '", ' .
                        'position=' . intval( $index ) . ', ' .
                        'rating=' . intval( $labeledData[$title] )
                    );
                    $labeledImageCount++;
                }
            }
            $this->log( $labeledImageCount . ' of the results were labeled' );
        } else {
            $this->db->query(
                'insert into resultset set ' .
                'searchId=' . intval( $searchId ) . ', ' .
                'term="' .  $this->db->real_escape_string( $searchTerm ) . '", ' .
                'resultCount=0'
            );
        }
    }

    private function getLabeledData( string $searchTerm ) : array {
        $return = [];
        $labeledImages = $this->db->query(
            'select file_page,rating from results_by_component where
            term="' . $this->db->real_escape_string( $searchTerm ) .'"
            and rating is not null'
        );
        while ( $labeledImage = $labeledImages->fetch_assoc() ) {
            $return[$labeledImage['file_page']] = $labeledImage['rating'];
        }
        return $return;
    }

    private function extractTitle( array $source ) : string {
        $title = str_replace( ' ', '_', $source['title'] );
        if ( $source['namespace'] > 0 ) {
            $title = $source['namespace_text'] . ':' . $title;
        }
        return $title;
    }
}

$options = getopt('', [ 'description::' ]);
$config = array_merge(
    parse_ini_file( __DIR__ . '/../config.ini', true ),
    parse_ini_file( __DIR__ . '/../replica.my.cnf', true )
);
$job = new FindLabeledImagesInResults( $config, $options['description'] ?? '' );
$searchId = $job->run();