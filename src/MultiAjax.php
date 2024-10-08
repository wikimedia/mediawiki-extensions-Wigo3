<?php

namespace Wigo3;

use MediaWiki\MediaWikiServices;

class MultiAjax {

	/**
	 * @param string $pollid
	 * @param int $vote
	 * @param int $countoptions
	 * @return string
	 */
	public static function vote( $pollid, $vote, $countoptions ) {
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		global $wgWigo3ConfigStoreIPs;
		$context = \RequestContext::getMain();
		$voter = $wgWigo3ConfigStoreIPs ? $context->getRequest()->getIP() : $context->getUser()->getName();
		$dbw->startAtomic( __METHOD__ );
		$dbw->replace(
			'wigovote',
			[ 'id', 'voter_name' ],
			[
				'id' => $pollid,
				'voter_name' => $voter,
				'vote' => $vote,
				'timestamp' => wfTimestampNow()
			],
			__METHOD__ );
		$dbw->endAtomic( __METHOD__ );
		// get the number of votes for each option
		// $dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$res = $dbw->select(
			'wigovote',
			[ 'vote', 'count' => 'count(vote)' ],
			[ 'id' => $pollid ],
			__METHOD__,
			[ 'GROUP BY' => [ 'id', 'vote' ] ]
		);
		// fill with zeroes
		for ( $i = 0; $i < $countoptions; ++$i ) {
			$results[$i] = 0;
		}
		// now store values for options that have received votes
		foreach ( $res as $row ) {
			$results[$row->vote] = $row->count;
		}
		$res->free();
		return implode( ":", $results );
	}

	/**
	 * @param string $pollid
	 * @return string
	 */
	public static function getmyvote( $pollid ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		global $wgWigo3ConfigStoreIPs;
		$context = \RequestContext::getMain();
		$voter = $wgWigo3ConfigStoreIPs ? $context->getRequest()->getIP() : $context->getUser()->getName();
		$res = $dbr->select(
			'wigovote',
			[ 'vote' ],
			[ 'id' => $pollid, 'voter_name' => $voter ],
			__METHOD__
		);
		$row = $res->fetchRow();
		$myvote = $row['vote'] ?? -1;
		$res->free();
		return "-{$myvote}";
	}
}
