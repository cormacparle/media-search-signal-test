# media-search-signal-test

Various tools for helping to improve media search on Wikimedia Commons by using labeled data

## 1. Use the labeled images to compare search algorithms

We need a quick way to compare search algorithms without having to A/B test, so we made some scripts to do comparisons by running a searches for the search terms used to get the labeled images in the first place, then counting the labeled images in the results and calculating some metrics like precision, recall and f1score. 

If you want to run this locally:
* load the existing labeled data into your database using `php jobs/Install.php --populate` (note this will delete any labeled data already in your database)
* `php jobs/AnalyzeResults.php` ... this will run default mediasearch on commons for all search terms that we have labeled data for (approx 2k), analyse the results and output them to stdout

If you want to run this using vagrant on your local machine:
* load the existing labeled data into your database using `php jobs/Install.php --populate` (note this will delete any labeled data already in your database)
* update `config.ini` setting `[search]baseUrl` to point at your local installation
* if you want to use the replica-of-production search index in cloudelastic instead of your local search index, see [instructions here](#cloudelastic) 

More detailed information below ...

#### jobs/FindLabeledImagesInResults.php

Search commons using MediaSearch for all the search terms in `ratedSearchResult`, then find all the labeled images in each resultset and store them

The point of this is to allow the user to do a search and store labeled images from the search, in order to allow the results of different searches to be compared.

Results are stored in the tables
* `search` Description of the search
* `resultset` Results for a search term for a particular search
* `labeledResult` A labeled result from a resultset, with its position and rating

Params
* `description` A description to be stored in the `search` table. Defaults to the date and time.
* `searchUrl` A custom search url. Defaults to standard MediaSearch (query builder + rescore)

#### jobs/AnalyzeResults.php

Analyze the (labeled) results from a particular search.

Calculates f1score, recall, average precision and precision@K over all results, and writes them to stdout.

If a search id is provided, the results for that search are analysed. If not, `FindLabeledImagesInResults.php` is run first, and the results from that are analysed.

Params
* `description` A description to be stored in the `search` table. Defaults to the date and time (only used if `searchId` is not provided).
* `searchId` The id of the stored search that we want to analyse.

#### runSearches.php

A convenience script that runs `jobs/AnalyzeResults.php` analysis on a pre-defined bunch of searches, and outputs the results.

## 2. Use the labeled images to create a dataset for training a model

Elasticsearch provides a thing called "learning to rank", where it applies machine learning to labeled data in order to improve search results ranking. See https://elasticsearch-learning-to-rank.readthedocs.io/en/latest/index.html

Elasticsearch uses a textfile in "ranklib" format as an input for model-building (which can also be used to build other models, see the section on logistic regression below).

### How to create a dataset in ranklib format: quick version

To generate a ranklib file using the labeled data in this repo, with elasticsearch queries corresponding to those used on production commons on Jan 27 2021:

1. Point `https://127.0.0.1:9243/` at a replica of the production wikimedia-commons elasticsearch index via an ssh tunnel using `ssh -n -L127.0.0.1:9243:cloudelastic1001.wikimedia.org:9243 mwdebug1002.eqiad.wmnet "sleep 36000"` 
2. Run `php createRankLib.php`

The ranklib file will be output to `out/MediaSearch_20211206.tsv`

### How to create a dataset in ranklib format: long version

To create a training dataset for learning-to-rank in elasticsearch we need to:
1. Create a "featureset" in elasticsearch. A featureset is basically a set of additional fields with their own query params that you tack on to a normal elasticsearch query, and you'll get back the scores for each field in your response.
2. Prepare elasticsearch queries for all the labeled data you have for each search term, plus the featureset stuff tacked on.
3. Run each query, and munge the responses into a format that elasticsearch can use for model-building (ranklib format).

#### Step 1: the featureset

Create an elasticsearch featureset and send it to your elasticsearch instance via a POST request (see the docs referenced above)

There are example featuresets in `input/`

#### Step 2: prepare the queries

1. run `php jobs/GenerateSearchTermsWithEntitiesFile.php --outputFile="out/searchTermsWithEntities.csv` to generate a file containing all the labeled search terms and any corresponding wikidata items they might have
2. run `php jobs/GenerateFeatureQueries.php --queryJsonGenerator="MediaSearchSignalTest\Jobs\MediaSearch_20211206" --searchTermsWithEntitiesFile="out/searchTermsWithEntities.csv"` 

The query json files will be output to `out/ltr/`

#### Step 3: run the queries, write the results in ranklib format

Run `php jobs/GenerateRanklibFile.php --queryDir="out/ltr/"  --featuresetName=MediaSearch_20211206 --searchTermsWithEntitiesFile="out/searchTermsWithEntities.csv"`

The script expects there to be an instance of elasticsearch at `https://127.0.0.1:9243/`. When running the script myself I set up an ssh tunnel to cloudelastic (a replica of the live search indices that already has the featureset set up) using `ssh -n -L127.0.0.1:9243:cloudelastic1001.wikimedia.org:9243 mwdebug1002.eqiad.wmnet "sleep 36000"` - if you do the same the script should just work.

## 3. Train a logistic regression model using ranklib

The file `logreg.py` trains a logistic regression model using a ranklib file, and outputs the model's coefficients and intercept. The coefficients and intercept can be then used in `WikibaseMediaInfo::MediaSearchProfiles.php`.

ATM the file assumes the ranklib file lives in `out/MediaSearch_20211206.tsv` - you can edit the code if you need to use a different one.

You can run the file using `python3 logreg.py --trainingDataSize=N` where N is the number of rows in the ranklib file you want to use for training the model. 

N is set to 0.8 by default, so running with no options means the ranklib file gets shuffled randomly, then the first (total rows * 0.8) rows are used for training the model, and the rest are used for testing it.

Running with `-x` skips the shuffling step, and uses the last shuffled version.


## 4. Gather and label search results

#### jobs/GetImagesForClassification.php

Use this to gather search results from wikimedia commons so they can be labeled via the web app. It searches commons for each term in the input file, and inserts the results into `ratedSearchResult` with `rating` set to null

Params
* `searchTermsFile` A comma-separated file. First column is ignored, second should contain the search term, third should contain the language of the search term 

#### public_html/

A little web app where the user is presented with a random image from the stored search results, and rates it as good, bad or indifferent

#### public_html/bulk.html

A form where you can enter a search term plus language with a list of good/bad/indifferent results. It gets inserted directly into the labeled data.

## Installation

1. create a mysql db
2. update `config.ini` to point at the right db
3. run `composer update`
4. run `php jobs/Install.php` to install the DB schema (add the `--populate` param to populate the tables with existing labeled data)
5. away you go

A job can just be run via `php jobs/<filename>` or on toolforge it can be run using [`jsub`](https://wikitech.wikimedia.org/wiki/Help:Toolforge/Grid#Submitting_simple_one-off_jobs_using_'jsub') 

If you want to use the web app for labeling images locally, you need to point a webserver at `public_html`

#### <a name="cloudelastic"></a> cloudelastic

There's a replica of the production search index in cloudelastic that you can use instead of your local search index when running locally.

If you're running vagrant there are 2 steps to do this:

1. Set up an ssh tunnel from your machine to cloudelastic by typing `ssh -n -L127.0.0.1:9243:cloudelastic1001.wikimedia.org:9243 mwdebug1002.eqiad.wmnet "sleep 36000"` in a console window (you'll need production access for this to work)
2. add the following to `LocalSettings.php`
```
    $wgCirrusSearchClusters = [
		'default' => [
			[
				'host' => 'cloudelastic1001.wikimedia.org',
				'port' => 9243,
				'transport' => 'Https'
			]
		]
	];

	// Activate devel options useful for relforge
	$wgCirrusSearchDevelOptions = [
		'morelike_collect_titles_from_elastic' => true,
		'ignore_missing_rev' => true,
	];

	$wgCirrusSearchIndexBaseName = 'commonswiki';

	$wgCirrusSearchNamespaceMappings[ NS_FILE ] = 'file';
	// Undo global config that includes commons files in other wikis search results
	unset( $wgCirrusSearchExtraIndexes[ NS_FILE ] );
	$wgMediaInfoMediaSearchProperties = [
		'P180' => 1, //depicts
		'P6243' => 1, //.1, // digital representation of
	];
	// stemming settings for live indices
	$wgWBCSUseStemming = [
		'ar' => [ 'index' => true, 'query' => true ],
		'bg' => [ 'index' => true, 'query' => true ],
		'ca' => [ 'index' => true, 'query' => true ],
		'ckb' => [ 'index' => true, 'query' => true ],
		'cs' => [ 'index' => true, 'query' => true ],
		'da' => [ 'index' => true, 'query' => true ],
		'de' => [ 'index' => true, 'query' => true ],
		'el' => [ 'index' => true, 'query' => true ],
		'en' => [ 'index' => true, 'query' => true ],
		'en-ca' => [ 'index' => true, 'query' => true ],
		'en-gb' => [ 'index' => true, 'query' => true ],
		'es' => [ 'index' => true, 'query' => true ],
		'eu' => [ 'index' => true, 'query' => true ],
		'fa' => [ 'index' => true, 'query' => true ],
		'fi' => [ 'index' => true, 'query' => true ],
		'fr' => [ 'index' => true, 'query' => true ],
		'ga' => [ 'index' => true, 'query' => true ],
		'gl' => [ 'index' => true, 'query' => true ],
		'he' => [ 'index' => true, 'query' => true ],
		'hi' => [ 'index' => true, 'query' => true ],
		'hu' => [ 'index' => true, 'query' => true ],
		'hy' => [ 'index' => true, 'query' => true ],
		'id' => [ 'index' => true, 'query' => true ],
		'it' => [ 'index' => true, 'query' => true ],
		'ja' => [ 'index' => true, 'query' => true ],
		'ko' => [ 'index' => true, 'query' => true ],
		'lt' => [ 'index' => true, 'query' => true ],
		'lv' => [ 'index' => true, 'query' => true ],
		'nb' => [ 'index' => true, 'query' => true ],
		'nl' => [ 'index' => true, 'query' => true ],
		'nn' => [ 'index' => true, 'query' => true ],
		'pl' => [ 'index' => true, 'query' => true ],
		'pt' => [ 'index' => true, 'query' => true ],
		'pt-br' => [ 'index' => true, 'query' => true ],
		'ro' => [ 'index' => true, 'query' => true ],
		'ru' => [ 'index' => true, 'query' => true ],
		'simple' => [ 'index' => true, 'query' => true ],
		'sv' => [ 'index' => true, 'query' => true ],
		'th' => [ 'index' => true, 'query' => true ],
		'tr' => [ 'index' => true, 'query' => true ],
		'uk' => [ 'index' => true, 'query' => true ],
		'zh' => [ 'index' => true, 'query' => true ],
	];
	$wgMediaInfoExternalEntitySearchBaseUri = 'https://www.wikidata.org/w/api.php';
	$wgMediaSearchExternalEntitySearchBaseUri = 'https://www.wikidata.org/w/api.php';
```

