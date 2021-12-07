<?php

namespace MediaSearchSignalTest\Jobs;

require_once 'GenericJob.php';
require_once 'FindLabeledImagesInResults.class.php';

/**
 * Searches the IMA for all the search terms that we have for our labeled results, and stores
 * any labeled images that are found, along with their rating
 */
class FindLabeledImagesInResultsIMA extends FindLabeledImagesInResults {

    public function __construct( array $config = [] ) {
        parent::__construct( $config );
        $this->config['searchurl'] = 'https://image-suggestion-api.wmcloud.org/image-suggestions/v0/' .
            'wikipedia/%s/pages/%s?source=ima';
    }

    protected function getSearchUrl(): string {
        return $this->config['searchurl'];
    }

    /**
     * Only return search terms for which we have an exact-match wikidata id, because that's
     * what we're doing in the comparison search
     *
     * @return array
     */
    protected function getSearchTerms() : array {
        $searchTerms = [];
        $origSearchTerms = parent::getSearchTerms();
        foreach ( $origSearchTerms as $origSearchTerm ) {
            if ( $origSearchTerm['wikidataId'] ) {
                $term = str_replace( ' ', '_', $origSearchTerm['term'] );
                if ( $origSearchTerm['language'] != 'bn' ) {
                    $term = ucfirst( $term );
                }
                $searchTerms[] = [
                    'term' => $term,
                    'language' => $origSearchTerm['language'],
                    'wikidataId' => $origSearchTerm['wikidataId'],
                ];
            }
        }
        return $searchTerms;
    }

    protected function processResults( string $searchFor, string $language, array $searchResults,
                                       int $searchId, int $apiResponseTime ) {
        $labeledData = $this->getLabeledDataForTextSearchTerm( $searchFor, $language );

        if ( !isset( $searchResults['pages'] ) ) {
            $this->log( 'Found 0 results (couldn\'t find wikidata id for page)' );
            return;
        }
        if ( count( $searchResults['pages'] ) > 0 ) {
            $suggestions = $searchResults['pages'][0]['suggestions'];
            $this->log( 'Found ' . count( $suggestions ) . ' results' );
            $query = 'insert into resultset set ' .
                'searchId=' . intval( $searchId ) . ', ' .
                'term="' .  $this->db->real_escape_string( $searchFor ) . '", ' .
                'language="' .  $this->db->real_escape_string( $language ) . '", ' .
                'searchExecutionTime_ms=' .  intval( $apiResponseTime ) . ', ' .
                'resultCount=' . intval( count( $suggestions ) );
            $this->db->query( $query );
            $resultsetId = $this->db->insert_id;

            $labeledImageCount = 0;
            foreach ( $suggestions as $index => $suggestion ) {
                $title = $suggestion['filename'];
                if ( isset( $labeledData[$title] ) ) {
                    $query = 'insert into labeledResult set ' .
                        'resultsetId=' . intval( $resultsetId ) . ', ' .
                        'filePage="' .  $this->db->real_escape_string( $title ). '", ' .
                        'position=' . intval( $index ) . ', ' .
                        'score=-1,' .
                        'confidence="' .
                        $this->db->real_escape_string ( $suggestion['confidence_rating'] ) . '", ' .
                        'rating=' . intval( $labeledData[$title] );
                    $this->db->query( $query );
                    $labeledImageCount++;
                }
            }
            $this->log( $labeledImageCount . ' of the results were labeled' );
        }
    }
}

$options = getopt('w', [ 'description::', 'tag::' ]);
$job = new FindLabeledImagesInResultsIMA( $options );
$searchId = $job->run();
