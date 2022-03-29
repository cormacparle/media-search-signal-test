/* Global variables */
let COLUMN_NODE, LANG_NODE, TERM_NODE, SUBMIT_BUTTON, RATINGS;

/*
 * Functions
 */
function getThumbUrl( title ) {
	const cleanTitle = title.replace( / /g, '_' );
	const width = 600;
	return `https://commons.wikimedia.org/w/thumb.php?f=${cleanTitle}&w=${width}`;
}

// TODO display title on hover
function createThumbNail( data ) {
	const thumb = document.createElement( 'img' );
	thumb.src = getThumbUrl( data.result );
	thumb.alt = data.result;
	return thumb;
}

function greyAndRate( img, data ) {
	img.setAttribute( 'class', 'clicked' );
	data.rating = 1;
}

// TODO reload page after submission
function submit( data ) {
	// Build actual object to be submitted.
	const actualRatings = { 0: [], 1: [] };
	for (const obj of data) {
		const rating = obj.hasOwnProperty( 'rating' ) ? obj.rating : 0;
		actualRatings[rating].push(obj.id);
	}

	const body = new FormData();
	body.append( 'ratings', JSON.stringify( actualRatings ) );
	const params = {
		method: 'POST',
		body: body
	}

	fetch( 'submit_synonyms.php', params )
		.then( response => {
			// Throw a generic error in case of a non-2xx status.
			if ( !response.ok ) {
				throw new Error( `Got HTTP ${ response.status }` );
			}
			return response.blob();
		})
		// Handle response.
		.then( results => {
			console.log( 'Ratings submitted' );
		})
		// Handle errors.
		.catch( error => {
			console.error( `Something went wrong! ${error}` );
		})
}

/*
 * Main
 */
// Get required nodes when the DOM is ready
document.addEventListener('DOMContentLoaded', () => {
	// TODO populate 4 columns instead of 1
	COLUMN_NODE = document.getElementsByClassName( 'column' )[0];
	LANG_NODE = document.getElementsByClassName( 'lang' )[0];
	TERM_NODE = document.getElementsByClassName( 'term' )[0];
	SUBMIT_BUTTON = document.querySelector(' button ');
	SUBMIT_BUTTON.addEventListener( 'click', () => { submit( RATINGS ) } );
});

fetch( 'fetch_synonyms.php' )
	// Handle HTTP status.
	.then( response => {
		// Throw a generic error in case of a non-2xx status.
		// TODO catch 404 & 500
		if ( !response.ok ) {
			throw new Error( `Got HTTP ${ response.status }` );
		}
		return response.json();
	})
	// Handle response.
	.then( results => {
		// Copy `results` to the outer-scope `RATINGS`:
		// it will be populated after images get clicked.
		RATINGS = results;

		// Add search term and language code.
		const term = document.createTextNode( results[0].term );
		TERM_NODE.appendChild( term );
		const lang = document.createTextNode( results[0].language );
		LANG_NODE.appendChild( lang );

		for (const result of results) {
			const imgNode = createThumbNail( result );

			// On click, grey out and set `result.rating = 1`.
			imgNode.addEventListener(
				'click',
				() => { greyAndRate( imgNode, result ) }
			);

			// Populate image grid.
			COLUMN_NODE.appendChild( imgNode );
		}

	})
	// Handle errors.
	.catch( error => {
		console.error( `Something went wrong! ${error}` );
	})
