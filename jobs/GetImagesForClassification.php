<?php

namespace MediaSearchSignalTest\Jobs;

require 'GenericJob.php';

class GetImagesForClassification extends GenericJob {

    private $searchUrl;
    private $searchTerms = [];

    public function __construct( array $config = null ) {
        parent::__construct( $config );

        $this->searchUrl = $config['search']['baseUrl'];

        $searchTermsFile =
            fopen( __DIR__ . '/../' . $config['search']['searchTermsFile'], 'r' );
        while ( $searchTermsRow = fgetcsv( $searchTermsFile, 1024, ',', '"' ) ) {
            $this->searchTerms[] = $searchTermsRow;
        }
        fclose( $searchTermsFile );

        $this->setLogFileHandle( __DIR__ . '/../' . $this->config['log']['getImages'] );
    }

    public function run() {
        $this->log( "Begin\n" );
        foreach ( $this->searchTerms as $data ) {
            $searchTerm = trim( $data[1] );
            $language = $data[2];
            $this->log( "Searching $searchTerm\n" );
            $searchUrl = $this->getSearchUrl( $searchTerm );
            $searchResults = $this->httpGETJson( $searchUrl );
            $this->storeResults( $searchTerm, $language, $searchResults );
        }
        $this->log( "End\n" );
    }

    private function getSearchUrl( string $searchTerm ) : string {
        return sprintf(
            $this->searchUrl . '/w/index.php?search=%s+filetype:bitmap&ns6=1&' .
            'cirrusDumpResult&mediasearch=1&limit=100&normalizeFulltextScores=0',
            urlencode( $searchTerm )
        );
    }

    private function storeResults( string $searchTerm, string $language, array $searchResults ) {
        $titles = [];
        if ( isset( $searchResults['__main__']['result']['hits']['hits'] ) ) {
            foreach ( $searchResults['__main__']['result']['hits']['hits'] as $result ) {
                $titles[] = ['title' => $this->extractTitle( $result['_source'] )];
            }
        }
        if ( count( $titles ) > 0) {
            foreach ( $titles as $title ) {
                $this->db->query(
                    'INSERT INTO ratedSearchResult SET
                    searchTerm = "' . $this->db->real_escape_string( $searchTerm ) . '",
                    language = "' . $this->db->real_escape_string( $language ) . '",
                    result = "' . $this->db->real_escape_string( $title['title'] ) . '"'
                );
                $this->db->query(
                    'INSERT INTO ratedSearchResult_tag SET
                    ratedSearchResultId = ' . intval( $this->db->insert_id ) . ',
                    tagId=1'
                );
            }
        }
    }

    private function extractTitle( array $source ) : string {
        $title = $source['title'];
        if ( $source['namespace'] > 0 ) {
            $title = $source['namespace_text'] . ':' . $title;
        }
        return $title;
    }
}

$config = parse_ini_file( __DIR__ . '/../config.ini', true );
if ( file_exists( __DIR__ . '/../replica.my.cnf' ) ) {
    $config = array_merge(
        $config,
        parse_ini_file( __DIR__ . '/../replica.my.cnf', true )
    );
}
$job = new GetImagesForClassification( $config );
$job->run();
