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

$maxId = $mysqli->query(
	'select max(id) as id
	from results_by_component'
);
if ( $maxId === false ) {
	throw new Exception( 'No images exist' );
}
$maxId = intval( $maxId->fetch_assoc()['id'] );

$result = $mysqli->query(
	'select id, term, file_page, image_url
	from results_by_component
	where rating is null and skipped=0 and id >= '. rand( 0, $maxId ) .'
	order by id limit 1'
);

$mysqli->close();

if ( $result === false ) {
	throw new Exception( 'No image found' );
}

header('Content-Type: application/json');
echo json_encode( $result->fetch_assoc() );
