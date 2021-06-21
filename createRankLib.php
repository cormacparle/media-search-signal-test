<?php

/**
 * A convenience script to create a ranklib file from labeled data using defaults
 *
 * Defaults:
 *  expects instance of elasticsearch at `https://127.0.0.1:9243/`
 *  uses the elasticsearch featureset labeled $featureSetName
 *  the ranklib file will be output to out/$featureSetName.tsv
 */

$featureSetName = 'MediaSearch_20210127';
$searchTermsWithEntitiesFile = "out/searchTermsWithEntities.csv";

shell_exec(
    'php jobs/GenerateSearchTermsWithEntitiesFile.php ' .
    ' --outputFile="' . $searchTermsWithEntitiesFile . '"'
);
shell_exec(
    'php jobs/GenerateFeatureQueries.php ' .
    ' --searchTermsWithEntitiesFile=' . $searchTermsWithEntitiesFile .
    ' --queryJsonGenerator="MediaSearchSignalTest\Jobs\\' . $featureSetName . '"'
);
shell_exec(
    'php jobs/GenerateRanklibFile.php ' .
    ' --queryDir="out/ltr/" '.
    ' --featuresetName=' . $featureSetName
);
