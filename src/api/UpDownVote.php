<?php
/**
 * Wigo3 API module - casts a vote for a regular up/downvote only poll
 *
 * @file
 * @ingroup API
 * @see the /js/wigo3.js file for how this is called
 */
namespace Wigo3\API;

use MediaWiki\Api\ApiBase;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use Wikimedia\ParamValidator\ParamValidator;

class UpDownVote extends ApiBase {

	/** @inheritDoc */
	public function execute() {
		$user = $this->getUser();

		// Get the request parameters
		$params = $this->extractRequestParams();

		$pollid = $params['pollid'];
		$vote = $params['vote'];

		// Store the vote
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$context = RequestContext::getMain();
		$voter = $this->getConfig()->get( 'Wigo3ConfigStoreIPs' ) ? $context->getRequest()->getIP() : $user->getName();

		$dbw->replace(
			'wigovote',
			[ [ 'id', 'voter_name' ] ],
			[
				'id' => $pollid,
				'voter_name' => $voter,
				'vote' => $vote,
				'timestamp' => $dbw->timestamp( wfTimestampNow() )
			],
			__METHOD__
		);

		// Get updated data to update page
		$plus = 0;
		$minus = 0;
		$zero = 0;
		$myVote = -2;

		self::getVotes( $pollid, $plus, $minus, $zero, $voter, $myVote );

		$totalVotes = $plus + $minus + $zero;
		$totalTooltip = $this->msg( 'wigo-votes-total-d' )
			->params( $totalVotes, $plus, $zero, $minus )
			->text();
		$distribTitle = $this->msg( 'wigo-vote-distrib' )
			->params( $plus, $zero, $minus )
			->text();

		// @todo In the future, change this return value and the caller(s) to something more programmatical...
		$retVal = "$pollid:$plus:$minus:$zero:$totalVotes:$totalTooltip:$distribTitle:$myVote:";

		$this->getResult()->addValue( null, $this->getModuleName(), $retVal );
	}

	/**
	 * @param string $voteId
	 * @param int &$plus
	 * @param int &$minus
	 * @param int &$zero
	 * @param string $voter
	 * @param int &$myVote
	 */
	private static function getVotes( $voteId, &$plus, &$minus, &$zero, $voter, &$myVote ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$res = $dbr->select(
			'wigovote',
			[
				'SUM(CASE vote WHEN 1 THEN 1 ELSE 0 END) AS plus',
				'SUM(CASE vote WHEN -1 THEN 1 ELSE 0 END) AS minus',
				'SUM(CASE vote WHEN 0 THEN 1 ELSE 0 END) AS zero'
			],
			[ 'id' => $voteId ],
			__METHOD__
		);
		$row = $res->fetchRow();
		$plus = $row['plus'] ?? 0;
		$minus = $row['minus'] ?? 0;
		$zero = $row['zero'] ?? 0;
		$myVote = -2;
	}

	/**
	 * Per code review and the todo comment in #execute, the output format is presumably
	 * going to change, which makes this API an unstable one.
	 * Let's inform any and all potential external consumers of this API about that so they know
	 * what (not) to expect.
	 *
	 * @return bool
	 */
	public function isInternal() {
		return true;
	}

	/** @inheritDoc */
	public function isWriteMode() {
		return true;
	}

	/** @inheritDoc */
	public function needsToken() {
		return 'csrf';
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'pollid' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			],
			'vote' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true
			]
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=wigo-vote-updown&pollid=cats&vote=1'
				=> 'apihelp-wigo-vote-updown-example-1',
			'action=wigo-vote-updown&pollid=snakes&vote=-1'
				=> 'apihelp-wigo-vote-updown-example-2'
		];
	}

}
