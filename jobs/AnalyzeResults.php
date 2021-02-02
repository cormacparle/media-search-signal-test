<?php

namespace MediaSearchSignalTest\Jobs;

use mysqli;

class AnalyzeResults {

    private $db;
    private $searchId;
    private $out;

    public function __construct( array $config, int $searchId ) {
        $this->db = new mysqli( $config['db']['host'], $config['client']['user'],
            $config['client']['password'], $config['db']['dbname'] );
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
            'select id,term from resultset where searchId = ' . intval( $this->searchId )
        )->fetch_all( MYSQLI_ASSOC );
        if ( count( $resultsets ) == 0 ) {
            die( "ERROR: no results found with for search id " . $this->searchId . "\n");
        }

        fwrite(
            $this->out,
            "Term,f1Score\n"
        );

        $f1Scores = $precisionTop30s = [];
        foreach ( $resultsets as $resultset ) {
            $f1Scores[$resultset['term']] = $this->calculateF1Score(
                $resultset['id'], $resultset['term'] );
            $precisionTop30s[$resultset['term']] = $this->calculatePrecisionTop30(
                $resultset['id'], $resultset['term'] );
            fwrite( $this->out,
                $resultset['term'] . "," .
                $f1Scores[$resultset['term']] . "," .
                $precisionTop30s[$resultset['term']] . "\n" );
        }
        fwrite(
            $this->out,
            "ARITHMETIC MEAN," .
            array_sum( $f1Scores ) / count( $resultsets) . "," .
            array_sum( $precisionTop30s ) / count( $resultsets) .
            "\n"
        );
    }

    private function calculateF1Score( int $resultsetId, string $searchTerm ) : float {
        $knownGoodImageCount = $this->db->query(
            'select count(*) as count from results_by_component where ' .
            'term = "' . $this->db->real_escape_string( trim( $searchTerm ) ) . '" and ' .
            'rating=1'
        )->fetch_object()->count;
        $foundGoodImageCount = $this->db->query(
            'select count(*) as count from labeledResult where ' .
            'resultsetId = "' . intval( $resultsetId ) . '" and ' .
            'rating=1'
        )->fetch_object()->count;
        if ( $foundGoodImageCount == 0 ) {
            return 0;
        }
        $foundBadImageCount = $this->db->query(
            'select count(*) as count from labeledResult where ' .
            'resultsetId = "' . intval( $resultsetId ) . '" and ' .
            'rating<1'
        )->fetch_object()->count;

        $precision = $foundGoodImageCount / ( $foundGoodImageCount + $foundBadImageCount );
        $recall = $foundGoodImageCount / $knownGoodImageCount;

        return 2 * ( ( $precision * $recall ) / ( $precision + $recall ) );
    }

    /**
     * @param int $resultsetId
     * @param string $searchTerm
     * @return float
     */
    private function calculatePrecisionTop30( int $resultsetId, string $searchTerm ) : float {
        $knownGoodImageCount = $this->db->query(
            'select count(*) as count from results_by_component where ' .
            'term = "' . $this->db->real_escape_string( trim( $searchTerm ) ) . '" and ' .
            'rating=1 and position < 31'
        )->fetch_object()->count;
        $foundGoodImageCount = $this->db->query(
            'select count(*) as count from labeledResult where ' .
            'resultsetId = "' . intval( $resultsetId ) . '" and ' .
            'rating=1'
        )->fetch_object()->count;
        if ( $foundGoodImageCount == 0 ) {
            return 0;
        }
        $foundBadImageCount = $this->db->query(
            'select count(*) as count from labeledResult where ' .
            'resultsetId = "' . intval( $resultsetId ) . '" and ' .
            'rating<1 and position < 31'
        )->fetch_object()->count;

        $precision = $foundGoodImageCount / ( $foundGoodImageCount + $foundBadImageCount );
        return $precision;
    }
}

$config = array_merge(
    parse_ini_file( __DIR__ . '/../config.ini', true ),
    parse_ini_file( __DIR__ . '/../replica.my.cnf', true )
);

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
$job->run();