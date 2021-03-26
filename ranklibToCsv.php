<?php

/**
 * Converts out/MediaSearch_20210127.tsv (ranklib format) into a csv with column names in the first
 * line. Also all "meh" ratings (with value 0) are dropped, and all -1 ratings are converted to
 * 0 (to make analysis in python easier)
 */

function removeColon( $field ) {
    list( , $value ) = explode( ":", $field );
    return $value;
}

$fh = fopen( 'out/MediaSearch_20210127.tsv', 'r' );
$output = fopen( 'out/MediaSearch_20210127.csv', 'w' );
fputcsv(
    $output,
    [
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

while ( $line = fgetcsv( $fh, 0, "\t" ) ) {
    if ( $line[0] !== "0" ) {
        fputcsv(
            $output,
            [
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
            ],
            ","
        );
    }
}
echo "Done\n";