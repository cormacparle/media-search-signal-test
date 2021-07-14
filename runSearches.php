<?php

/**
 * A convenience script to run AnalyzeResults.php on
 * 1. plain search
 * 2. mediasearch with default rescore
 * 3. mediasearch with no rescore
 *
 * ... and output the results to stdout
 */

$config = parse_ini_file( __DIR__ . '/config.ini', true );
if ( file_exists( __DIR__ . '/../replica.my.cnf' ) ) {
    $config = array_merge(
        $config,
        parse_ini_file( __DIR__ . '/../replica.my.cnf', true )
    );
}

// make sure to login with an account that has apihighlimits grant;
// get a bot account, generate bot password, and enable high volume
// editing at https://commons.wikimedia.org/wiki/Special:BotPasswords/
// alternatively, run API calls on local setup (where limits can be
// manipulated) with elastic via SSH tunnel
if ($config['search']['username'] && $config['search']['password']) {
    $endPoint = $config['search']['baseUrl'] . '/w/api.php';

    // get token
    $ch = curl_init( $endPoint . '?action=query&meta=tokens&type=login&format=json' );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_COOKIEJAR, $config['search']['cookiePath'] );
    curl_setopt( $ch, CURLOPT_COOKIEFILE, $config['search']['cookiePath'] );
    $output = curl_exec( $ch );
    curl_close( $ch );
    $result = json_decode( $output, true );
    $token = $result['query']['tokens']['logintoken'];

    // login
    $ch = curl_init( $endPoint );
    curl_setopt( $ch, CURLOPT_POST, true );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query([
        'action' => 'login',
        'lgname' => $config['search']['username'],
        'lgpassword' => $config['search']['password'],
        'lgtoken' => $token,
        'format' => 'json',
    ]));
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_COOKIEJAR, $config['search']['cookiePath'] );
    curl_setopt( $ch, CURLOPT_COOKIEFILE, $config['search']['cookiePath'] );
    $output = curl_exec( $ch );
    curl_close( $ch );
}

$searches = [
    // force non-mediasearch by including another namespace (where we don't expect any matches)
    'plain search' => '/w/api.php?action=query&list=search&srsearch=%s+filetype:bitmap&srnamespace=6,2303&srqiprofile=empty&srlimit=max&uselang=%s&cirrusDumpResult',
    'mediasearch (query builder only)' => '/w/api.php?action=query&list=search&srsearch=%s+filetype:bitmap&srnamespace=6&srqiprofile=empty&srlimit=max&uselang=%s&cirrusDumpResult',
    'mediasearch (query builder + rescore)' => '/w/api.php?action=query&list=search&srsearch=%s+filetype:bitmap&srnamespace=6&srlimit=max&uselang=%s&cirrusDumpResult',
];

foreach ($searches as $description => $url) {
    echo "$description\n" . str_repeat('-', strlen($description)) . "\n";
    echo shell_exec("php jobs/AnalyzeResults.php --description='$description' --searchurl='$url'");
    echo "\n\n";
}
