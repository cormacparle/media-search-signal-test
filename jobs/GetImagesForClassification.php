<?php

namespace MediaSearchSignalTest\Jobs;

require 'GenericJob.php';

/**
 * Reads the search terms plus language in the specific input file, fetches images corresponding
 * to the search terms on commons, and stores the results with rating set to NULL in
 * `ratedSearchResult`
 */
class GetImagesForClassification extends GenericJob {

    private $searchUrl;
    private $searchTerms = [];

    public function __construct( array $config = null ) {
        parent::__construct( $config );

        $this->searchUrl = $this->config['search']['baseUrl'];

        $searchTermsFile =
            fopen( $config['searchTermsFile'], 'r' );
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
            $searchUrl = $this->getSearchUrl( $searchTerm, $language );
            $searchResults = $this->httpGETJson( $searchUrl );
            $this->storeResults( $searchTerm, $language, $searchResults );
        }
        $this->log( "End\n" );
    }

    private function getSearchUrl( string $searchTerm, string $language ) : string {
        return sprintf(
            $this->searchUrl . '/w/index.php?search=%s+filetype:bitmap&ns6=1&' .
            'cirrusDumpResult&limit=100&uselang=%s',
            urlencode( $searchTerm ),
            $language
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

$options = getopt( '', [ 'searchTermsFile:' ] );
if ( !isset( $options['searchTermsFile'] ) || !file_exists( $options['searchTermsFile'] ) ) {
    die( "ERROR: you must specify an existing file as a source for search terms using --searchTermsFile\n" );
}

$job = new GetImagesForClassification( $options );
$job->run();
