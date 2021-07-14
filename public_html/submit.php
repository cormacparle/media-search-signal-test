<?php

$config = array_merge(
	parse_ini_file( __DIR__.'/../config.ini', true ),
	file_exists(__DIR__ . '/../replica.my.cnf') ? parse_ini_file( __DIR__ . '/../replica.my.cnf', true ) : []
);
$mysqli = new mysqli( $config['db']['host'], $config['client']['user'],
	$config['client']['password'], $config['db']['dbname'] );

try {
    if ( $mysqli->connect_error ) {
        throw new RuntimeException('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }

    function formatTitle( string $title ): string {
        // expect "File:" prefix and spaces instead of underscores
        if ( !preg_match( '/^File:[^_]+$/', $title ) || strlen( $title ) > 255 ) {
            throw new InvalidArgumentException( "Invalid title: $title" );
        }

        return preg_replace( '/^File:/', '', $title );
    }

    if ( !isset( $_POST['term'] ) || !$_POST['term'] ) {
        throw new InvalidArgumentException( "Missing search term" );
    }

    if ( !isset( $_POST['language'] ) || !$_POST['language'] ) {
        throw new InvalidArgumentException( "Missing language" );
    }

    $mysqli->begin_transaction();

    $tagIds = [];
    if ( isset( $_POST['tags'] ) && $_POST['tags'] ) {
        $data = preg_split( '/\r\n|\r|\n/', trim( $_POST['tags'] ) );
        foreach ( $data as $tag ) {
            $mysqli->query(
                'INSERT INTO tag
			SET text = "'. $mysqli->escape_string( $tag ) .'"'
            );
            if ( $mysqli->insert_id ) {
                $tagIds[] = $mysqli->insert_id;
            } else {
                $result = $mysqli->query(
                    'SELECT *
				FROM tag
				WHERE text = "'. $mysqli->escape_string( $tag ) .'"'
                );
                $tagIds[] = $result->fetch_object()->id;
            }
        }
    }

    $ratings = [-1, 0, 1];
    foreach ( $ratings as $rating ) {
        if ( isset( $_POST[$rating] ) && $_POST[$rating] ) {
            $data = preg_split( '/\r\n|\r|\n/', trim( $_POST[$rating] ) );
            $titles = array_map( 'formatTitle', $data );

            foreach ( $titles as $title ) {
                $mysqli->query(
                    'INSERT INTO ratedSearchResult
				SET
					searchTerm = "' . $mysqli->escape_string( $_POST['term'] ) . '",
					language = "' . $mysqli->escape_string( $_POST['language'] ) . '",
					result = "' . $mysqli->escape_string( $title ) . '",
					rating = ' . $rating . '
				ON DUPLICATE KEY UPDATE rating = ' . $rating
                );
            }

            if ( $tagIds ) {
                $mysqli->query(
                    'REPLACE INTO ratedSearchResult_tag
				SELECT id AS ratedSearchResultId, tagId
				FROM ratedSearchResult JOIN (SELECT ' . implode( ' AS tagId UNION SELECT ', $tagIds ) . ' AS tagId) AS t
				WHERE
					searchTerm = "' . $mysqli->escape_string( $_POST['term'] ) . '" AND
					language = "' . $mysqli->escape_string( $_POST['language'] ) . '" AND
					result IN ("' . implode( '","', array_map( [$mysqli, 'escape_string'], $titles ) ) . '")'
                );
            }
        }
    }

    if ( isset( $_POST['invalid'] ) && $_POST['invalid'] ) {
        $data = preg_split( '/\r\n|\r|\n/', trim( $_POST['invalid'] ) );
        $titles = array_map( 'formatTitle', $data );

        $mysqli->query(
            'DELETE FROM ratedSearchResult
		WHERE
			searchTerm = "' . $mysqli->escape_string( $_POST['term'] ) . '" AND
			language = "' . $mysqli->escape_string( $_POST['language'] ) . '" AND
			result IN ("' . implode( '","', array_map( [$mysqli, 'escape_string'], $titles ) ) . '")'
        );
    }

    $success = $mysqli->commit();
    if ( !$success) {
        throw new RuntimeException( "Error: $mysqli->error" );
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
