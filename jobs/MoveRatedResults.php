<?php

namespace MediaSearchSignalTest\Jobs;

require 'GenericJob.php';

class MoveRatedResults extends GenericJob {

    private $languagesByTerm = [];

    public function __construct( array $config = null ) {
        parent::__construct( $config );
        // load languages for search terms
        $fh = fopen( __DIR__ . '/../input/searchTerms.csv', 'r' );
        while ( $row = fgetcsv( $fh ) ) {
            $this->languagesByTerm[ $row[1] ] = $row[2];
        }
    }

    public function run() {
        $alreadyInserted = [];
        $result = $this->db->query(
            'select * from results_by_component where rating is not null'
        );
        while ( $row = $result->fetch_assoc() ) {
            $hash = md5(
                $row['term'].$this->getLanguageForTerm( $row['term'] ).$row['file_page']
            );
            if ( !isset( $alreadyInserted[$hash] ) ) {
                $alreadyInserted[$hash] = 1;
                $this->db->query(
                    'insert into ratedSearchResult set ' .
                    'searchTerm = "' . $this->dbEscape( $row['term'] ). '", ' .
                    'language = "' .
                    $this->dbEscape( $this->getLanguageForTerm( $row['term'] ) ) . '", ' .
                    'result = "' . $this->dbEscape( $this->transformFilePage( $row['file_page'] ) ). '", ' .
                    'rating =' . intval( $row['rating'] )
                );
                $this->db->query(
                    'insert into ratedSearchResult_tag set ' .
                    'ratedSearchResultId = ' . intval( $this->db->insert_id ) . ',' .
                    'tagId=1'
                );
            }
        }
    }

    private function getLanguageForTerm( $term ) {
        if ( !isset( $this->languagesByTerm[ $term ] ) ) {
            echo( "Language not found for " . $term ."\n" );
            exit;
        }
        return $this->languagesByTerm[ $term ];
    }

    private function transformFilePage( $filePage ) {
        return str_replace( '_' , ' ', substr( $filePage, 5 ) );
    }
}

$job = new MoveRatedResults();
$job->run();