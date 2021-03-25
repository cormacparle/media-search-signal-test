<?php

namespace MediaSearchSignalTest\Jobs;

use mysqli;

class AnalyzeResults {

    private $db;
    private $searchId;
    private $out;

    public function __construct( array $config, int $searchId ) {
        $this->db = new mysqli(
            $config['db']['host'],
            $config['client']['user'],
            $config['client']['password'],
            $config['db']['dbname']
        );
        if ( $this->db->connect_error ) {
            die('DB connection Error (' . $this->db->connect_errno . ') '
                . $this->db->connect_error);
        }
        $this->out = fopen(
            __DIR__ . '/../out/AnalyzeResults_' . $searchId . '.csv',
            'w'
        );
        $this->searchId = $searchId;
    }

    public function __destruct() {
        fclose( $this->out );
    }

    public function run() {
        $resultsets = $this->db->query(
            'select id, term from resultset where searchId = ' . intval( $this->searchId )
        )->fetch_all( MYSQLI_ASSOC );
        if ( count( $resultsets ) === 0 ) {
            die( "ERROR: no results found with for search id " . $this->searchId . "\n");
        }

        fwrite(
            $this->out,
            "Term,F1Score,Precision@10,Precision@25,Precision@50,Precision@100,Recall\n"
        );

        $truePositives = $falsePositives = $falseNegatives = 0;
        $truePositivesAt10 = $truePositivesAt25 = $truePositivesAt50 = $truePositivesAt100 = 0;
        $falsePositivesAt10 = $falsePositivesAt25 = $falsePositivesAt50 = $falsePositivesAt100 = 0;
        foreach ( $resultsets as $resultset ) {
            $knownGoodResults = (int) $this->db->query(
                'select count(*) as count from results_by_component where ' .
                'term = "' . $this->db->real_escape_string( trim( $resultset['term'] ) ) . '" and ' .
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

            $truePositive = $this->getTruePositive( $resultset['id'], 99999999 );
            $falsePositive = $this->getFalsePositive( $resultset['id'], 99999999 );
            $falseNegative = $this->getFalseNegative( $resultset['id'], $resultset['term'] );

            $truePositiveAt10 = $this->getTruePositive( $resultset['id'], 10 );
            $truePositiveAt25 = $this->getTruePositive( $resultset['id'], 25 );
            $truePositiveAt50 = $this->getTruePositive( $resultset['id'], 50 );
            $truePositiveAt100 = $this->getTruePositive( $resultset['id'], 100 );

            $falsePositiveAt10 = $this->getFalsePositive( $resultset['id'], 10 );
            $falsePositiveAt25 = $this->getFalsePositive( $resultset['id'], 25 );
            $falsePositiveAt50 = $this->getFalsePositive( $resultset['id'], 50 );
            $falsePositiveAt100 = $this->getFalsePositive( $resultset['id'], 100 );

            $precision = $this->calculatePrecision( $truePositive, $falsePositive );
            $recall = $this->calculateRecall( $truePositive, $falseNegative );
            $f1Score = $this->calculateF1Score( $precision, $recall );

            $precisionAt10 = $this->calculatePrecision( $truePositiveAt10, $falsePositiveAt10 );
            $precisionAt25 = $this->calculatePrecision( $truePositiveAt25, $falsePositiveAt25 );
            $precisionAt50 = $this->calculatePrecision( $truePositiveAt50, $falsePositiveAt50 );
            $precisionAt100 = $this->calculatePrecision( $truePositiveAt100, $falsePositiveAt100 );

            fwrite( $this->out,
                $resultset['term'] . "," .
                ( $f1Score ?? '/' ) . "," .
                ( $precisionAt10 ?? '/' ) . "," .
                ( $precisionAt25 ?? '/' ) . "," .
                ( $precisionAt50 ?? '/' ) . "," .
                ( $precisionAt100 ?? '/' ) . "," .
                ( $recall ?? '/' ) . "\n"
            );

            // because the amount of known results varies greatly among resultsets,
            // we'll use the micro-average method to calculate the overall f1score
            // (if one resultset contains only 1 known value, it shouldn't have the
            // same weight as one for which we know 15 values, as the latter is much
            // more likely to be an accurate representation of reality - all labeled
            // documents get equal representation this way)
            // so we'll keep track of all relevant positive/negatives
            // @see http://rushdishams.blogspot.com/2011/08/micro-and-macro-average-of-precision.html
            $truePositives += $truePositive;
            $falsePositives += $falsePositive;
            $falseNegatives += $falseNegative;
            $truePositivesAt10 += $truePositiveAt10;
            $truePositivesAt25 += $truePositiveAt25;
            $truePositivesAt50 += $truePositiveAt50;
            $truePositivesAt100 += $truePositiveAt100;
            $falsePositivesAt10 += $falsePositiveAt10;
            $falsePositivesAt25 += $falsePositiveAt25;
            $falsePositivesAt50 += $falsePositiveAt50;
            $falsePositivesAt100 += $falsePositiveAt100;
        }

        $precision = $this->calculatePrecision( $truePositives, $falsePositives );
        $recall = $this->calculateRecall( $truePositives, $falseNegatives );
        $f1Score = $this->calculateF1Score( $precision, $recall );

        $precisionAt10 = $this->calculatePrecision( $truePositivesAt10, $falsePositivesAt10 );
        $precisionAt25 = $this->calculatePrecision( $truePositivesAt25, $falsePositivesAt25 );
        $precisionAt50 = $this->calculatePrecision( $truePositivesAt50, $falsePositivesAt50 );
        $precisionAt100 = $this->calculatePrecision( $truePositivesAt100, $falsePositivesAt100 );

        fwrite(
            $this->out,
            "OVERALL," .
            // overall f1score is not an arithmetic average of the individual f1scores,
            // but a calculation based on the harmonic means of all precision &
            // recall values, so that it's more sensitive to extremes
            ( $f1Score ?? '/' ) . "," .
            ( $precisionAt10 ?? '/' ) . "," .
            ( $precisionAt25 ?? '/' ) . "," .
            ( $precisionAt50 ?? '/' ) . "," .
            ( $precisionAt100 ?? '/' ) . "," .
            ( $recall ?? '/' ) . "\n"
        );

        return '' .
	        'F1 Score      | ' . ( $f1Score ?? '/' ) . "\n".
            'Precision@10  | ' . ( $precisionAt10 ?? '/' ) . "\n".
            'Precision@25  | ' . ( $precisionAt25 ?? '/' ) . "\n".
            'Precision@50  | ' . ( $precisionAt50 ?? '/' ) . "\n".
            'Precision@100 | ' . ( $precisionAt100 ?? '/' ) . "\n".
            'Recall        | ' . ( $recall ?? '/' ) . "\n";
    }

    private function getTruePositive( int $resultsetId, int $offset ) : int {
        return (int) $this->db->query(
            'select count(*) as count from labeledResult where ' .
            'resultsetId = ' . intval( $resultsetId ) . ' and ' .
            'position < ' . intval($offset) . ' and ' . // only count hits before a given offset
            // results with zero scores can happen if we're using the url to tweak boosts for
            // specific search signals - these should probably be ignored
            'score > 0 and ' .
            'rating = 1'
        )->fetch_object()->count;
    }

    private function getFalsePositive( int $resultsetId, int $offset ) : int {
        return (int) $this->db->query(
            'select count(*) as count from labeledResult where ' .
            'resultsetId = ' . intval( $resultsetId ) . ' and ' .
            'position < ' . intval($offset) . ' and ' . // only count hits before a given offset
            // results with zero scores can happen if we're using the url to tweak boosts for
            // specific search signals - these should probably be ignored
            'score > 0 and ' .
            'rating = -1'
        )->fetch_object()->count;
    }

    private function getFalseNegative( int $resultsetId, string $searchTerm ) : int {
        $truePositive = $this->getTruePositive( $resultsetId, 99999999 );

        // false negative is going to be an approximation based on looking at
        // how many of the known good matches are not present in the resultset;
        // (note: we have to make sure they're not missing because only a part
        // of the results was requested; i.e. limit too low...)
        $ratings = [];
        $labeledImages = $this->db->query(
            'select distinct file_page, rating from results_by_component where
            term="' . $this->db->real_escape_string( trim( $searchTerm ) ) .'"
            and rating is not null'
        );
        while ( $labeledImage = $labeledImages->fetch_assoc() ) {
            if (
                isset($return[$labeledImage['file_page']]) &&
                $ratings[$labeledImage['file_page']] !== $labeledImage['rating']
            ) {
                // guard against conflicting ratings
                unset($ratings[$labeledImage['file_page']]);
            } else {
                $ratings[$labeledImage['file_page']] = $labeledImage['rating'];
            }
        }
        $knownPositive = count( array_filter( $ratings, function ( $rating ) {
            return (int) $rating === 1;
        } ) );

        return $knownPositive - $truePositive;
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

$config = parse_ini_file( __DIR__ . '/../config.ini', true );
if ( file_exists( __DIR__ . '/../replica.my.cnf' ) ) {
    $config = array_merge(
        $config,
        parse_ini_file( __DIR__ . '/../replica.my.cnf', true )
    );
}

$options = getopt( '', [ 'searchId:', 'description::' ] );
if ( isset( $options['searchId'] ) ) {
    $searchId = $options['searchId'];
} else {
    $findLabeledImagesJob = function() {
        include( __DIR__ . '/FindLabeledImagesInResults.php' );
        return $searchId;
    };
    $searchId = $findLabeledImagesJob();
}
$job = new AnalyzeResults( $config, $searchId );
echo $job->run();
