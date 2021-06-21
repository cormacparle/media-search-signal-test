<?php

namespace MediaSearchSignalTest\Jobs;

require_once 'GenericJob.php';

/**
 * Searches commons for all the search terms that we have for our labeled results, and stores
 * any labeled images that are found, along with their rating
 */
class FindLabeledImagesInResults extends GenericJob {

    public function __construct( array $config = [] ) {
        if ( !isset( $config['searchurl'] ) ) {
            $config['searchurl'] =
                '/w/api.php?action=query&generator=search&gsrsearch=filetype:bitmap|drawing+%s' .
                '&gsrlimit=max&gsroffset=0&gsrnamespace=6&format=json&uselang=%s&cirrusDumpResult';
        }
        if ( !isset( $config['description'] ) ) {
            $config['description'] = 'Search of ' . $config['searchurl'] . ' on ' .
                date('Y-m-d H:i:s');
        }
        parent::__construct( $config );
        $this->setLogFileHandle( __DIR__ . '/../' . $this->config['log']['findLabeledImages'] );
    }

    public function run() {
        $searchId = $this->createSearchRecord();
        $this->log( 'Begin #' . $searchId . ': ' . $this->config['description'] );
        foreach ( $this->getSearchTerms() as $searchTerm ) {
            $this->log( 'Searching ' . $searchTerm['term'] . ' in ' . $searchTerm['language'] );
            $searchUrl = $this->config['search']['baseUrl'].$this->config['searchurl'];
            try {
                $results = $this->httpGETJson( $searchUrl, $searchTerm['term'], $searchTerm['language'] );
                $this->processResults( $searchTerm['term'], $searchTerm['language'],
                    $results, $searchId );
            } catch ( \Exception $e ) {
                $this->log( "Failed to fetch {$searchTerm['language']} results for {$searchTerm['term']} at $searchUrl\n" );
            }
        }
        $this->log( 'End #' . $searchId . ': ' . $this->config['description'] );
        return $searchId;
    }

    private function createSearchRecord() {
        $this->db->query(
            'insert into search set ' .
            'description="' . $this->db->real_escape_string( $this->config['description'] ). '"'
        );
        return $this->db->insert_id;
    }

    private function getSearchTerms() : array {
        $searchTerms = [];
        $query = 'select distinct searchTerm, language from ratedSearchResult where rating is not null';
        if ( isset( $this->config['tag'] ) ) {
            $query .= ' join ratedSearchResult_tag ' .
                'on ratedSearchResult_tag.ratedSearchResultId=ratedSearchResult.id ' .
                'join tag on ratedSearchResult_tag.tagId=tag.id ' .
                ' where tag.text="' . $this->dbEscape( $this->config['tag'] ). '"';
        }
        $query .= ' limit 10';
        $searchTermResults = $this->db->query( $query );
        while ( $row = $searchTermResults->fetch_assoc() ) {
            $searchTerms[] = [
                'term' => trim( $row['searchTerm'] ),
                'language' => $row['language']
            ];
        }
        return $searchTerms;
    }

    private function processResults( string $searchTerm, string $language, array $searchResults,
                                     int $searchId ) {
        $labeledData = $this->getLabeledData( $searchTerm, $language );

        $hits = $searchResults['__main__']['result']['hits']['hits'] ?? [];
        if ( $hits ) {
            $this->log( 'Found ' . count( $hits ) . ' results' );
            $this->db->query(
            'insert into resultset set ' .
                'searchId=' . intval( $searchId ) . ', ' .
                'term="' .  $this->db->real_escape_string( $searchTerm ) . '", ' .
                'language="' .  $this->db->real_escape_string( $language ) . '", ' .
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

    private function getLabeledData( string $searchTerm, string $language ) : array {
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
}

$options = getopt('', [ 'description::', 'searchurl::', 'tag::' ]);

$job = new FindLabeledImagesInResults(
    $options
);
$searchId = $job->run();
