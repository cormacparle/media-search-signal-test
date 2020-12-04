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

if ( !isset( $_GET['id'] ) && $_GET['id'] ) {
	throw new Exception( 'Missing id' );
}

if ( isset( $_GET['skip'] ) && $_GET['skip'] ) {
	$mysqli->query(
		'update results_by_component 
		set skipped=1
		where id=' . intval( $_GET['id'] )
	);
} else {
	$mysqli->query(
		'update results_by_component 
		set rating='. intval( $_GET['rating'] ) .'
		where id=' . intval( $_GET['id'] )
	);
}

$mysqli->close();
