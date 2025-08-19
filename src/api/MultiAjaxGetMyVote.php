<?php
/**
 * Wigo3 API module - gets the user's current vote (or -1 if they've not voted yet)
 *
 * @file
 * @ingroup API
 * @see the /js/multi.js file for how this is called
 */
namespace Wigo3\API;

use ApiBase;
use RequestContext;
use Wikimedia\ParamValidator\ParamValidator;

class MultiAjaxGetMyVote extends ApiBase {

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
		$context = RequestContext::getMain();
		$voter = $this->getConfig()->get( 'Wigo3ConfigStoreIPs' ) ? $context->getRequest()->getIP() : $user->getName();

		$res = $this->getDB()->select(
			'wigovote',
			[ 'vote' ],
			[ 'id' => $pollid, 'voter_name' => $voter ],
			__METHOD__
		);
		$row = $res->fetchRow();
		$myVote = $row['vote'] ?? -1;
		$res->free();

		$this->getResult()->addValue( null, $this->getModuleName(), "-{$myVote}" );
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'pollid' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=wigo-get-my-vote&pollid=multiBirds'
				=> 'apihelp-wigo-get-my-vote-example-1'
		];
	}

}
