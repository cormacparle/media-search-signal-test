# media-search-signal-test

Various tools for helping to improve media search on Wikimedia Commons by gathering labeled data

## jobs/GetImagesForClassification.php

Search commons using MediaSearch for all the search terms in searchTerms/searchTerms.csv, and store the results along with their score from elasticsearch.

The point of this is to gather a large set of images for labelling using the web app.

## public_html/

A little web app where the user is presented with a random image from the stored search results and rates it as good, bad or indifferent

## jobs/FindLabeledImagesInResults.php

Search commons using MediaSearch for all the search terms in searchTerms/searchTerms.csv, then find all the labeled images in each resultset and store them

The point of this is to allow the user to do a search and store labeled images from the search, in order to allow the results of different searches to be compared.

Results are stored in the tables
* `search` Description of the search
* `resultset` Results for a search term for a particular search
* `labeledResult` A labeled result from a resultset, with its position and rating

Params
* `description` A description to be stored in the `search` table. Defaults to the date and time.
* `searchUrl` A custom search url. Defaults to MediaSearch (query builder + rescore) in English.

## jobs/AnalyzeResults.php

Analyze the (labeled) results from a particular search.

Calculates f1score, precision of the top 30 results for each search result, and writes them with their averages to a text file.

If a search id is provided, the results for that search are analysed. If not, `FindLabeledImagesInResults.php` is run first, and the results from that are analysed.

Params
* `description` A description to be stored in the `search` table. Defaults to the date and time (only used if `searchId` is not provided).
* `searchId` The id of the stored search that we want to analyse.
