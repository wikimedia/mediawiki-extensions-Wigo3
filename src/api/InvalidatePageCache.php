<?php
/**
 * Wigo3 API module - handles invalidating the page cache when a vote on the
 * page is changed
 *
 * @file
 * @ingroup API
 */
namespace Wigo3\API;

use ApiBase;
// use MediaWiki\ParamValidator\TypeDef\TitleDef;
// use MediaWiki\Title\Title;
use Title;
use Wikimedia\ParamValidator\ParamValidator;

class InvalidatePageCache extends ApiBase {

	/** @inheritDoc */
	public function execute() {
		$user = $this->getUser();

		$params = $this->extractRequestParams();

		$retVal = 'notok';

		$title = Title::newFromText( $params['pagename'] );

		// Some basic validation copied from ApiBase#getTitleOrPageId
		if ( !$title || $title->isExternal() ) {
			$this->dieWithError( [ 'apierror-invalidtitle', wfEscapeWikiText( $params['pagename'] ) ] );
		}
		if ( !$title->canExist() ) {
			$this->dieWithError( 'apierror-pagecannotexist' );
		}
		// End copied validation code

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
				// Per CR, once MW 1.43+ is supported&required, we can change this to:
				/*
				ParamValidator::PARAM_TYPE => 'title',
				ParamValidator::PARAM_REQUIRED => true,
				TitleDef::PARAM_RETURN_OBJECT => true,
				*/
				// and simplify #execute by removing some of the copied code from it.
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
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
