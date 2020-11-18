# media-search-signal-test

A tool with 2 parts:

1. a job to fetch images from commons using mediasearch, and store them with their position in the search results and elasticsearch score
2. a web page where the user is presented with a random image from the stored search results and rates it as good, bad or indifferent 

The idea is to gather data on which fields in the elasticsearch index for commons are useful for finding good images.
