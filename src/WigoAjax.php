<?php

namespace Wigo3;

use MediaWiki\MediaWikiServices;

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
			$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
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
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		global $wgWigo3ConfigStoreIPs;
		$context = \RequestContext::getMain();
		$voter = $wgWigo3ConfigStoreIPs ? $context->getRequest()->getIP() : $context->getUser()->getName();
		$dbw->startAtomic( __METHOD__ );
		$dbw->replace(
			'wigovote',
			[ [ 'id', 'voter_name' ] ],
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
		return "$pollid:$plus:$minus:$zero:$totalvotes:$totaltooltip:$distribtitle:$myvote:";
	}

	/**
	 * @param string $voteid
	 * @param int &$plus
	 * @param int &$minus
	 * @param int &$zero
	 * @param string $voter
	 * @param int &$myvote
	 */
	private static function getVotes( $voteid, &$plus, &$minus, &$zero, $voter, &$myvote ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$res = $dbr->select(
			'wigovote',
			[ 'sum(case vote when 1 then 1 else 0 end) as plus',
			  'sum(case vote when -1 then 1 else 0 end) as minus',
			  'sum(case vote when 0 then 1 else 0 end) as zero' ],
			[ 'id' => $voteid ], __METHOD__
		);
		$row = $res->fetchRow();
		$plus = $row['plus'] ?? 0;
		$minus = $row['minus'] ?? 0;
		$zero = $row['zero'] ?? 0;
		$res->free();
		$myvote = -2;
	}

	/**
	 * Get votes for multiple polls, to cut down on AJAX requests
	 *
	 * @param string[] ...$args
	 * @return string
	 */
	public static function getmyvotes( ...$args ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		global $wgWigo3ConfigStoreIPs;
		$context = \RequestContext::getMain();
		$voter = $wgWigo3ConfigStoreIPs ? $context->getRequest()->getIP() : $context->getUser()->getName();
		$res = $dbr->select( 'wigovote', [ 'id', 'vote' ],
			[ 'id' => $args, 'voter_name' => $voter ], __METHOD__ );
		$myvotes = [];
		foreach ( $args as $a ) {
			$myvotes[$a] = false;
		}
		foreach ( $res as $row ) {
			$myvotes[$row->id] = $row->vote;
		}
		$res->free();
		return json_encode( $myvotes );
	}

}
