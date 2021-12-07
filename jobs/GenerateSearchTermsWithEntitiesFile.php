<?php

namespace MediaSearchSignalTest\Jobs;

require_once 'GenericJob.php';

/**
 * Finds wikidata items corresponding to the search terms in ratedSearchResult, and outputs then
 * with the search term and language to the specified output file
 *
 * Options:
 * --tag Only use search terms from rated search results tagged with the tag
 * -w Only output `searchTermExactMatchWikidataId`, and skip finding other wikidata items
 * -t Append searchTermExactMatchWikidataId to each row
 */
class GenerateSearchTermsWithEntitiesFile extends GenericJob {

    private $entitySearchBaseUrl;
    private $out;
    private $wikidataIdsAsSearchTerms = false;

    public function __construct( array $config = [] ) {
        parent::__construct( $config );
        $this->setLogFileHandle( __DIR__ . '/../' . $this->config['log']['generateSearchTermsWithEntitiesFile'] );
        $this->entitySearchBaseUrl = $this->config['search']['entitySearchBaseUrl'];
        $this->out = fopen(
            __DIR__ . '/../' . $this->config['outputFile'],
            'w'
        );
        if ( isset( $this->config['w'] ) ) {
            $this->wikidataIdsAsSearchTerms = true;
        }
    }

    private function getSearchTerms() : array {
        $searchTerms = [];
        $query = 'select distinct searchTerm,language,searchTermExactMatchWikidataId from ratedSearchResult ';
        if ( isset( $this->config['tag'] ) ) {
            $query .= 'join ratedSearchResult_tag ' .
                'on ratedSearchResult_tag.ratedSearchResultId=ratedSearchResult.id ' .
                'join tag on ratedSearchResult_tag.tagId=tag.id ' .
                'where tag.text="' . $this->dbEscape( $this->config['tag'] ). '" ' .
                'and rating is not null ';
        } else {
            $query .= 'where rating is not null ';
        }
        $searchTermResults = $this->db->query( $query );
        while ( $row = $searchTermResults->fetch_assoc() ) {
            $searchTerms[] = [
                'term' => trim( $row['searchTerm'] ),
                'language' => $row['language'],
                'wikidataId' => $row['searchTermExactMatchWikidataId'],
            ];
        }
        return $searchTerms;
    }

    public function run() {
        $this->log( 'Begin' . "\n" );
        $count = 1;
        foreach ( $this->getSearchTerms() as $searchTerm ) {
            if ( $this->wikidataIdsAsSearchTerms ) {
                if ( !$searchTerm['wikidataId'] ) {
                    continue;
                }
                $output = [ $count, $searchTerm['wikidataId'], $searchTerm['language'] ];
            } else {
                $output = [ $count, $searchTerm['term'], $searchTerm['language'] ];
                $this->log( 'Searching ' . $searchTerm['term'] . ' in ' . $searchTerm['language'] );
                $entities = array_pad(
                    $this->getEntities( $searchTerm['term'], $searchTerm['language'] ),
                    50,
                    'NO_ENTITY'
                );
                $output = array_merge( $output, $entities );
                if ( isset( $this->config['t'] ) ) {
                    $output = array_merge( $output, [ $searchTerm['wikidataId'] ?: 'NO_MATCH' ] );
                }
            }
            fwrite(
                $this->out,
                implode( ",", $output ) . "\n"
            );
            $count++;
        }
        $this->log( 'End' . "\n" );
    }

    private function getEntities( string $searchTerm, string $language ) : array {
        $response = $this->httpGETJson(
            $this->getGetEntitiesUrl( $searchTerm, $language )
        );
        $transformedResponse = [];
        foreach ( $response['query']['search'] ?? [] as $index => $result ) {
            list( $title, $score ) = $this->transformResult( $result, $index );
            $transformedResponse[ $title ] = floatval( $score );
        }

        arsort( $transformedResponse );
        return array_keys( $transformedResponse );
    }

    private function getGetEntitiesUrl( string $searchTerm, string $language ) : string {
        $params = [
            'format' => 'json',
            'action' => 'query',
            'list' => 'search',
            'srsearch' => $searchTerm,
            'srnamespace' => 0,
            'srlimit' => 50,
            'srqiprofile' => 'wikibase',
            'srprop' => 'snippet|titlesnippet|extensiondata',
            'uselang' => $language,
        ];

        return $this->entitySearchBaseUrl . '?' . http_build_query( $params );
    }

    protected function transformResult( array $result, int $index ) : array {
        // unfortunately, the search API doesn't return an actual score
        // (for relevancy of the match), which means that we have no way
        // of telling which results are awesome matches and which are only
        // somewhat relevant
        // since we can't rely on the order to tell us much about how
        // relevant a result is (except for relative to one another), and
        // we don't know the actual score of these results, we'll try to
        // approximate a term frequency - it won't be great, but at least
        // we'll be able to tell which of "cat" and "Pirates of Catalonia"
        // most resemble "cat"
        // the highlight will either be in extensiondata (in the case
        // of a matching alias), snippet (for descriptions), or
        // titlesnippet (for labels)
        $snippets = [
            $result['snippet'],
            $result['titlesnippet'],
            $result['extensiondata']['wikibase']['extrasnippet'] ?? ''
        ];

        $maxTermFrequency = 0;
        foreach ( $snippets as $snippet ) {
            // let's figure out how much of the snippet actually matched
            // the search term based on the highlight
            $source = preg_replace( '/<span class="searchmatch">(.*?)<\/span>/', '$1', $snippet );
            $omitted = preg_replace( '/<span class="searchmatch">.*?<\/span>/', '', $snippet );
            $termFrequency = $source === '' ? 0 : 1 - mb_strlen( $omitted ) / mb_strlen( $source );
            $maxTermFrequency = max( $maxTermFrequency, $termFrequency );
        }

        // average the order in which results were returned (because that
        // takes into account additional factors such as popularity of
        // the page) and the naive term frequency to calculate how relevant
        // the results are relative to one another
        $relativeOrder = 1 / ( $index + 1 );

        return [ $result['title'], ( $relativeOrder + $maxTermFrequency ) / 2 ];
    }
}

$options = getopt( 'wt', [ 'outputFile:', 'tag::' ] );
if ( !isset( $options['outputFile'] ) ) {
    die( "ERROR: you must specify a file to output to using --outputFile\n" );
}
$job = new GenerateSearchTermsWithEntitiesFile( $options );
$job->run();
