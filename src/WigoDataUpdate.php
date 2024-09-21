<?php

namespace Wigo3;

use MediaWiki\MediaWikiServices;

class WigoDataUpdate extends \DataUpdate {
	/** @var \ArrayObject */
	private $polls;

	/**
	 * @param \ArrayObject $polls
	 */
	public function __construct( $polls ) {
		parent::__construct();
		$this->polls = $polls;
	}

	/**
	 * @inheritDoc
	 */
	public function doUpdate() {
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		foreach ( $this->polls as $poll ) {
			$dbw->replace(
				'wigotext',
				'vote_id',
				[
					'vote_id' => $poll['id'],
					'text' => $poll['text'],
					'is_cp' => $poll['cp']
				], __METHOD__ );
		}
	}
}
