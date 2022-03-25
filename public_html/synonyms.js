// DONE set rating = 1 when images are clicked
// TODO add submit button
// TODO send rating = 1 when images are clicked, else 0
function getThumbUrl( title ) {
	const cleanTitle = title.replace( / /g, '_' );
	const width = 600;

	return `https://commons.wikimedia.org/w/thumb.php?f=${cleanTitle}&w=${width}`;
}

function createThumbNail( data ) {
	const thumb = document.createElement( 'img' );
	thumb.src = getThumbUrl( data.result );
	return thumb;
}

function greyAndRate( img, data ) {
	img.setAttribute( 'class', 'clicked' );
	data.rating = 1;
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

		for (const result of results) {
			const imgNode = createThumbNail( result );

			// On click, grey out and set `result.rating = 1`.
			imgNode.addEventListener(
				'click',
				() => { greyAndRate( imgNode, result ) }
			);

			// Populate image grid.
			columnNode.appendChild( imgNode );
		}

	})
	// Handle errors.
	.catch( error => {
		console.error( `Something went wrong! ${error}` );
	})
