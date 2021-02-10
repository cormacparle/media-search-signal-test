# media-search-signal-test

Various tools for helping to improve media search on Wikimedia Commons by gathering labeled data

## Gather and label search results

The first thing we set out to do is gather lots of image results from existing commons search, and classify them as good or bad. This allows us to do a bunch of analysis, and tune our search algorithm. 

The script to gather search results has been run on toolforge, and the interface for labeling them is public at https://media-search-signal-test.toolforge.org/

### jobs/GetImagesForClassification.php

Search commons using MediaSearch for all the search terms in input/searchTerms.csv (a mixture of the most popular search terms and a selection of random search terms), and store the results along with their score from elasticsearch.

### public_html/

A little web app where the user is presented with a random image from the stored search results and rates it as good, bad or indifferent

## Use the labeled images to compare search algorithms

We need a quick way to compare search algorithms without having to A/B test, so we made some scripts to do comparisons by running a searches for the search terms used to get the labeled images in the first place, then counting the labeled images in the results and calculating some metrics like precision, recall and f1score. 

If you want to run this locally:
* there's a dump of the labeled image data we have gathered on toolforge in `sql/results_by_component_20210201.sql`, so load that
* `php jobs/AnalyzeResults.php` ... this will run default mediasearch on commons, analyse the results, and output to the `out/` directory

More detailed information below ...

### jobs/FindLabeledImagesInResults.php

Search commons using MediaSearch for all the search terms in searchTerms/searchTerms.csv, then find all the labeled images in each resultset and store them

The point of this is to allow the user to do a search and store labeled images from the search, in order to allow the results of different searches to be compared.

Results are stored in the tables
* `search` Description of the search
* `resultset` Results for a search term for a particular search
* `labeledResult` A labeled result from a resultset, with its position and rating

Params
* `description` A description to be stored in the `search` table. Defaults to the date and time.
* `searchUrl` A custom search url. Defaults to MediaSearch (query builder + rescore) in English.

### jobs/AnalyzeResults.php

Analyze the (labeled) results from a particular search.

Calculates f1score, recall, and precision of the top 25, 50 and 100 results for each search result, and writes them with their averages to a text file.

If a search id is provided, the results for that search are analysed. If not, `FindLabeledImagesInResults.php` is run first, and the results from that are analysed.

Params
* `description` A description to be stored in the `search` table. Defaults to the date and time (only used if `searchId` is not provided).
* `searchId` The id of the stored search that we want to analyse.

### runSearches.php

A convenience script that does analysis on a bunch of searches.

## Installation

Create a mysql db, populate it using `search_component_results.sql` and `labeled_images_in_results.sql`, update config to point at the right db, and away you go.

If you want to use the web app for labeling images locally, you need to point a webserver at `public_html` 