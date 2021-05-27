<?php

namespace MediaSearchSignalTest\Jobs;

require 'GenericJob.php';

class ImportImageRecommendationResults extends GenericJob {

    public function run() {
        $alreadyInserted = [];
        $fh = fopen( __DIR__ . '/../input/imageSuggestionRatingsMS.csv', 'r' );
        // skip first line
        $row = fgetcsv( $fh, null, "\t" );
        while ( $row = fgetcsv( $fh, null, "\t" ) ) {
            list( $langCode, $searchTerm, $resultFilePage,	$s,	$c, $rating ) = $row;
            $hash = md5(
                $searchTerm.$langCode.$resultFilePage
            );
            if ( !isset( $alreadyInserted[$hash] ) ) {
                $alreadyInserted[$hash] = 1;
                $this->db->query(
                    'insert into ratedSearchResult set ' .
                    'searchTerm = "' . $this->dbEscape( $searchTerm ). '", ' .
                    'language = "' .
                    $this->dbEscape( $langCode ) . '", ' .
                    'result = "' . $this->dbEscape( $this->transformFilePage( $resultFilePage ) ). '", ' .
                    'rating =' . intval( $rating )
                );
                $this->db->query(
                    'insert into ratedSearchResult_tag set ' .
                    'ratedSearchResultId = ' . intval( $this->db->insert_id ) . ',' .
                    'tagId=2'
                );
            }
        }
    }

    private function transformFilePage( $filePage ) {
        return str_replace( '_' , ' ', substr( $filePage, 5 ) );
    }
}

$job = new ImportImageRecommendationResults();
$job->run();