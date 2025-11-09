<?php

namespace Wigo3;

use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Xml\Xml;

class MultiParserHooks {
	/**
	 * @param string $input
	 * @param string[] $args
	 * @param Parser $parser
	 * @return string
	 */
	public static function multi( $input, $args, $parser ) {
		$voteid = $args['poll'];
		$voteid = str_replace( ' ', '_', $voteid );
		if ( !$voteid ) {
			static $err = null;
			if ( $err === null ) {
				$err = wfMessage( 'wigo-error' )->text();
			}
			$output = $parser->recursiveTagParse( $input );
			return "<p><span style='color:red;'>{$err}</span> {$output}</p>";
		}

		// inject CSS & JS
		$parserOutput = $parser->getOutput();

		$parserOutput->addModules( [ 'ext.wigo3.multi' ] );
		$parserOutput->appendJsConfigVar( 'wigo3MultiVoteId', $voteid );

		$parserOutput->addModuleStyles( [ 'ext.wigo3.multi.styles' ] );

		// avoid hacking wigo votes
		$voteid = "multi" . $voteid;

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		// get the total number of votes
		$sum = intval( $dbr->selectField(
			'wigovote',
			'count(vote)',
			[ 'id' => $voteid ],
			__METHOD__,
			[ 'GROUP BY' => 'id' ] ) );

		$lines = preg_split( '/\n+/', trim( $input ) );
		foreach ( $lines as $i => $line ) {
			// get the result for this option
			$results[$i] = intval( $dbr->selectField(
				'wigovote',
				'count(vote)',
				[ 'id' => $voteid, "vote" => $i ],
				__METHOD__
			) );

			$jsVoteId = Xml::encodeJsVar( $voteid );
			$htmlVoteId = htmlspecialchars( $voteid );
			$htmlJsVoteId = htmlspecialchars( $jsVoteId );

			$line = "<span id=\"{$htmlVoteId}-{$i}\">{$line}</span>";
			$resultstr[$i] = "<span id=\"{$htmlVoteId}-{$i}-result\">" . $results[$i] . "</span>";
			$outputlines[] = $parser->recursiveTagParse( $line );
		}

		if (
			array_key_exists( 'closed', $args ) &&
			strcasecmp( $args['closed'], 'yes' ) === 0
		) {
			$output = '<table class="multivote">';
			foreach ( $outputlines as $i => $line ) {
				if ( $sum == 0 ) {
					$percent = 0;
				} else {
					$percent = $results[$i] / $sum * 100;
				}
				// phpcs:disable Generic.Files.LineLength.TooLong
				$output .= <<<HTML
<tr>
	<td class="multioption multiclosed">
		$line
	</td>
	<td class="multiresult">"
		{$resultstr[$i]}
	</td>
	<td class="multicolumncontainer">
		<div class="votecolumnback">
			<div id="$htmlVoteId-{$i}-column" class="votecolumnfront" style="width:{$percent}%;"></div>
		</div>
	</td>
</tr>
HTML;
				// phpcs:enable Generic.Files.LineLength.TooLong

			}
			$output .= '</table>';
			return $output;
		} else {
			$output = '<table class="multivote">';
			$numLines = count( $outputlines );
			$votetitle = wfMessage( 'wigo-multi-vote-title' )->escaped();
			$votebutton = wfMessage( 'wigo-multi-vote-button' )->escaped();

			foreach ( $outputlines as $i => $line ) {
				if ( $sum == 0 ) {
					$percent = 0;
				} else {
					$percent = $results[$i] / $sum * 100;
				}
				// phpcs:disable Generic.Files.LineLength.TooLong
				$output .= <<<HTML
<tr>
	<td class="multioption">
		$line
	</td>
	<td class="multiresult">
		$resultstr[$i]
	</td>
	<td class="multibutton">
		<a href="javascript:mediaWiki.multivote.send($htmlJsVoteId,$i,$numLines)" title="$votetitle">$votebutton</a>
	</td>
	<td class="multicolumncontainer">
		<div class="votecolumnback">
			<div id="{$htmlVoteId}-{$i}-column" class="votecolumnfront" style="width:{$percent}%;"></div>
		</div>
	</td>
</tr>
HTML;
			}
			$output .= '</table>';
			// phpcs:enable Generic.Files.LineLength.TooLong
			return $output;
		}
	}
}
