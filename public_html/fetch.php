<?php

$config = array_merge(
	parse_ini_file( __DIR__.'/../config.ini', true ),
	file_exists(__DIR__ . '/../replica.my.cnf') ? parse_ini_file( __DIR__ . '/../replica.my.cnf', true ) : []
);
$mysqli = new mysqli( $config['db']['host'], $config['client']['user'],
	$config['client']['password'], $config['db']['dbname'] );
if ( $mysqli->connect_error ) {
	die('Connect Error (' . $mysqli->connect_errno . ') '
	    . $mysqli->connect_error);
}

$maxId = $mysqli->query(
	'select max(id) as id
	from ratedSearchResult
	WHERE rating IS NULL'
);


try {
    if ( $maxId === false ) {
        throw new \Exception( 'No images exist' );
    }
    $maxId = intval( $maxId->fetch_assoc()['id'] );

    $result = $mysqli->query( 'select id, searchTerm, result, language
        from ratedSearchResult
        where rating is null and id >= ' . rand( 0, $maxId ) . '
        order by id limit 1' );

    $mysqli->close();

    if ( $result === false ) {
        throw new \Exception( 'No images found' );
    }
    $data = $result->fetch_assoc();
    if ( !$data ) {
        throw new \Exception( 'No images found' );
    }

    header( 'Content-Type: application/json' );
    echo json_encode( $data );
} catch ( \Exception $e ) {
    header( $_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500 );
    header( 'Content-Type: application/json' );
    echo json_encode( [ "error" => $e->getMessage() ] );
}
