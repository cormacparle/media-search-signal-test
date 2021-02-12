# media-search-signal-test

Various tools for helping to improve media search on Wikimedia Commons by gathering labeled data

## Gather and label search results

The first thing we set out to do is gather lots of image results from existing commons search, and classify them as good or bad. This allows us to do a bunch of analysis, and tune our search algorithm. 

The script to gather search results has been run on toolforge, and the interface for labeling them is public at https://media-search-signal-test.toolforge.org/

Also there's a mysqldump of the labeled data as of Feb 2021 available in this repo in `sql/`

#### jobs/GetImagesForClassification.php

Search commons using MediaSearch for all the search terms in input/searchTerms.csv (a mixture of the most popular search terms and a selection of random search terms), and store the results along with their score from elasticsearch.

#### public_html/

A little web app where the user is presented with a random image from the stored search results and rates it as good, bad or indifferent

## Use the labeled images to compare search algorithms

We need a quick way to compare search algorithms without having to A/B test, so we made some scripts to do comparisons by running a searches for the search terms used to get the labeled images in the first place, then counting the labeled images in the results and calculating some metrics like precision, recall and f1score. 

If you want to run this locally:
* there's a dump of the labeled image data we have gathered on toolforge in `sql/results_by_component_20210201.sql`, so load that
* `php jobs/AnalyzeResults.php` ... this will run default mediasearch on commons, analyse the results, and output to the `out/` directory

More detailed information below ...

#### jobs/FindLabeledImagesInResults.php

Search commons using MediaSearch for all the search terms in input/searchTerms.csv, then find all the labeled images in each resultset and store them

The point of this is to allow the user to do a search and store labeled images from the search, in order to allow the results of different searches to be compared.

Results are stored in the tables
* `search` Description of the search
* `resultset` Results for a search term for a particular search
* `labeledResult` A labeled result from a resultset, with its position and rating

Params
* `description` A description to be stored in the `search` table. Defaults to the date and time.
* `searchUrl` A custom search url. Defaults to MediaSearch (query builder + rescore) in English.

#### jobs/AnalyzeResults.php

Analyze the (labeled) results from a particular search.

Calculates f1score, recall, and precision of the top 25, 50 and 100 results for each search result, and writes them with their averages to a text file.

If a search id is provided, the results for that search are analysed. If not, `FindLabeledImagesInResults.php` is run first, and the results from that are analysed.

Params
* `description` A description to be stored in the `search` table. Defaults to the date and time (only used if `searchId` is not provided).
* `searchId` The id of the stored search that we want to analyse.

#### runSearches.php

A convenience script that does analysis on a bunch of searches.

## Use the labeled images to create a dataset for training an elasticsearch model

elasticsearch provides a thing called "learning to rank", where it applies machine learning to labeled data in order to improve search results ranking. See https://elasticsearch-learning-to-rank.readthedocs.io/en/latest/index.html 

The basic steps for doing learning to rank are:
1. Create a "featureset" in elasticsearch. A featureset is basically a set of additional fields with their own query params that you tack on to a normal elasticsearch query, and you'll get back the scores for each field in your response.
2. Prepare elasticsearch queries for all the labeled data you have for each search term, plus the featureset stuff tacked on.
3. Run each query, and munge the responses into a format that elasticsearch can use for model-building (ranklib format).
4. Build a model using your data in elasticsearch.
5. Search using the model.

Steps 4 and 5 are outside the scope of this repo, but we have tools to do steps 1-3.

#### Step 1: the featureset

An example featureset is in `input/featureset.MediaSearch_20210127.json`. This needs to be sent to your elasticsearch instance via a POST request (see the docs referenced above)

#### Step 2: preparing the queries

##### search terms

The example featureset needs as inputs
* a search term
* a language
* a bunch of `statement_keywords` strings to search for so it can find things using structured data. 

We already have search term and language in `input/searchTerms.csv` 

We also have search term, language AND the `statement_keywords` strings in `input/searchTermsWithEntities.csv`, which was generated using `jobs/GenerateSearchTermsWithEntitiesFile.php`

##### query json

The script `jobs/GenerateFeatureQueries.php` reads the `searchTermsWithEntities.csv` file, and generates json for elasticsearch queries for all the labeled results for all the search terms.

To run the script you need to pass the param `queryJsonGenerator` which is the name of the class used to construct the query json for each set of labeled results/search terms. 

ATM there is just one class available - `MediaSearch_20210127` in the job php file itself, which corresponds to the example featureset . 

So, to create the queries, run `php jobs/GenerateFeatureQueries.php --queryJsonGenerator="MediaSearchSignalTest\Jobs\MediaSearch_20210127"` and the query json files will be output to `out/ltr/`.

(note that because mysql select doesn't distinguish 'dali' from 'dalí' you'll have to adjust the json files XXX and YYY for those queries to work (or just remove them))

### Step 3: creating the ranklib file

The script `jobs/GenerateRanklibFile.php` generates a ranklib file that can be used to train a model in elasticsearch. 

Params:
* `queryDir` ... should be `out/ltr/` if you used the default setting to create the queries in the last step
* `featuresetName` ... will be MediaSearch_20210127 for the example featureset

The script expects there to be an instance of elasticsearch at `https://127.0.0.1:9243/`. When running the script myself I set up an ssh tunnel to cloudelastic (a replica of the live search indices) using `ssh -n -L127.0.0.1:9243:cloudelastic1001.wikimedia.org:9243 mwdebug1002.eqiad.wmnet "sleep 36000"` - if you do the same the script should just work.

## Installation

1. create a mysql db
2. populate it using `search_component_results.sql` and `labeled_images_in_results.sql`
3. update `config.ini` to point at the right db
4. run `composer update`
5. away you go

A job can just be run via `php jobs/<filename>` or on toolforge it can be run using [`jsub`](https://wikitech.wikimedia.org/wiki/Help:Toolforge/Grid#Submitting_simple_one-off_jobs_using_'jsub') 

If you want to use the web app for labeling images locally, you need to point a webserver at `public_html` 