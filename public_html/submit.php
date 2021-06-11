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

if ( !isset( $_POST['id'] ) && $_POST['id'] ) {
	throw new Exception( 'Missing id' );
}

if ( isset( $_POST['skip'] ) && $_POST['skip'] ) {
    echo "skipped image " . intval( $_POST['id'] ) . "\n";
	$mysqli->query(
		'DELETE FROM ratedSearchResult
		WHERE id=' . intval( $_POST['id'] )
	);
} else {
    echo "rated image " . intval( $_POST['id'] ) . " with " . intval( $_POST['rating'] ) . "\n";
	$mysqli->query(
		'UPDATE ratedSearchResult
		SET rating='. intval( $_POST['rating'] ) .'
		WHERE id=' . intval( $_POST['id'] )
	);
}

$mysqli->close();
