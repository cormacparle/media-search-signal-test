<?php
$config = parse_ini_file( __DIR__ . '/../replica.my.cnf', true );
$mysqli = new mysqli( $config['db']['host'], $config['db']['user'],
    $config['db']['password'], $config['db']['dbname'] );
if ( $mysqli->connect_error ) {
    die('Connect Error (' . $mysqli->connect_errno . ') '
            . $mysqli->connect_error);
}

if ( isset( $_GET['id'] ) && $_GET['id'] ) {
	if ( isset( $_GET['skip'] ) && $_GET['skip'] ) {
		$sql = 'update results_by_component set skipped=1 where id=' . intval( $_GET['id'] );
	} else {
		$sql = 'update results_by_component set rating='. intval( $_GET['rating'] ) .
            ' where id=' . intval($_GET['id']);
	}
	$mysqli->query($sql);
}

$result = $mysqli->query( 'select id,term,file_page,image_url from results_by_component ' .
  'where rating is null and skipped=0 order by rand() limit 1' )->fetch_object();

?>
<html><head></head><body>

<h1>Search for &quot;<?=$result->term?>&quot;</h1>

<p><img src="<?=$result->image_url?>" /></p>
<ul>
	<li><a href="https://commons.wikimedia.org/wiki/<?=$result->file_page?>" target="_blank">File page on commons</a></li>
	<li><a href="https://www.google.com/search?tbm=isch&as_q=<?=urlencode( $result->term )?>"
           target="_blank">Google image search</a></li>
</ul>

<p>Is this is a good match for the search term &quot;<?=$result->term?>&quot;?</p>

<ul>
  <li><a href="/?id=<?=$result->id?>&rating=1">Yes</a></li>
  <li><a href="/?id=<?=$result->id?>&rating=0">Meh</a></li>
  <li><a href="/?id=<?=$result->id?>&rating=-1">No</a></li>
  <li><a href="/?id=<?=$result->id?>&skip=1">Dunno</a></li>
</ul>

</body>
<?php
$mysqli->close();
