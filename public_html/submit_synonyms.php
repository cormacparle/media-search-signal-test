<?php

$config = array_merge(
	parse_ini_file( __DIR__.'/../../../config.ini', true ),
	file_exists(__DIR__ . '/../../../replica.my.cnf') ? parse_ini_file( __DIR__ . '/../../../replica.my.cnf', true ) : []
);
$mysqli = new mysqli( $config['db']['host'], $config['client']['user'],
	$config['client']['password'], $config['db']['dbname'] );

try {
    if ( $mysqli->connect_error ) {
        throw new RuntimeException('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    if ( !isset( $_POST['ratings'] ) || !$_POST['ratings'] ) {
        throw new InvalidArgumentException( 'Missing ratings' );
	}

	$ratings = json_decode( $_POST['ratings'], true );
	if ( $ratings === null ) {
		throw new InvalidArgumentException( 'Ratings are not valid JSON' );
	}

    $mysqli->begin_transaction();

	foreach ( $ratings as $rating => $ids ) {
		if ( count( $ids ) == 0 ) {
			continue;
		}

		$query = 'UPDATE synonyms SET rating = ' . $rating .
			' WHERE id IN (' . implode( ',', $ids ) . ')';
		var_dump( $query );
		$mysqli->query( $query );
    }

    $success = $mysqli->commit();
    if ( !$success) {
        throw new RuntimeException( 'Error: ' . $mysqli->error );
    }

    $mysqli->close();

    echo "Done\n";
} catch ( RuntimeException $e ) {
    header( $_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500 );
    echo $e->getMessage();
} catch ( InvalidArgumentException $e ) {
    header( $_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request', true, 400 );
    echo $e->getMessage();
}
