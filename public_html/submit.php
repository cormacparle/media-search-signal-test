<?php

$config = array_merge(
	parse_ini_file( __DIR__ . '/../config.ini', true ),
	parse_ini_file( __DIR__ . '/../replica.my.cnf', true )
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
		'update results_by_component 
		set skipped=1
		where id=' . intval( $_POST['id'] )
	);
} else {
    echo "rated image " . intval( $_POST['id'] ) . " with " . intval( $_POST['rating'] ) . "\n";
	$mysqli->query(
		'update results_by_component 
		set rating='. intval( $_POST['rating'] ) .'
		where id=' . intval( $_POST['id'] )
	);
}

$mysqli->close();
