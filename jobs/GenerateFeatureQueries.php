<?php

namespace MediaSearchSignalTest\Jobs;

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'GenericJob.php';

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
class MediaSearch_20210826 implements QueryJsonCreator {
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
            file_get_contents( __DIR__ . '/../input/MediaSearch_20210826_template.json' ),
            $params
        );
    }
}

class GenerateFeatureQueries extends GenericJob {

    private $searchTerms;
    /** @var QueryJsonCreator  */
    private $queryStringGenerator;

    public function __construct( array $config ) {
        parent::__construct( $config );
        $this->setLogFileHandle( __DIR__ . '/../' . $this->config['log']['generateFeatureQueries'] );

        $this->searchTerms =
            file( __DIR__ . '/../' . $this->config['searchTermsWithEntitiesFile'] );

        $this->outputDir = __DIR__ . '/../' . $this->config['ltr']['queriesOutputDir'] . '/';
        $this->queryStringGenerator = new $this->config['queryJsonGenerator'];
    }

    public function run() {
        $this->log( 'Begin' . "\n" );
        foreach ( $this->searchTerms as $index => $searchTermsRowString ) {
            $searchTermsRow = explode( ',', $searchTermsRowString );
            $this->log( 'Creating query for ' . $searchTermsRow[1] . ' in ' . $searchTermsRow[2] );
            $titles = [];
            $labeledImages = $this->db->query(
                'select result from ratedSearchResult where ' .
                'searchTerm ="' . $this->db->real_escape_string( $searchTermsRow[1] ) . '" and ' .
                'language ="' . $this->db->real_escape_string( $searchTermsRow[2] ) . '" and ' .
                'rating is not null'
            );
            while ( $labeledImage = $labeledImages->fetch_object() ) {
                $title = trim( $labeledImage->result );
                $titleNoNamespace = preg_replace( '/.+:/', '', $title );
                $titles[] = addcslashes( $titleNoNamespace, '"' );
            }
            if ( count( $titles ) > 0 ) {
                $query = $this->queryStringGenerator->createQueryString( $searchTermsRow, $titles );
                file_put_contents( $this->getOutputFilename( $searchTermsRow[0] ), $query );
            }
        }
        $this->log( 'End' . "\n" );
    }

    private function getOutputFilename( $index ) {
        $classNameArray = explode( '\\', get_class( $this->queryStringGenerator ) );
        return $this->outputDir . DIRECTORY_SEPARATOR .
            end( $classNameArray ) . '_' . $index  . '.json';
    }
}

$options = getopt( '', [ 'queryJsonGenerator:', 'searchTermsWithEntitiesFile:' ] );
$job = new GenerateFeatureQueries( $options );
$job->run();
