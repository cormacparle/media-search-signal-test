// TODO add selectable language

/* Global variables */
let COLUMN_NODES, LANG_NODE, TERM_NODE, SUBMIT_BUTTON, SKIP_BUTTON, RATINGS;

/*
 * Functions
 */
function getThumbUrl( title ) {
	const cleanTitle = title.replace( / /g, '_' );
	const width = 600;
	return `https://commons.wikimedia.org/w/thumb.php?f=${cleanTitle}&w=${width}`;
}

function createThumbNail( data ) {
	const thumb = document.createElement( 'img' );
	thumb.src = getThumbUrl( data.result );
	thumb.title = data.result;
	return thumb;
}

function toggle( img, data ) {
	img.toggleAttribute( 'clicked' );
	// First click: add rating
	if ( !data.hasOwnProperty( 'rating' ) ) {
		data.rating = 1;
	} else {  // Already clicked: toggle rating
		data.rating = data.rating == 1 ? 0 : 1;
	}
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
			//location.reload();
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
	SUBMIT_BUTTON = document.querySelector( 'button.submit' );
	SUBMIT_BUTTON.addEventListener( 'click', () => { submit( RATINGS ) } );
	SKIP_BUTTON = document.querySelector( 'button.skip' );
	SKIP_BUTTON.addEventListener( 'click', () => { location.reload(); } );
});

fetch( 'fetch_synonyms.php' )
	.then( response => {
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

			// Handle images that fail to load.
			imgNode.addEventListener( 'error', () => {
				console.warn(
					`Skipping image that failed to load: ${result.result}`
				);
				imgNode.remove();
			});

			// On click, toggle highlight & rating.
			imgNode.addEventListener(
				'click',
				() => { toggle( imgNode, result ) }
			);

			imgNodes.push(imgNode);
		}

		// Populate grid.
		const resultsAmount = results.length;
		const columnsAmount = COLUMN_NODES.length;
		const imgsPerColumn = Math.floor( resultsAmount / columnsAmount );

		let sliceStart = 0;
		for ( const column of COLUMN_NODES ) {
			const sliceEnd = sliceStart + imgsPerColumn;
			const slice = imgNodes.slice( sliceStart, sliceEnd );
			for ( const img of slice ) {
				column.appendChild( img );
			}
			sliceStart += imgsPerColumn;
		}

		// TODO rebuild with K = 12 to minimize leftovers
		// Populate leftovers.
		const leftOversAmount = resultsAmount % columnsAmount;
		if ( leftOversAmount != 0 ) {
			console.log(leftOversAmount);
			const leftOvers = imgNodes.slice( -leftOversAmount );
			for ( i = 0; i < leftOversAmount; i++ ) {
				console.log('column:', COLUMN_NODES[i], 'img:', leftOvers[i]);
				COLUMN_NODES[i].appendChild( leftOvers[i] );
			}
		}

	})
	.catch( error => {
		console.error( `Something went wrong! ${error}` );
	});
