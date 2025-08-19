( function ( $, mw ) {

	var $spinner;

	/**
	 * Updates the displayed result bars after the user has voted.
	 *
	 * @param {Object} data Data returned by the API module
	 * @param {String} voteId Unique poll identifier preceded by the word "multi", e.g. "multijusttesting"
	 * @param {Ńumber} val Which option the user voted for?
	 * @param {Number} count Total amount of options in this poll
	 */
	function multiUpdate( data, voteid, val, count ) {
		var results = data[ 'wigo-cast-vote' ].split( ':' );
		var sum = 0;
		var i;

		for ( i = 0; i < results.length; i++ ) {
			sum += parseInt( results[i], 10 );
		}

		var invalidate = false;
		for ( i = 0; i < count; ++i ) {
			var span = document.getElementById( voteid + '-' + i + '-result' );
			var titleSpan = document.getElementById( voteid + '-' + i );

			if ( span ) {
				// Get number of votes
				var currentVotes = parseInt( span.innerHTML, 10 );

				// Update and invalidate if number of votes changed
				var newVotes = parseInt( results[i], 10 );
				if ( newVotes !== currentVotes ) {
					invalidate = true;
					$( span ).text( newVotes );
				}

				// Calculate column width and update
				var columnDiv = document.getElementById( voteid + '-' + i + '-column' );
				if ( columnDiv ) {
					var percent;
					//removeSpinner(voteid+'-'+i);
					if ( sum === 0 ) {
						percent = 0;
					} else {
						percent = newVotes / sum * 100;
					}

					var oldWidth = columnDiv.style.width;
					var newWidth = percent + '%';
					if ( oldWidth != newWidth ) {
						invalidate = true;
						columnDiv.style.width = newWidth;
					}
				}

				// Add class and bolding if my vote
				if ( i == val ) {
					// Add class
					$( span ).addClass( 'myvote' );
					span.style.fontWeight = 'bold';
					$( titleSpan ).addClass( 'myvote' );
					titleSpan.style.fontWeight = 'bold';
				} else {
					// Remove class and bolding
					span.style.fontWeight = 'normal';
					$( span ).removeClass( 'myvote' );
					titleSpan.style.fontWeight = 'normal';
					$( titleSpan ).removeClass( 'myvote' );
				}
			}
		}

		if ( invalidate ) {
			mw.wigo.invalidate();
		}
	}

	/**
	 * Cast a vote in a poll which has multiple options.
	 *
	 * @param {String} voteId Unique poll identifier preceded by the word "multi", e.g. "multijusttesting"
	 * @param {Ńumber} val Which option the user voted for?
	 * @param {Number} count Total amount of options in this poll
	 */
	function multiVoteSend( voteid, val, count ) {
		if ( voteid.substr( 0, 5 ) !== 'multi' ) {
			// @todo FIXME: i18n
			alert( 'Invalid vote id' );
		} else {
			if ( $spinner ) {
				$spinner.remove();
			}

			$spinner = $.createSpinner();
			$( document.getElementById( voteid + '-' + val + '-column' )
				.parentNode.parentNode.previousSibling.firstChild ).append( $spinner );

			( new mw.Api() ).postWithToken( 'csrf', {
				action: 'wigo-cast-vote',
				format: 'json',
				pollid: voteid,
				vote: val,
				countoptions: count
			} ).done( function ( data, textStatus, req ) {
				multiUpdate( data, voteid, val, count );
			} ).fail( function ( errorCode, details ) {
				// errorCode is usually a single-word code from the API module
				// (though WIGO's API module does not propagate any errors...but perhaps core might?)
				// details.error.info is the human-readable error text
				if ( details && details.error && details.error.info ) {
					alert( details.error.info );
				}
			} );
		}
	}

	/**
	 * @param {String} voteId Unique poll identifier preceded by the word "multi", e.g. "multijusttesting"
	 */
	function multiVoteGetMyVote( voteId ) {
		if ( voteId.substr( 0, 5 ) !== 'multi' ) {
			voteId = 'multi' + voteId;
		}
		$.ajax(
			mw.util.wikiScript( 'api' ),
			{
				data: {
					action: 'wigo-get-my-vote',
					format: 'json',
					pollid: voteId
				}
			}
		).done( function ( data, textStatus, req ) {
			var i = data[ 'wigo-get-my-vote' ];
			var span = $( '#' + voteId + i + '-result' );
			var titleSpan = $( '#' + voteId + i );
			if ( span.length > 0 ) {
				$( span ).addClass( 'myvote' ).css( 'font-weight', 'bold' );
				$( titleSpan ).addClass( 'myvote' ).css( 'font-weight', 'bold' );
			}
		} );
	}

	$( function () {
		multiVoteGetMyVote( mw.config.get( 'wigo3MultiVoteId' ) );
	} );

	mw.multivote = {
		send: multiVoteSend,
	};

} )( jQuery, mediaWiki );
