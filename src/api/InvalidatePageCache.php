<?php
/**
 * Wigo3 API module - handles invalidating the page cache when a vote on the
 * page is changed
 *
 * @file
 * @ingroup API
 */
namespace Wigo3\API;

use MediaWiki\Api\ApiBase;
use MediaWiki\ParamValidator\TypeDef\TitleDef;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

class InvalidatePageCache extends ApiBase {

	/** @inheritDoc */
	public function execute() {
		$user = $this->getUser();

		$params = $this->extractRequestParams();

		$retVal = 'notok';

		$title = Title::newFromText( $params['pagename'] );

		if ( $title->invalidateCache() === true ) {
			$retVal = 'ok';
		}

		$this->getResult()->addValue( null, $this->getModuleName(), $retVal );
	}

	/** @inheritDoc */
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
			'pagename' => [
				ParamValidator::PARAM_TYPE => 'title',
				ParamValidator::PARAM_REQUIRED => true,
				TitleDef::PARAM_RETURN_OBJECT => true,
			]
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=wigo-invalidate-page-cache&pagename=Foo_bar'
				=> 'apihelp-wigo-invalidate-page-cache-example-1'
		];
	}

}
