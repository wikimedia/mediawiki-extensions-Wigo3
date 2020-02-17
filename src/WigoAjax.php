<?php

namespace Wigo3;

class WigoAjax {
	/**
	 * AJAX entry point which invalidates the page cache when a vote on the
	 * page is changed
	 *
	 * @param string $pagename
	 * @return string
	 */
	public static function invalidate( $pagename ) {
		// $pagename is wgPageName from javascript
		$title = \Title::newFromText( $pagename );
		if ( $title->invalidateCache() === true ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->commit();
			return "ok";
		} else {
			return "notok";
		}
	}

	/**
	 * AJAX entry point for up-down wigo voting only
	 * @param string $pollid
	 * @param string $vote
	 * @return string
	 */
	public static function vote2( $pollid, $vote ) {
		// Store the vote
		$dbw = wfGetDB( DB_MASTER );
		global $wgUser, $wgWigo3ConfigStoreIPs, $wgRequest;
		$voter = $wgWigo3ConfigStoreIPs ? $wgRequest->getIP() : $wgUser->getName();
		$dbw->startAtomic( __METHOD__ );
		$result = $dbw->replace(
			'wigovote',
			[ [ 'id','voter_name' ] ],
			[
				'id' => $pollid,
				'voter_name' => $voter,
				'vote' => $vote,
				'timestamp' => wfTimestampNow()
			],
			__METHOD__ );
		$dbw->endAtomic( __METHOD__ );

		// get updated data to update page
		$plus = 0;
		$minus = 0;
		$zero = 0;
		$myvote = -2;

		self::getVotes( $pollid, $plus, $minus, $zero, $voter, $myvote );

		$totalvotes = $plus + $minus + $zero;
		$totaltooltip = wfMessage( 'wigovotestotald' )
			->params( $totalvotes, $plus, $zero, $minus )
			->text();
		$distribtitle = wfMessage( 'wigovotedistrib' )
			->params( $plus, $zero, $minus )
			->text();
		return "$pollid:$plus:$minus:$zero:$totalvotes:$totaltooltip:$distribtitle:$myvote:$result";
	}

	private static function getVotes( $voteid, &$plus, &$minus, &$zero, $voter, &$myvote ) {
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			'wigovote',
			[ 'sum(case vote when 1 then 1 else 0 end) as plus',
			  'sum(case vote when -1 then 1 else 0 end) as minus',
			  'sum(case vote when 0 then 1 else 0 end) as zero' ],
			[ 'id' => $voteid ], __METHOD__
		);
		if ( $row = $res->fetchRow() ) {
			$plus = $row['plus'];
			$minus = $row['minus'];
			$zero = $row['zero'];
		}
		if ( $plus == null ) {
			$plus = 0;
		}
		if ( $minus == null ) {
			$minus = 0;
		}
		if ( $zero == null ) {
			$zero = 0;
		}
		$res->free();
		$myvote = -2;
	}

	//get votes for multiple polls, to cut down on AJAX requests
	public static function getmyvotes( ...$args ) {
		$dbr = wfGetDB( DB_REPLICA );
		global $wgUser, $wgWigo3ConfigStoreIPs, $wgRequest;
		$voter = $wgWigo3ConfigStoreIPs ? $wgRequest->getIP() : $wgUser->getName();
		$res = $dbr->select( 'wigovote', [ 'id','vote' ],
			[ 'id' => $args, 'voter_name' => $voter ], __METHOD__ );
		$myvotes = [];
		foreach ( $args as $a ) {
			$myvotes[$a] = false;
		}
		while ( $row = $res->fetchRow() ) {
			$myvotes[$row['id']] = $row['vote'];
		}
		$res->free();
		return json_encode( $myvotes );
	}

}
