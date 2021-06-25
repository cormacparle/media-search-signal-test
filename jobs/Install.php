<?php

namespace MediaSearchSignalTest\Jobs;

require 'GenericJob.php';

class Install extends GenericJob {
    public function __construct( array $config = [] ) {
        $config['populate'] = isset($config['populate']);
        parent::__construct( $config );
    }

    public function run() {
        $sql = [];
        $sql[] = file_get_contents(__DIR__.'/../sql/ratedSearchResult.sql');
        $sql[] = file_get_contents(__DIR__.'/../sql/labeled_images_in_results.sql');
        if ($this->config['populate']) {
            $sql[] = file_get_contents(__DIR__.'/../sql/ratedSearchResult.latest.sql');
        }

        $this->db->multi_query(implode("\n", $sql));
        do {
            $this->db->store_result();
        } while ($this->db->next_result());
    }
}

$options = getopt('', [ 'populate::' ]);
$job = new Install($options);
$job->run();
