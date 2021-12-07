<?php

require_once 'FindLabeledImagesInResults.class.php';

$options = getopt('w', [ 'description::', 'searchurl::', 'tag::' ]);

$job = new MediaSearchSignalTest\Jobs\FindLabeledImagesInResults(
    $options
);
$searchId = $job->run();
