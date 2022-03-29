// TODO add selectable language

/* Global variables */
let COLUMN_NODES, LANG_NODE, TERM_NODE, SUBMIT_BUTTON, RATINGS;

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
			if ( !response.ok ) {
				throw new Error( `Got HTTP ${ response.status }` );
			}
			return response.blob();
		})
		.then( results => {
			// NOTE Comment this to see the request in the console
			location.reload();
			console.log( 'Ratings submitted' );
		})
		.catch( error => {
			console.error( `Something went wrong! ${error}` );
		})
}

/*
 * Main
 */
// Get required nodes when the DOM is ready
document.addEventListener('DOMContentLoaded', () => {
	COLUMN_NODES = document.getElementsByClassName( 'column' );
	LANG_NODE = document.getElementsByClassName( 'lang' )[0];
	TERM_NODE = document.getElementsByClassName( 'term' )[0];
	SUBMIT_BUTTON = document.querySelector(' button ');
	SUBMIT_BUTTON.addEventListener( 'click', () => { submit( RATINGS ) } );
});

fetch( 'fetch_synonyms.php' )
	.then( response => {
		// TODO catch 404 & 500
		if ( !response.ok ) {
			throw new Error( `Got HTTP ${ response.status }` );
		}
		return response.json();
	})
	.then( results => {
		// Copy `results` to the outer-scope `RATINGS`:
		// it will be populated after images get clicked.
		RATINGS = results;

		// Add search term and language code.
		const term = document.createTextNode( results[0].term );
		TERM_NODE.appendChild( term );
		const lang = document.createTextNode( results[0].language );
		LANG_NODE.appendChild( lang );

		// Populate images.
		const imgNodes = [];
		for ( const result of results ) {
			const imgNode = createThumbNail( result );

			// On click, grey out and set `result.rating = 1`.
			imgNode.addEventListener(
				'click',
				() => { greyAndRate( imgNode, result ) }
			);

			imgNodes.push(imgNode);
		}

		// Populate grid.
		// TODO rebuild with K = 12
		const resultsAmount = results.length;
		const columnsAmount = COLUMN_NODES.length;
		const imgsPerColumn = Math.floor( resultsAmount / columnsAmount );
		const leftOvers = resultsAmount % columnsAmount;

		let sliceStart = 0;
		for ( const column of COLUMN_NODES ) {
			const sliceEnd = sliceStart + imgsPerColumn;
			const imgs = imgNodes.slice( sliceStart, sliceEnd );
			for ( const img of imgs ) {
				column.appendChild( img );
			}
			sliceStart += imgsPerColumn;
		}

	})
	.catch( error => {
		console.error( `Something went wrong! ${error}` );
	})
