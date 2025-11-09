<?php
/**
 * Wigo3 API module - casts a vote.
 *
 * @file
 * @ingroup API
 * @see the /js/multi.js file for how this is called
 */
namespace Wigo3\API;

use MediaWiki\Api\ApiBase;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use Wikimedia\ParamValidator\ParamValidator;

class MultiAjaxCastVote extends ApiBase {

	/** @inheritDoc */
	public function execute() {
		$user = $this->getUser();

		// Get the request parameters
		$params = $this->extractRequestParams();

		$pollid = $params['pollid'];
		// Ensure that the poll ID starts with the word "multi" and if it's absent for some reason, add it in
		if ( substr( $pollid, 0, 5 ) !== 'multi' ) {
			$pollid = 'multi' . $pollid;
		}
		$vote = $params['vote'];
		$countOptions = $params['countoptions'];

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$context = RequestContext::getMain();
		$voter = $this->getConfig()->get( 'Wigo3ConfigStoreIPs' ) ? $context->getRequest()->getIP() : $user->getName();

		$dbw->startAtomic( __METHOD__ );
		$dbw->upsert(
			'wigovote',
			[
				'id' => $pollid,
				'voter_name' => $voter,
				'vote' => $vote,
				'timestamp' => $dbw->timestamp( wfTimestampNow() )
			],
			[ 'id' ],
			[
				'voter_name' => $voter,
				'vote' => $vote,
				'timestamp' => $dbw->timestamp( wfTimestampNow() )
			],
			__METHOD__
		);
		$dbw->endAtomic( __METHOD__ );

		// get the number of votes for each option
		// $dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$res = $dbw->select(
			'wigovote',
			[ 'vote', 'count' => 'COUNT(vote)' ],
			[ 'id' => $pollid ],
			__METHOD__,
			[ 'GROUP BY' => [ 'id', 'vote' ] ]
		);

		// fill with zeroes
		for ( $i = 0; $i < $countOptions; ++$i ) {
			$results[$i] = 0;
		}

		// now store values for options that have received votes
		foreach ( $res as $row ) {
			$results[$row->vote] = $row->count;
		}

		$res->free();

		$this->getResult()->addValue( null, $this->getModuleName(), implode( ':', $results ) );
	}

	/**
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'pollid' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			],
			'vote' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true
			],
			'countoptions' => [
				ParamValidator::PARAM_TYPE => 'integer',
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=wigo-cast-vote&pollid=multiBirds&vote=2&countoptions=1'
				=> 'apihelp-wigo-cast-vote-example-1'
		];
	}

}
