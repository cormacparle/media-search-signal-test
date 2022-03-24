// TODO make images clickable
// TODO add submit button
// TODO send rating = 1 when images are clicked, else 0
function appendItem( item, container ) {
	const thumbnail = document.createElement( 'img' );
	thumbnail.src = getThumbUrl( item.result );
	container.appendChild( thumbnail );
}

function getThumbUrl( title ) {
	const cleanTitle = title.replace( / /g, '_' );
	const width = 600;

	return `https://commons.wikimedia.org/w/thumb.php?f=${cleanTitle}&w=${width}`;
}

// Get required nodes when the DOM is ready
var columnNode, langNode, termNode;
document.addEventListener("DOMContentLoaded", function(event) {
	// TODO populate 4 columns instead of 1
	columnNode = document.getElementsByClassName( 'column' )[0];
	langNode = document.getElementsByClassName( 'lang' )[0];
	termNode = document.getElementsByClassName( 'term' )[0];
});

fetch( 'fetch_synonyms.php' )
	// Handle HTTP status.
	.then( response => {
		// Throw a generic error in case of a non-2xx status.
		if ( !response.ok ) {
			throw new Error( `Got HTTP ${ response.status }` );
		}
  	return response.json();
	})
	// Handle response.
	.then( results => {
		// Add search term and language code.
		const term = document.createTextNode( results[0].term );
		termNode.appendChild( term );
		const lang = document.createTextNode( results[0].language );
		langNode.appendChild( lang );

		// Populate image grid.
		results.forEach( result => appendItem( result, columnNode ) );

	})
	// Handle errors.
	.catch( error => {
		console.error( `Something went wrong! ${error}` );
	})
