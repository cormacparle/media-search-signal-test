<?php

namespace MediaSearchSignalTest\Jobs;

use mysqli;

class GetImagesForClassification {

    private $db;
    private $searchUrl;
    private $searchTerms;
    private $searchComponents = [
        'statement',
        'caption',
        'title',
        'category',
        'heading',
        'auxiliary_text',
        'file_text',
        'redirect.title',
        'suggest',
        'text',
    ];
    private $ch;
    private $log;

    public function __construct( array $config ) {
        $this->db = new mysqli( $config['db']['host'], $config['client']['user'],
            $config['client']['password'], $config['db']['dbname'] );
        if ( $this->db->connect_error ) {
            die('DB connection Error (' . $this->db->connect_errno . ') '
                . $this->db->connect_error);
        }

        $this->searchUrl = $config['search']['baseUrl'];
        $this->searchTerms =
            file( __DIR__ . '/../' . $config['search']['searchTermsFile'] );

        $this->ch = curl_init();
        curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, true );

        $this->log = fopen(
            __DIR__ . '/../' . $config['log']['getImages'],
            'a'
        );
    }

    public function __destruct() {
        curl_close( $this->ch );
        fclose( $this->log );
    }

    public function run() {
        $this->log( 'Begin' . "\n" );
        foreach ( $this->searchTerms as $searchTerm ) {
            $searchTerm = trim( $searchTerm );
            foreach ( $this->searchComponents as $component ) {
                $this->log( 'Searching ' . $searchTerm . ' using ' . $component . "\n" );
                $this->storeResults(
                    $searchTerm,
                    $component,
                    $this->search( $searchTerm, $component )
                );
            }
        }
        $this->log( 'End' . "\n" );
    }

    public function log( string $msg ) {
        fwrite( $this->log, date( 'Y-m-d H:i:s' ) . ': ' . $msg );
    }

    private function search( string $searchTerm, string $component ) : array {
        curl_setopt( $this->ch, CURLOPT_URL, $this->getSearchUrl( $searchTerm, $component ) );
        $result = curl_exec( $this->ch );
        if ( curl_errno( $this->ch ) ) {
            $this->log( curl_error( $this->ch ) . ':' . curl_errno( $this->ch ) );
            die( 'Exiting because of curl error, see log for details.' );
        }
        $array = json_decode( $result, true );
        return $array;
    }

    private function getSearchUrl( string $searchTerm, string $component ) : string {
        return sprintf(
            $this->searchUrl . '/w/index.php?search=%s+filetype:bitmap&ns6=1&' .
            'cirrusDumpResult&mediasearch=1&limit=100%s',
            urlencode( $searchTerm ),
            $this->getBoostQueryParams( $component )
        );
    }

    private function getBoostQueryParams( string $componentToBoost ) {
        return array_reduce(
                $this->searchComponents,
                function ( $carry, $component ) use ( $componentToBoost ) {
                    if ( $component == $componentToBoost ) {
                        return $carry . '&boost:' . $component . '=1';
                    }
                    return $carry . '&boost:' . $component . '=0';
                },
                ''
            ) . '&boost:non-file_namespace_boost=0';
    }

    private function storeResults( string $searchTerm, string $component, array $searchResults ) {
        $titles = [];
        if ( isset( $searchResults['__main__']['result']['hits']['hits'] ) ) {
            foreach ( $searchResults['__main__']['result']['hits']['hits'] as $index => $result ) {
                $titles[] = [
                    'title' => $this->extractTitle( $result['_source'] ),
                    'score' => $result['_score'],
                ];
            }
        }
        if ( count( $titles ) > 0) {
            $titleMetaData = $this->getFileMetadata( array_column( $titles, 'title' ) );
            foreach ( $titles as $index => $title ) {
                $query = 'insert into results_by_component set ' .
                        ' position= ' . intval( $index + 1 ) . ', ' .
						' term="' . $this->db->real_escape_string( $searchTerm ) . '", '.
						' component="' . $this->db->real_escape_string( $component ) . '", ' .
						' score=' .  floatval( $title['score'] ) . ', ' .
						' file_page="' . $this->db->real_escape_string( $title['title'] ) . '", ' .
						' image_url="' . $this->db->real_escape_string(
						    $this->getImageUrl( $title['title'], $titleMetaData )
                        ) . '"';
                $this->db->query( $query  );
            }
        }
    }

    private function extractTitle( array $source ) : string {
        $title = str_replace( ' ', '_', $source['title'] );
        if ( $source['namespace'] > 0 ) {
            $title = $source['namespace_text'] . ':' . $title;
        }
        return $title;
    }

    private function getFileMetadata( array $titles ) : array {
        $titleToMetadata = [];
        $apiEndpoint =
            'https://commons.wikimedia.org/w/api.php?action=query&format=json&prop=imageinfo' .
            '&titles=%s&iiprop=url';

        $offset = 0;
        // max number of titles for imageinfo call is 50
        while ( $titlesSlice = array_slice( $titles, $offset, 50 ) ) {
            curl_setopt(
                $this->ch,
                CURLOPT_URL,
                sprintf( $apiEndpoint, urlencode( implode( '|', $titlesSlice ) ) ) );
            $jsonResult = curl_exec( $this->ch );
            if ( curl_errno( $this->ch ) ) {
                $this->log( curl_error( $this->ch ) . ':' . curl_errno( $this->ch ) );
                die( 'Exiting because of curl error, see log for details.' );
            }
            $result = json_decode( $jsonResult, true );

            $titleMap = [];
            if ( isset( $result['query']['normalized'] ) ) {
                foreach ( $result['query']['normalized'] as $fromTo ) {
                    $titleMap[ $fromTo['to'] ] = $fromTo['from'];
                }
            }

            foreach ( $result['query']['pages']  as $id => $page ) {
                $title = $titleMap[ $page['title'] ] ?? $page['title'];
                $titleToMetadata[ $title ] = [
                    'url' => $page['imageinfo'][0]['url'] ?? '',
                ];
            }
            $offset += 50;
        }

        return $titleToMetadata;
    }

    private function getImageUrl( string $title, array $metadata ) : string {
        if ( $metadata[$title]['url'] !== '' ) {
            return $this->getThumbnail( $metadata[$title]['url'] );
        }
        return 'https://commons.wikimedia.org/wiki/' . $title;
    }

    private function getThumbnail( string $url ) : string {
        $src = str_replace( '/commons/', '/commons/thumb/', $url ) . '/';
        if ( substr( $url, strrpos( $url, '.' ) + 1 ) == 'tif' ) {
            $src .= 'lossy-page1-800px-thumbnail.tif.jpg';
            return $src;
        }
        $src .= '800px-' . substr( $url, strrpos( $url, '/' ) + 1 );
        if ( substr( $url, strrpos( $url, '.' ) + 1 ) == 'pdf' ) {
            $src .= '.jpg';
        }
        if ( substr( $url, strrpos( $url, '.' ) + 1 ) == 'svg' ) {
            $src .= '.png';
        }
        return $src;
    }
}

$config = array_merge(
    parse_ini_file( __DIR__ . '/../config.ini', true ),
    parse_ini_file( __DIR__ . '/../replica.my.cnf', true )
);
$job = new GetImagesForClassification( $config );
$job->run();