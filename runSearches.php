<?php

$config = parse_ini_file( __DIR__ . '/config.ini', true );

// make sure to login with an account that has apihighlimits grant;
// get a bot account, generate bot password, and enable high volume
// editing at https://commons.wikimedia.org/wiki/Special:BotPasswords/
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
    'plain search' => '/w/api.php?action=query&list=search&srsearch=%s+filetype:bitmap&srnamespace=6&srqiprofile=empty&srlimit=max&uselang=en&cirrusDumpResult',
    'mediasearch (query builder only)' => '/w/api.php?action=query&list=search&srsearch=%s+filetype:bitmap&srnamespace=6&srqiprofile=empty&srlimit=max&uselang=en&mediasearch&cirrusDumpResult',
    'mediasearch (query builder + rescore)' => '/w/api.php?action=query&list=search&srsearch=%s+filetype:bitmap&srnamespace=6&srlimit=max&uselang=en&mediasearch&cirrusDumpResult',
    'statement/descriptions/suggest' => '/w/api.php?action=query&list=search&srsearch=%s+filetype:bitmap&srnamespace=6&srqiprofile=empty&srlimit=max&uselang=en&mediasearch&cirrusDumpResult&boost:statement=1&boost:descriptions=1&boost:title=0&boost:category=0&boost:heading=0&boost:auxiliary_text=0&boost:file_text=0&boost:redirect.title=0&boost:suggest=1&boost:text=0',
    'statement' => '/w/api.php?action=query&list=search&srsearch=%s+filetype:bitmap&srnamespace=6&srqiprofile=empty&srlimit=max&uselang=en&mediasearch&cirrusDumpResult&boost:statement=1&boost:descriptions=0&boost:title=0&boost:category=0&boost:heading=0&boost:auxiliary_text=0&boost:file_text=0&boost:redirect.title=0&boost:suggest=0&boost:text=0',
    'descriptions' => '/w/api.php?action=query&list=search&srsearch=%s+filetype:bitmap&srnamespace=6&srqiprofile=empty&srlimit=max&uselang=en&mediasearch&cirrusDumpResult&boost:statement=0&boost:descriptions=1&boost:title=0&boost:category=0&boost:heading=0&boost:auxiliary_text=0&boost:file_text=0&boost:redirect.title=0&boost:suggest=0&boost:text=0',
    'title' => '/w/api.php?action=query&list=search&srsearch=%s+filetype:bitmap&srnamespace=6&srqiprofile=empty&srlimit=max&uselang=en&mediasearch&cirrusDumpResult&boost:statement=0&boost:descriptions=0&boost:title=1&boost:category=0&boost:heading=0&boost:auxiliary_text=0&boost:file_text=0&boost:redirect.title=0&boost:suggest=0&boost:text=0',
    'category' => '/w/api.php?action=query&list=search&srsearch=%s+filetype:bitmap&srnamespace=6&srqiprofile=empty&srlimit=max&uselang=en&mediasearch&cirrusDumpResult&boost:statement=0&boost:descriptions=0&boost:title=0&boost:category=1&boost:heading=0&boost:auxiliary_text=0&boost:file_text=0&boost:redirect.title=0&boost:suggest=0&boost:text=0',
    'heading' => '/w/api.php?action=query&list=search&srsearch=%s+filetype:bitmap&srnamespace=6&srqiprofile=empty&srlimit=max&uselang=en&mediasearch&cirrusDumpResult&boost:statement=0&boost:descriptions=0&boost:title=0&boost:category=0&boost:heading=1&boost:auxiliary_text=0&boost:file_text=0&boost:redirect.title=0&boost:suggest=0&boost:text=0',
    'auxiliary_text' => '/w/api.php?action=query&list=search&srsearch=%s+filetype:bitmap&srnamespace=6&srqiprofile=empty&srlimit=max&uselang=en&mediasearch&cirrusDumpResult&boost:statement=0&boost:descriptions=0&boost:title=0&boost:category=0&boost:heading=0&boost:auxiliary_text=1&boost:file_text=0&boost:redirect.title=0&boost:suggest=0&boost:text=0',
    'file_text' => '/w/api.php?action=query&list=search&srsearch=%s+filetype:bitmap&srnamespace=6&srqiprofile=empty&srlimit=max&uselang=en&mediasearch&cirrusDumpResult&boost:statement=0&boost:descriptions=0&boost:title=0&boost:category=0&boost:heading=0&boost:auxiliary_text=0&boost:file_text=1&boost:redirect.title=0&boost:suggest=0&boost:text=0',
    'redirect.title' => '/w/api.php?action=query&list=search&srsearch=%s+filetype:bitmap&srnamespace=6&srqiprofile=empty&srlimit=max&uselang=en&mediasearch&cirrusDumpResult&boost:statement=0&boost:descriptions=0&boost:title=0&boost:category=0&boost:heading=0&boost:auxiliary_text=0&boost:file_text=0&boost:redirect.title=1&boost:suggest=0&boost:text=0',
    'suggest' => '/w/api.php?action=query&list=search&srsearch=%s+filetype:bitmap&srnamespace=6&srqiprofile=empty&srlimit=max&uselang=en&mediasearch&cirrusDumpResult&boost:statement=0&boost:descriptions=0&boost:title=0&boost:category=0&boost:heading=0&boost:auxiliary_text=0&boost:file_text=0&boost:redirect.title=0&boost:suggest=1&boost:text=0',
    'text' => '/w/api.php?action=query&list=search&srsearch=%s+filetype:bitmap&srnamespace=6&srqiprofile=empty&srlimit=max&uselang=en&mediasearch&cirrusDumpResult&boost:statement=0&boost:descriptions=0&boost:title=0&boost:category=0&boost:heading=0&boost:auxiliary_text=0&boost:file_text=0&boost:redirect.title=0&boost:suggest=0&boost:text=1',
];

foreach ($searches as $description => $url) {
    echo "$description\n" . str_repeat('-', strlen($description)) . "\n";
    echo shell_exec("php jobs/AnalyzeResults.php --description='$description'");
    echo "\n\n";
}
