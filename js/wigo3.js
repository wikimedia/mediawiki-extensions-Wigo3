
( function ( $, mw ) {

/**
 * Call an API module, either the up/downvote one or the page cache purging one.
 *
 * @param {String} name 'wigo-vote-updown' or 'wigo-invalidate-page-cache'; used to call the correct API module
 * @param {Object} args Additional parameters to be passed to the API module in addition to the default options
 * @param {Function} callback A method to call after the request has completed successfully
 */
function wigo_ajax( name, args, callback ) {
	( new mw.Api() ).postWithToken( 'csrf',
		// Merge the custom parameters passed to the function with the defaults
		Object.assign( {
			action: name,
			format: 'json'
		}, args )
	).done( function ( data, textStatus, req ) {
		callback( data );
	} ).fail( function ( errorCode, details ) {
		// errorCode is usually a single-word code from the API module
		// (though none of WIGO's API modules currently propagate any errors...but perhaps core might?)
		// details.error.info is the human-readable error text
		if ( details && details.error && details.error.info ) {
			alert( details.error.info );
		}
	} );
}

// for new up-down voting
// API return value is in the form of "$pollid:$plus:$minus:$zero:$totalvotes:$totaltooltip:$distribtitle:$myvote:$result"
function wigoupdate2( data ) {
	const arr = data[ 'wigo-vote-updown' ].split( ':' );
	const span = document.getElementById( arr[0] );
	if ( span ) {
		const origVote = parseInt( span.innerHTML, 10 );
		const plus = arr[1];
		const minus = arr[2];
		const zero = arr[3];
		const newVote = plus - minus;
		const newTotalVotes = arr[4];
		const newTotalTooltip = arr[5];
		const newDistribTitle = arr[6];
		const myVote = parseInt( arr[7] );

		const upButton = document.getElementById( arr[0] + '-up' );
		const neutralButton = document.getElementById( arr[0] + '-neutral' );
		const downButton = document.getElementById( arr[0] + '-down' );

		if ( upButton && upButton.style.display === 'none' ) {
			upButton.style.display = '';
		}

		if ( neutralButton && neutralButton.style.display === 'none' ) {
			neutralButton.style.display = '';
		}

		if ( downButton && downButton.style.display === 'none' ) {
			downButton.style.display = '';
		}

		if ( origVote !== newVote || span.title !== newTotalTooltip ) {
			$( span ).text( newVote );
			span.title = newTotalTooltip;
			// Update buttons

			// up
			if ( upButton ) {
				if ( myVote === 1 ) {
					$( upButton ).addClass( 'myvotebutton' );
				} else {
					$( upButton ).removeClass( 'myvotebutton' );
				}
			}

			// neutral
			if ( neutralButton ) {
				if ( myVote === 0 ) {
					$( neutralButton ).addClass( 'myvotebutton' );
				} else {
					$( neutralButton ).removeClass( 'myvotebutton' );
				}
			}

			// down
			if ( downButton ) {
				if ( myVote === -1 ) {
					$( downButton ).addClass( 'myvotebutton' );
				} else {
					$( downButton ).removeClass( 'myvotebutton' );
				}
			}

			// Update distribution bar
			const distribTable = document.getElementById( arr[0] + '-dist' );
			if ( distribTable ) {
				distribTable.title = newDistribTitle;

				// Calculate percentages
				let upPercent, downPercent, neutralPercent;
				if ( newTotalVotes !== 0 ) {
					upPercent = ( plus / newTotalVotes ) * 100;
					downPercent = ( minus / newTotalVotes ) * 100;
					neutralPercent = ( zero / newTotalVotes ) * 100;
				} else {
					upPercent = 0;
					downPercent = 0;
					neutralPercent = 0;
				}

				if ( upPercent !== 0 ) {
					upPercent += '%';
				}
				if ( neutralPercent !== 0 ) {
					neutralPercent += '%';
				}
				if ( downPercent !== 0 ) {
					downPercent += '%';
				}

				// Update widths
				const tr = distribTable.getElementsByTagName( 'tr' )[ 0 ];
				//tr.style.display = "";
				const tds = tr.getElementsByTagName( 'td' );
				tds[0].style.display = '';
				tds[1].style.display = '';
				tds[2].style.display = '';
				tds[0].style.width = upPercent;
				tds[1].style.width = neutralPercent;
				tds[2].style.width = downPercent;
			}

			wigoinvalidate();
		}
	}
}

function wigoinvalidated( data ) {
	if ( data[ 'wigo-invalidate-page-cache' ].substring( 0, 2 ) !== 'ok' ) {
		alert( mw.msg( 'wigo-error-invalidating-cache' ) );
	}
}

function wigoinvalidate() {
	wigo_ajax( 'wigo-invalidate-page-cache', { pagename: mw.config.get( 'wgPageName' ) }, wigoinvalidated );
}

function wigovoteup( voteId ) {
	button = document.getElementById( voteId + '-up' );
	button.style.display = 'none';
	wigo_ajax( 'wigo-vote-updown', { pollid: voteId, vote: 1 }, wigoupdate2 );
}

function wigovotedown( voteId ) {
	button = document.getElementById( voteId + '-down' );
	button.style.display = 'none';
	wigo_ajax( 'wigo-vote-updown', { pollid: voteId, vote: -1 }, wigoupdate2 );
}

function wigovotereset( voteId ) {
	button = document.getElementById( voteId + '-neutral' );
	button.style.display = 'none';
	wigo_ajax( 'wigo-vote-updown', { pollid: voteId, vote: 0 }, wigoupdate2 );
}

mw.wigo = {
	voteup: wigovoteup,
	votereset: wigovotereset,
	votedown: wigovotedown,
	invalidate: wigoinvalidate,
};

} )( jQuery, mediaWiki );
