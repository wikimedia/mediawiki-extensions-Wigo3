<?php

namespace Wigo3;

class VoteStore {
	public static function getVoteCounts( $voteid ) {
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			'wigovote',
			[ 'sum(case vote when 1 then 1 else 0 end) as plus',
			  'sum(case vote when -1 then 1 else 0 end) as minus',
			  'sum(case vote when 0 then 1 else 0 end) as zero' ],
			[ 'id' => $voteid ], __FUNCTION__
		);
		if ( $row = $res->fetchRow() ) {
			return [
				'plus' => $row['plus'],
				'minus' => $row['minus'],
				'zero' => $row['zero']
			];
		} else {
			return [
				'plus' => 0,
				'minus' => 0,
				'zero' => 0
			];
		}
	}
}
