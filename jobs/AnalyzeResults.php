<?php

namespace MediaSearchSignalTest\Jobs;

require_once 'GenericJob.php';

/**
 * Calculates search quality metrics based on our labeled search results, and outputs them to stdout
 */
class AnalyzeResults extends GenericJob {

    private $searchId;

    public function __construct( array $config ) {
        parent::__construct( $config );
        $this->searchId = $config['searchId'];
    }

    public function run() {
        $maxResultCount = $this->db->query(
            'select max(resultcount) as count from resultset where searchId = ' .
            intval( $this->searchId )
        )->fetch_all( MYSQLI_ASSOC )[0]['count'];
        $resultsets = $this->db->query(
            'select id, term, resultcount from resultset where searchId = ' .
            intval( $this->searchId )
        )->fetch_all( MYSQLI_ASSOC );
        if ( count( $resultsets ) === 0 ) {
            die( "ERROR: no results found with for search id " . $this->searchId . "\n");
        }

        $truePositives = $falseNegatives = $falsePositives = $recalls =
            array_fill(1, $maxResultCount, 0 );
        $averagePrecision = 0;
        foreach ( $resultsets as $resultset ) {
            $knownGoodResults = (int) $this->db->query(
                'select count(*) as count from ratedSearchResult where ' .
                'searchTerm = "' . $this->db->real_escape_string( trim( $resultset['term'] ) ) . '" and ' .
                'rating > 0'
            )->fetch_object()->count;
            if ( $knownGoodResults === 0 ) {
                // skip terms for which were are not aware of the existence of
                // good results in the firsts place; those perfectly valid scores
                // of `0` would otherwise drag down the average, when it's not
                // the algorithm that is at fault - there simply isn't anything
                // to find
                continue;
            }

            for ( $i = 1 ; $i <= $maxResultCount ; $i++ ) {
                $truePositives[$i] += $this->getTruePositive( $resultset['id'], $i );
                $falsePositives[$i] += $this->getFalsePositive( $resultset['id'], $i );
                $falseNegatives[$i] += $this->getFalseNegative( $resultset['id'], $i,
                    $resultset['term'] );
            }
        }

        // Average precision
        // @see https://en.wikipedia.org/w/index.php?title=Information_retrieval&oldid=793358396#Average_precision
        for ( $i = 1 ; $i <= $maxResultCount ; $i++ ) {
            $recalls[$i] = $this->calculateRecall( $truePositives[$i], $falseNegatives[$i] );
            $precision = $this->calculatePrecision( $truePositives[$i], $falsePositives[$i] );
            $averagePrecision += ( $recalls[$i] - $recalls[$i-1]??0 ) * $precision;
        }

        $precision = $this->calculatePrecision( $truePositives[$maxResultCount],
            $falsePositives[$maxResultCount] );
        $f1Score = $this->calculateF1Score( $precision, $recalls[$maxResultCount] );

        $precisionAt10 = $this->calculatePrecision( $truePositives[10], $falsePositives[10] );
        $precisionAt25 = $this->calculatePrecision( $truePositives[25], $falsePositives[25] );
        $precisionAt50 = $this->calculatePrecision( $truePositives[50], $falsePositives[50] );
        $precisionAt100 = $this->calculatePrecision( $truePositives[100], $falsePositives[100] );

        return '' .
	        'F1 Score          | ' . $f1Score . "\n".
            'Precision@10      | ' . $precisionAt10 . "\n".
            'Precision@25      | ' . $precisionAt25 . "\n".
            'Precision@50      | ' . $precisionAt50 . "\n".
            'Precision@100     | ' . $precisionAt100 . "\n".
            'Recall            | ' . $recalls[$maxResultCount] . "\n".
            'Average precision | ' . $averagePrecision . "\n";
    }

    private function getTruePositive( int $resultsetId, int $offset ) : int {
        $count = 0;
        $ratings = $this->getRatings( $resultsetId );
        // looping rather than using array_filter() because can't use $offset in the filter
        // function
        foreach ( $ratings as $rating ) {
            if ( $rating['position'] + 1 > $offset ) {
                break;
            }
            if ( $rating['rating'] > 0 ) {
                $count++;
            }
        }
        return $count;
    }

    private function getFalsePositive( int $resultsetId, int $offset ) : int {
        $count = 0;
        $ratings = $this->getRatings( $resultsetId );
        // looping rather than using array_filter() because can't use $offset in the filter
        // function
        foreach ( $ratings as $rating ) {
            if ( $rating['position'] + 1 > $offset ) {
                break;
            }
            if ( $rating['rating'] < 0 ) {
                $count++;
            }
        }
        return $count;
    }

    private function getRatings( int $resultsetId ) {
        static $ratings = [];
        if ( !isset( $ratings[$resultsetId] ) ) {
            $ratings[$resultsetId] = $this->db->query(
                'select position,rating from labeledResult where ' .
                'resultsetId = ' . intval( $resultsetId ) . ' and ' .
                // results with zero scores can happen if we're using the url to tweak boosts for
                // specific search signals - these should probably be ignored
                'score > 0 '.
                'order by position'
            )->fetch_all(MYSQLI_ASSOC);
        }
        return $ratings[$resultsetId];
    }

    private function getFalseNegative( int $resultsetId, int $offset, string $searchTerm ) : int {
        static $allPositive = [];
        if ( !isset( $allPositive[$resultsetId] ) ) {
            $allPositive[$resultsetId] = $this->db->query(
                    'select count(distinct result) as count from ratedSearchResult where
                    searchTerm="' . $this->db->real_escape_string( trim( $searchTerm ) ) .'" and
                    rating > 0'
                )->fetch_assoc()['count'];
        }
        return $allPositive[$resultsetId] - $this->getTruePositive( $resultsetId, $offset );
    }

    private function calculatePrecision( int $truePositive, int $falsePositive ) : ?float {
        if ( $truePositive === 0 && $falsePositive === 0) {
            // if we don't know anything (positive or negative) about any of the
            // results in this subset, then we must ignore it
            return null;
        }
        return $truePositive / ( $truePositive + $falsePositive );
    }

    private function calculateRecall( int $truePositive, int $falseNegative ) : ?float {
        if ( $truePositive == 0 && $falseNegative == 0) {
            // if we don't know anything (positive or negative) about any of the
            // results in this set, then we must ignore it
            return null;
        }

        return $truePositive / ( $truePositive + $falseNegative );
    }

    private function calculateF1Score( float $precision = null, float $recall = null ) : ?float {
        if ( $precision === null || $recall === null ) {
            return null;
        }
        if ( $precision + $recall === 0.0 ) {
            return 0.0;
        }
        return 2 * ( ( $precision * $recall ) / ( $precision + $recall ) );
    }
}

$config = getopt( '', [ 'searchId:', 'description::' ] );
if ( !isset( $config['searchId'] ) ) {
    $findLabeledImagesJob = function() {
        include( __DIR__ . '/FindLabeledImagesInResults.php' );
        return $searchId;
    };
    $config['searchId'] = $findLabeledImagesJob();
}
$job = new AnalyzeResults( $config );
echo $job->run();
