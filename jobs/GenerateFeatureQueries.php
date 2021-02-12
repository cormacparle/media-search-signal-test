<?php

namespace MediaSearchSignalTest\Jobs;

use mysqli;

require __DIR__ . '/../vendor/autoload.php';

interface QueryJsonCreator {
    public function createQueryString( array $searchTermsRow, array $titles ) :
    string;
}
class MediaSearch_20210127 implements QueryJsonCreator {
    public function createQueryString( array $searchTermsRow, array $titles ) :
    string {
        $params = [
            'textSearchTerm' => addcslashes( trim( $searchTermsRow[1] ), '"' ),
            'languageCode' => trim( $searchTermsRow[2] ),
            'commaSeparatedTitles' => '"' . implode("\",\n\"", $titles ) . "\"\n",
        ];
        for ( $i = 1; $i < count( $searchTermsRow ) - 2 ; $i++ ) {
            $params[ 'DigRepOf_' . $i ] = 'P6243=' . trim( $searchTermsRow[$i + 2] );
            $params[ 'Depicts_' . $i ] = 'P180=' . trim( $searchTermsRow[$i + 2] );
        }

        $m = new \Mustache_Engine( ['entity_flags' => ENT_NOQUOTES] );
        return $m->render(
            file_get_contents( __DIR__ . '/../input/MediaSearch_20210127_template.json' ),
            $params
        );
    }
}

class GenerateFeatureQueries {

    private $db;
    private $searchTerms;
    private $log;
    /** @var QueryJsonCreator  */
    private $queryStringGenerator;

    public function __construct( array $config ) {
        $this->db = new mysqli( $config['db']['host'], $config['client']['user'],
            $config['client']['password'], $config['db']['dbname'] );
        if ( $this->db->connect_error ) {
            die('DB connection Error (' . $this->db->connect_errno . ') '
                . $this->db->connect_error);
        }

        $this->searchTerms =
            file( __DIR__ . '/../' . $config['search']['searchTermsWithEntitiesFile'] );

        $this->log = fopen(
            __DIR__ . '/../' . $config['log']['generateFeatureQueries'],
            'a'
        );
        $this->outputDir = __DIR__ . '/../' . $config['ltr']['queriesOutputDir'] . '/';
        $this->queryStringGenerator = new $config['queryJsonGenerator'];
    }

    public function __destruct() {
        fclose( $this->log );
    }

    public function run() {
        $this->log( 'Begin' . "\n" );
        foreach ( $this->searchTerms as $index => $searchTermsRowString ) {
            $searchTermsRow = explode( ',', $searchTermsRowString );
            $this->log( 'Creating query for ' . $searchTermsRow[1] );
            $titles = [];
            $labeledImages = $this->db->query(
                'select distinct file_page from results_by_component where ' .
                'term ="' . $this->db->real_escape_string( $searchTermsRow[1] ) . '" and ' .
                'rating is not null'
            );
            while ( $labeledImage = $labeledImages->fetch_object() ) {
                $title = trim( $labeledImage->file_page );
                $titleNoNamespace = str_replace( '_', ' ', substr( $title, 5 ) );
                $titles[] = addcslashes( $titleNoNamespace, '"' );
            }
            if ( count( $titles ) > 0 ) {
                $query = $this->queryStringGenerator->createQueryString( $searchTermsRow, $titles );
                file_put_contents( $this->getOutputFilename( $index ), $query );
            }
        }
        $this->log( 'End' . "\n" );
    }

    private function getOutputFilename( $index ) {
        $classNameArray = explode( '\\', get_class( $this->queryStringGenerator ) );
        return $this->outputDir . DIRECTORY_SEPARATOR .
            end( $classNameArray ) . '_' . $index  . '.json';
    }

    private function log( string $msg ) {
        fwrite( $this->log, date( 'Y-m-d H:i:s' ) . ': ' . $msg . "\n" );
    }
}

$options = getopt( '', [ 'queryJsonGenerator:' ] );
$config = array_merge(
    parse_ini_file( __DIR__ . '/../config.ini', true ),
    parse_ini_file( __DIR__ . '/../replica.my.cnf', true ),
    $options
);
$job = new GenerateFeatureQueries( $config );
$job->run();