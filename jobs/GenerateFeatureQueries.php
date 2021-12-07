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
class MediaSearch_20211112 implements QueryJsonCreator {
    private static $stemmedFields = [
        'ar', 'bg', 'ca', 'ckb', 'cs', 'da', 'de', 'el', 'en', 'en-ca', 'en-gb', 'es', 'eu',
        'fa', 'fi', 'fr', 'ga', 'gl', 'he', 'hi', 'hu', 'hy', 'id', 'it', 'ja', 'ko', 'lt', 'lv',
        'nb', 'nl', 'nn', 'pl', 'pt', 'pt-br', 'ro', 'ru', 'sv', 'th', 'tr', 'uk', 'zh'
    ];
    public function createQueryString( array $searchTermsRow, array $titles ) :
    string {
        $langCode = trim( $searchTermsRow[2] );
        $params = [
            'textSearchTerm' => addcslashes( trim( $searchTermsRow[1] ), '"' ),
            'languageCode' => $langCode,
            'commaSeparatedTitles' => '"' . implode("\",\n\"", $titles ) . "\"\n",
        ];
        $template = file_get_contents( __DIR__ . '/../input/MediaSearch_20211112_template.json' );
        if ( in_array( $langCode, self::$stemmedFields ) ) {
            $template = str_replace( "MediaSearch_20211112",
                "MediaSearch_20211112_stemmed", $template );
        }
        for ( $i = 1; $i < count( $searchTermsRow ) - 2 ; $i++ ) {
            $params[ 'Item_' . $i ] = trim( $searchTermsRow[$i + 2] );
        }

        $m = new \Mustache_Engine( ['entity_flags' => ENT_NOQUOTES] );
        return $m->render( $template, $params );
    }
}

class Connectedtowikidataid implements QueryJsonCreator {
    public function createQueryString( array $searchTermsRow, array $titles ) :
    string {
        $params = [
            'commaSeparatedTitles' => '"' . implode("\",\n\"", $titles ) . "\"\n",
            'wikidataId' => $searchTermsRow[1],
            'language' =>trim( $searchTermsRow[2] ),
        ];
        $m = new \Mustache_Engine( ['entity_flags' => ENT_NOQUOTES] );
        return $m->render(
            file_get_contents( __DIR__ . '/../input/Connectedtowikidataid_template.json' ),
            $params
        );
    }
}

class MediaSearch_20211206 implements QueryJsonCreator {
    private static $stemmedFields = [
        'ar', 'bg', 'ca', 'ckb', 'cs', 'da', 'de', 'el', 'en', 'en-ca', 'en-gb', 'es', 'eu',
        'fa', 'fi', 'fr', 'ga', 'gl', 'he', 'hi', 'hu', 'hy', 'id', 'it', 'ja', 'ko', 'lt', 'lv',
        'nb', 'nl', 'nn', 'pl', 'pt', 'pt-br', 'ro', 'ru', 'sv', 'th', 'tr', 'uk', 'zh'
    ];
    public function createQueryString( array $searchTermsRow, array $titles ) :
    string {
        $langCode = trim( $searchTermsRow[2] );
        $params = [
            'textSearchTerm' => addcslashes( trim( $searchTermsRow[1] ), '"' ),
            'languageCode' => $langCode,
            'commaSeparatedTitles' => '"' . implode("\",\n\"", $titles ) . "\"\n",
        ];
        $template = file_get_contents( __DIR__ . '/../input/MediaSearch_20211206_template.json' );
        if ( in_array( $langCode, self::$stemmedFields ) ) {
            $template = str_replace( "MediaSearch_20211206_plain",
                "MediaSearch_20211206_stemmed", $template );
        }
        for ( $i = 1; $i < count( $searchTermsRow ) - 3 ; $i++ ) {
            $params[ 'Item_' . $i ] = trim( $searchTermsRow[$i + 2] );
        }
        $params[ 'title_match_item_id' ] = $searchTermsRow[ count( $searchTermsRow ) - 1 ];

        $m = new \Mustache_Engine( ['entity_flags' => ENT_NOQUOTES] );
        return $m->render( $template, $params );
    }
}

class GenerateFeatureQueries extends GenericJob {

    private $searchTerms;
    /** @var QueryJsonCreator  */
    private $queryStringGenerator;
    private $wikidataIdsAsSearchTerms = false;

    public function __construct( array $config ) {
        parent::__construct( $config );
        $this->setLogFileHandle( __DIR__ . '/../' . $this->config['log']['generateFeatureQueries'] );

        $this->searchTerms =
            file( __DIR__ . '/../' . $this->config['searchTermsWithEntitiesFile'] );

        $this->outputDir = __DIR__ . '/../' . $this->config['ltr']['queriesOutputDir'] . '/';
        $this->queryStringGenerator = new $this->config['queryJsonGenerator'];
        if ( isset( $this->config['w'] ) ) {
            $this->wikidataIdsAsSearchTerms = true;
        }
    }

    public function run() {
        $this->log( 'Begin' . "\n" );
        foreach ( $this->searchTerms as $index => $searchTermsRowString ) {
            $searchTermsRow = array_map( 'trim',
                explode( ',', $searchTermsRowString ) );
            $this->log( 'Creating query for ' . $searchTermsRow[1] . ' in ' . $searchTermsRow[2] );
            $titles = [];
            $query = 'select result from ratedSearchResult where ';
            if ( $this->wikidataIdsAsSearchTerms ) {
                $query .= 'searchTermExactMatchWikidataId ="' .
                    $this->db->real_escape_string( $searchTermsRow[1] ) . '" ';
            } else {
                $query .= 'searchTerm ="' . $this->db->real_escape_string( $searchTermsRow[1] ) .
                    '" ';
            }
            $query .= 'and language ="' . $this->db->real_escape_string( $searchTermsRow[2] ) .
                '" and rating is not null ';
            $labeledImages = $this->db->query( $query );
            while ( $labeledImage = $labeledImages->fetch_object() ) {
                $title = trim( $labeledImage->result );
                $titleNoNamespace = preg_replace( '/.+:/', '', $title );
                $titles[] = addcslashes( $titleNoNamespace, '"' );
            }
            if ( count( $titles ) > 0 ) {
                $this->log( count( $titles ) . ' titles found' );
                $query = $this->queryStringGenerator->createQueryString( $searchTermsRow, $titles );
                file_put_contents( $this->getOutputFilename( $searchTermsRow[0] ), $query );
            } else {
                $this->log( 'No titles found' );
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

$options = getopt( 'w', [ 'queryJsonGenerator:', 'searchTermsWithEntitiesFile:' ] );
$job = new GenerateFeatureQueries( $options );
$job->run();
