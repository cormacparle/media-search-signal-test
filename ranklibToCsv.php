<?php

/**
 * Converts ranklib format into a csv with column names in the first line.
 *
 * All "meh" ratings (with value 0) are dropped, and all -1 ratings are converted to 0 (to make
 * analysis in python easier)
 *
 * The order of the rows is shuffled
 *
 * Params:
 * - ranklibFile ... a file in ranklib format for conversion
 * - split ... only write (total_rows)*(1/split) rows selected at random to the csv
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
        'descriptions',
        'title',
        'category',
        'redirect.title',
        'suggest',
        'auxiliary_text',
        'text',
        'statements',
        'p18',
        'p373',
        'sitelinks'
    ],
    ","
);

$ranklibAsArray = file( $options['ranklibFile'] );
$randomArrayKeys = array_rand(
    $ranklibAsArray,
    count($ranklibAsArray) / ( $options['split'] ?? 1)
);
shuffle($randomArrayKeys);
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
                removeColon( $line[7] ),
                removeColon( $line[8] ),
                removeColon( $line[9] ),
                1000 * removeColon( $line[10] ),
                removeColon( $line[11] ),
                removeColon( $line[12] ),
            ], "," );
    }
}
