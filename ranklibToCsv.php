<?php

/**
 * Converts ranklib format into a csv with column names in the first line.
 *
 * All "meh" ratings (with value 0) are dropped, and all -1 ratings are converted to 0 (to make
 * analysis in python easier)
 *
 * Params:
 * - ranklibFile ... a file in ranklib format for conversion
 * - split ...
 */

function removeColon( $field ) {
    list( , $value ) = explode( ":", $field );
    return $value;
}

$options = getopt( '', [ 'ranklibFile:', 'split::' ] );
if ( !isset( $options['ranklibFile'] ) || !file_exists( $options['ranklibFile'] ) ) {
    die( "Please specify a valid ranklib file using --ranklibFile\n" );
}
if ( isset( $options['split'] ) && $options['split'] < 1 ) {
    die( "Please specify a valid value (>=1) for --split\n" );
}
$outputFilename = str_replace( '.tsv', '.csv', $options['ranklibFile'] );

$output = fopen( $outputFilename, 'w' );
fputcsv(
    $output,
    [
        'queryId',
        'rating',
        'descriptions.plain',
        'descriptions',
        'title',
        'title.plain',
        'category',
        //'category.plain', NO SUCH FIELD, so the data is meaningless, so ignore
        'redirect.title',
        'redirect.title.plain',
        'suggest',
        //'suggest.plain', NO SUCH FIELD, so the data is meaningless, so ignore
        'auxiliary_text',
        'auxiliary_text.plain',
        'text',
        'text.plain',
        'statements'
    ],
    ","
);

$ranklibAsArray = file( $options['ranklibFile'] );
if ( $options['split'] != 1 ) {
    $randomArrayKeys = array_rand(
        $ranklibAsArray,
        count($ranklibAsArray) / ( $options['split'] ?? 1)
    );
    shuffle($randomArrayKeys);
} else {
    $randomArrayKeys = array_keys( $ranklibAsArray );
}

foreach ( $randomArrayKeys as $key ) {
    $line = str_getcsv( $ranklibAsArray[$key], "\t" );
    if ( $line[0] !== "0" ) {
        fputcsv( $output, [
                removeColon( $line[1] ),
                $line[0] < 1 ? 0 : 1,
                removeColon( $line[2] ),
                removeColon( $line[3] ),
                removeColon( $line[4] ),
                removeColon( $line[5] ),
                removeColon( $line[6] ),
                //$line[7], NO SUCH FIELD
                removeColon( $line[8] ),
                removeColon( $line[9] ),
                removeColon( $line[10] ),
                //$line[11], NO SUCH FIELD
                removeColon( $line[12] ),
                removeColon( $line[13] ),
                removeColon( $line[14] ),
                removeColon( $line[15] ),
                removeColon( $line[16] ),
            ], "," );
    }
}
