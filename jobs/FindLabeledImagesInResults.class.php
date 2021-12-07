<?php

namespace MediaSearchSignalTest\Jobs;

require_once 'GenericJob.php';

/**
 * Searches commons for all the search terms that we have for our labeled results, and stores
 * any labeled images that are found, along with their rating
 */
class FindLabeledImagesInResults extends GenericJob {

    protected $useWikidataIds = false;

    public function __construct( array $config = [] ) {
        if ( !isset( $config['searchurl'] ) ) {
            $config['searchurl'] =
                /*'/w/api.php?action=query&list=search&uselang=%s&srsearch=connectedtowikidataid:%s' .
                '&srnamespace=6&srqiprofile=empty&srlimit=max&cirrusDumpResult';*/
                '/w/api.php?action=query&list=search&uselang=%s&srsearch=%s+filetype:bitmap' .
                '&srnamespace=6&srqiprofile=empty&srlimit=max&cirrusDumpResult' .
                '&mediasearch_weighted_tags';
        }
        if ( !isset( $config['description'] ) ) {
            $config['description'] = 'Search of ' . $config['searchurl'] . ' on ' .
                date('Y-m-d H:i:s');
        }
        parent::__construct( $config );
        if ( isset( $this->config['w'] ) ) {
            $this->useWikidataIds = true;
        }
        $this->setLogFileHandle( __DIR__ . '/../' . $this->config['log']['findLabeledImages'] );
    }

    public function run() {
        $searchTerms = $this->getSearchTerms();
        $searchId = $this->createSearchRecord();
        $this->log( 'Begin #' . $searchId . ': ' . $this->config['description'] );
        foreach ( $searchTerms as $searchTerm ) {
            if ( $this->useWikidataIds ) {
                if ( !$searchTerm['wikidataId'] ) {
                    continue;
                }
                $searchDescription = $searchTerm['wikidataId'] . ' in ' . $searchTerm['language'];
            } else {
                $searchDescription = $searchTerm['term'] . ' in ' . $searchTerm['language'];
            }
            $this->log( 'Searching ' . $searchDescription );
            $searchUrl = $this->getSearchUrl();
            try {
                $startTime = microtime( true );
                if ( $this->useWikidataIds ) {
                    $searchFor = $searchTerm['wikidataId'];
                } else {
                    $searchFor = $searchTerm['term'];
                }
                $results = $this->httpGETJson( $searchUrl, $searchTerm['language'], $searchFor );
                $apiResponseTime = ( microtime( true ) - $startTime ) * 1000;
                $this->processResults( $searchFor, $searchTerm['language'],
                    $results, $searchId, $apiResponseTime );
            } catch ( \Exception $e ) {
                $this->log( "Failed to fetch results for $searchDescription at $searchUrl\n" );
            }
        }
        $this->log( 'End #' . $searchId . ': ' . $this->config['description'] );
        return $searchId;
    }

    protected function getSearchUrl(): string {
        return $this->config['search']['baseUrl'].$this->config['searchurl'];
    }

    protected function createSearchRecord() {
        $this->db->query(
            'insert into search set ' .
            'description="' . $this->db->real_escape_string( $this->config['description'] ). '"'
        );
        return $this->db->insert_id;
    }

    protected function getSearchTerms() : array {
        $searchTerms = [];
        $query = 'select distinct searchTerm, language, searchTermExactMatchWikidataId from ' .
            'ratedSearchResult ';
        if ( isset( $this->config['tag'] ) ) {
            $query .= 'join ratedSearchResult_tag ' .
                'on ratedSearchResult_tag.ratedSearchResultId=ratedSearchResult.id ' .
                'join tag on ratedSearchResult_tag.tagId=tag.id ' .
                'where tag.text="' . $this->dbEscape( $this->config['tag'] ). '" ' .
                'and rating is not null ';
        } else {
            $query .= 'where rating is not null ';
        }
        $searchTermResults = $this->db->query( $query );
        while ( $row = $searchTermResults->fetch_assoc() ) {
            $searchTerms[] = [
                'term' => trim( $row['searchTerm'] ),
                'language' => $row['language'],
                'wikidataId' => $row['searchTermExactMatchWikidataId'],
            ];
        }
        return $searchTerms;
    }

    protected function processResults( string $searchFor, string $language, array $searchResults,
                                     int $searchId, int $apiResponseTime ) {
        if ( $this->useWikidataIds ) {
            $labeledData = $this->getLabeledDataForWikidataId( $searchFor, $language );
        } else {
            $labeledData = $this->getLabeledDataForTextSearchTerm( $searchFor, $language );
        }

        $hits = $searchResults['__main__']['result']['hits']['hits'] ?? [];
        if ( $hits ) {
            $this->log( 'Found ' . count( $hits ) . ' results' );
            $this->db->query(
            'insert into resultset set ' .
                'searchId=' . intval( $searchId ) . ', ' .
                'term="' .  $this->db->real_escape_string( $searchFor ) . '", ' .
                'language="' .  $this->db->real_escape_string( $language ) . '", ' .
                'searchExecutionTime_ms=' .  intval( $apiResponseTime ) . ', ' .
                'resultCount=' . intval( count( $hits ) )
            );
            $resultsetId = $this->db->insert_id;

            $labeledImageCount = 0;
            foreach ( $hits as $index => $hit ) {
                $title = $hit['_source']['title'];
                if ( isset( $labeledData[$title] ) ) {
                    $this->db->query(
                        'insert into labeledResult set ' .
                        'resultsetId=' . intval( $resultsetId ) . ', ' .
                        'filePage="' .  $this->db->real_escape_string( $title ). '", ' .
                        'position=' . intval( $index ) . ', ' .
                        'score=' . $hit['_score'] . ', ' .
                        'rating=' . intval( $labeledData[$title] )
                    );
                    $labeledImageCount++;
                }
            }
            $this->log( $labeledImageCount . ' of the results were labeled' );
        }
    }

    protected function getLabeledDataForTextSearchTerm( string $searchTerm, string $language ) : array {
        $return = [];
        $labeledImages = $this->db->query(
            'select distinct result, rating from ratedSearchResult where ' .
            'searchTerm="' . $this->db->real_escape_string( $searchTerm ) .'" and ' .
            'language="' . $this->db->real_escape_string( $language ) .'"'
        );
        while ( $labeledImage = $labeledImages->fetch_assoc() ) {
            $return[$labeledImage['result']] = $labeledImage['rating'];
        }
        return $return;
    }

    protected function getLabeledDataForWikidataId( string $wikidataId, string $language ) : array {
        $return = [];
        $labeledImages = $this->db->query(
            'select distinct result, rating from ratedSearchResult where ' .
            'searchTermExactMatchWikidataId="' . $this->db->real_escape_string( $wikidataId ) .'" and ' .
            'language="' . $this->db->real_escape_string( $language ) .'"'
        );
        while ( $labeledImage = $labeledImages->fetch_assoc() ) {
            $return[$labeledImage['result']] = $labeledImage['rating'];
        }
        return $return;
    }
}
