<?php

$config = array_merge(
	parse_ini_file( __DIR__.'/../../../config.ini', true ),
	file_exists(__DIR__ . '/../../../replica.my.cnf') ? parse_ini_file( __DIR__ . '/../../../replica.my.cnf', true ) : []
);
$mysqli = new mysqli( $config['db']['host'], $config['client']['user'],
	$config['client']['password'], $config['db']['dbname'] );
if ( $mysqli->connect_error ) {
	die('Connect Error (' . $mysqli->connect_errno . ') '
	    . $mysqli->connect_error);
}

$maxSearchId = $mysqli->query(
	'SELECT max(search_id) as search_id
	FROM synonyms
	WHERE rating IS NULL'
);


try {
    if ( $maxSearchId === false ) {
        throw new \Exception( 'No images exist' );
    }
	$maxSearchId = intval( $maxSearchId->fetch_assoc()['search_id'] );

	$result = $mysqli->query(
		'SELECT id, search_id, term, result, language
		FROM synonyms
		WHERE rating IS NULL AND search_id = ' . rand( 0, $maxSearchId ) . '
		ORDER BY id'
	);

    $mysqli->close();

    if ( $result === false ) {
        throw new \Exception( 'No images found' );
    }
    $data = $result->fetch_all(MYSQLI_ASSOC);
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
