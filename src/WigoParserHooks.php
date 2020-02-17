<?php

namespace Wigo3;

use Xml;

class WigoParserHooks {
	/**
	 * @param string $input
	 * @param string[] $args
	 * @param \Parser $parser
	 * @param \PPFrame $frame
	 * @return string
	 */
	public static function votecp( $input, $args, $parser, $frame ) {
		return self::vote( $input, $args, $parser, $frame, true );
	}

	/**
	 * @param string $input
	 * @param string[] $args
	 * @param \Parser $parser
	 * @param \PPFrame $frame
	 * @param bool $cp Whether the conservapedia/capture variant was called
	 * @return string
	 */
	public static function vote( $input, $args, $parser, $frame, $cp = false ) {
		$voteid = $args['poll'];
		$voteid = str_replace( ' ', '_', $voteid );
		if ( !$voteid ) {
			static $err = null;
			if ( $err === null ) {
				$err = wfMessage( 'wigoerror' )->text();
			}
			$output = $parser->recursiveTagParse( $input, $frame );
			return "<p><span style='color:red;'>{$err}</span> {$output}</p>";
		}

		// inject js
		$parserOutput = $parser->getOutput();
		$parserOutput->addModules( 'ext.wigo3.wigo3' );

		$counts = VoteStore::getVoteCounts( $voteid );
		$votes = $counts['plus'] - $counts['minus'];
		$countvotes = $counts['plus'] + $counts['minus'] + $counts['zero'];

		// backwards compatible img tag handling
		if ( !$cp && array_key_exists( 'img', $args )
			&& ( !strcmp( $args['img'], 'on' ) || !strcmp( $args['img'], 'expanded' ) )
		) {
			$input = preg_replace(
				'/\[[^\]]*conservapedia\.com[^\]]*\]/i',
				"$0<sup>[[:Image:{$args['poll']}_x.png|img]]</sup>", $input
			);
			$x = 0;
			do {
				$input = preg_replace(
					'/(<sup>\[\[:Image:' . $args['poll'] . '_)x(\.png\|img\]\]<\/sup>)/',
					"$1 {$x}$2", $input, 1, $count
				);
				++$x;
			} while ( $count );
		}

		$output = $parser->recursiveTagParse( $input, $frame );
		// votecp img tag handling
		if ( $cp ) {
			$matchi = preg_match_all( '/(<a[^>]*href="([^"]*conservapedia\.com[^"]*)"[^>]*>' .
				'(?:[^<]|<[^\/]|<\/[^a]|<\/a[^>])*<\/a>)(?!<span class="wigocapture">)/i',
				$output, $matches, PREG_OFFSET_CAPTURE );
			if ( $matchi > 0 ) {
				$newoutput = substr( $output, 0, $matches[1][0][1] );
			}
			for ( $i = 0; $i < $matchi; ++$i ) {
				$imgname = 'capture_' . sha1( $matches[2][$i][0] ) . '.png';
				$text = $matches[1][$i][0];
				$img = $parser->recursiveTagParse(
					"<span class=\"wigocapture\"><sup>[[:Image:$imgname|img]]</sup></span>",
					$frame );
				if ( $i == $matchi - 1 ) {
					$nextlength = strlen( $output ) - ( $matches[1][$i][1] + strlen( $text ) );
				} else {
					$nextlength = $matches[1][$i + 1][1] - ( $matches[1][$i][1] + strlen( $text ) );
				}
				$newoutput .= substr( $output, $matches[1][$i][1], strlen( $text ) ) . $img .
					substr( $output, $matches[1][$i][1] + strlen( $text ), $nextlength );
			}
			if ( $matchi > 0 ) {
				$output = $newoutput;
			}
		}

		$totalvotes = wfMessage( 'wigovotestotald' )
			->params( $countvotes, $counts['plus'], $counts['zero'], $counts['minus'] )
			->escaped();
		$distribtitle = wfMessage( 'wigovotedistrib' )
			->params( $counts['plus'], $counts['zero'], $counts['minus'] )
			->escaped();
		if ( $countvotes != 0 ) {
			$uppercent = ( $counts['plus'] / $countvotes ) * 100;
			$downpercent = ( $counts['minus'] / $countvotes ) * 100;
			$neutralpercent = ( $counts['zero'] / $countvotes ) * 100;
		} else {
			$uppercent = 0;
			$downpercent = 0;
			$neutralpercent = 0;
		}

		if ( $uppercent != 0 ) {
			$uppercent .= "%";
		}
		if ( $neutralpercent != 0 ) {
			$neutralpercent .= "%";
		}
		if ( $downpercent != 0 ) {
			$downpercent .= "%";
		}

		$htmlVoteId = htmlspecialchars( $voteid );
		$jsVoteId = htmlspecialchars( Xml::encodeJsVar( $voteid ) );
		$displayNoneIfEmpty = $totalvotes == 0 ? "display:none;" : "";

		if ( array_key_exists( 'closed', $args ) && strcasecmp( $args['closed'], "yes" ) === 0 ) {
			// phpcs:disable Generic.Files.LineLength.TooLong
			return <<<HTML
<table class="vote" cellspacing="2" cellpadding="2" border="0">
	<tr>
		<td style="white-space:nowrap;">
			<table id="{$htmlVoteId}-dist" class="wigodistribution" style="width:48px; height:6px; border:1px solid grey; margin:0; padding:0; border-spacing:0;" title="{$distribtitle}">
				<tr>
					<td class="wigodist-up" style="border:none; background-color:limegreen; margin:0; padding:0; width:{$uppercent}; height:100%; $displayNoneIfEmpty"></td>
					<td class="wigodist-neutral" style="border:none; background-color:orange; margin:0; padding:0; width:{$neutralpercent}; height:100%; $displayNoneIfEmpty"></td>
					<td class="wigodist-down" style="border:none; background-color:red; margin:0; padding:0; width:{$downpercent}; height:100%; $displayNoneIfEmpty"></td>
				</tr>
			</table>
		</td>
		<td style="min-width:25px; text-align:center;">
			<span id="{$htmlVoteId}" title="{$totalvotes}">{$votes}</span>
		</td>
		<td style="vertical-align:middle;">
			<!--{$htmlVoteId}-->$output
		</td>
	</tr>
</table>
HTML;
			// phpcs:enable Generic.Files.LineLength.TooLong
		} else {
			// get up-down images
			static $up = null;
			static $down = null;
			static $reset = null;
			static $altup = null;
			static $altdown = null;
			static $altreset = null;
			static $titleup = null;
			static $titledown = null;
			static $titlereset = null;

			if ( $up === null || $down === null || $reset === null
				|| $altup === null || $altdown === null || $altreset === null
				|| $titleup === null || $titledown === null || $titlereset === null
			) {
				global $wgExtensionAssetsPath;
				$up = "$wgExtensionAssetsPath/Wigo3/images/wigovoteup.png";
				$down = "$wgExtensionAssetsPath/Wigo3/images/wigovotedown.png";
				$reset = "$wgExtensionAssetsPath/Wigo3/images/wigovoteneutral.png";
				$altup = wfMessage( 'wigoaltup' )->escaped();
				$altdown = wfMessage( 'wigoaltdown' )->escaped();
				$altreset = wfMessage( 'wigoaltreset' )->escaped();
				$titleup = wfMessage( 'wigotitleup' )->escaped();
				$titledown = wfMessage( 'wigotitledown' )->escaped();
				$titlereset = wfMessage( 'wigotitlereset' )->escaped();
			}

			// phpcs:disable Generic.Files.LineLength.TooLong
			return "<table class=\"vote\" cellspacing=\"2\" cellpadding=\"2\" border=\"0\">" .
			"<tr>" .
			"<td style=\"white-space:nowrap;\">" .
				"<a href=\"javascript:mediaWiki.wigo.voteup($jsVoteId)\" id=\"{$htmlVoteId}-up\" class=\"wigobutton wigoupbutton\">" .
				"<img alt=\"{$altup}\" title=\"{$titleup}\" src=\"$up\"></a>" .
				"<a href=\"javascript:mediaWiki.wigo.votereset($jsVoteId)\" id=\"{$htmlVoteId}-neutral\" class=\"wigobutton wigoneutralbutton\">" .
				"<img alt=\"{$altreset}\" title=\"{$titlereset}\" src=\"$reset\"></a>" .
				"<a href=\"javascript:mediaWiki.wigo.votedown($jsVoteId)\" id=\"{$htmlVoteId}-down\" class=\"wigobutton wigodownbutton\">" .
				"<img alt=\"{$altdown}\" title=\"{$titledown}\" src=\"$down\"></a>" .
				"<table id=\"{$htmlVoteId}-dist\" class=\"wigodistribution\" style=\"width:100%; height:6px; border:1px solid grey; margin:0; padding:0; border-spacing:0;\" title=\"{$distribtitle}\">" .
					"<tr>" .
						"<td class=\"wigodist-up\" style=\"border:none; background-color:limegreen; margin:0; padding:0; width:{$uppercent}; height:100%; " . ( $totalvotes == 0 ? "display:none;" : "" ) . "\"></td>" .
						"<td class=\"wigodist-neutral\" style=\"border:none; background-color:orange; margin:0; padding:0; width:{$neutralpercent}; height:100%; " . ( $totalvotes == 0 ? "display:none;" : "" ) . "\"></td>" .
						"<td class=\"wigodist-down\" style=\"border:none; background-color:red; margin:0; padding:0; width:{$downpercent}; height:100%; " . ( $totalvotes == 0 ? "display:none;" : "" ) . "\"></td>" .
					"</tr></table>" .
			"</td>" .
			"<td style=\"min-width:25px; text-align:center;\">" .
				"<span id=\"{$htmlVoteId}\" title=\"{$totalvotes}\">{$votes}</span>" .
			"</td>" .
			"<td style=\"vertical-align:middle;\">" .
				"<!--$htmlVoteId-->$output" .
			"</td>" .
			"</tr>" .
			"</table>";
			// phpcs:enable Generic.Files.LineLength.TooLong
		}
	}

	/**
	 * @param string $input
	 * @param string[] $args
	 * @param \Parser $parser
	 * @param \PPFrame $frame
	 * @return string
	 */
	public static function capture( $input, $args, $parser, $frame ) {
		$output = $parser->recursiveTagParse( $input, $frame );
		$matchi = preg_match_all(
			'/(<a[^>]*href="([^"]*)"[^>]*>(?:[^<]|<[^\/]|<\/[^a]|<\/a[^>])*<\/a>)' .
			'(?!<span class="wigocapture">)/i', $output, $matches, PREG_OFFSET_CAPTURE );
		if ( $matchi > 0 ) {
			$newoutput = substr( $output, 0, $matches[1][0][1] );
		}
		for ( $i = 0; $i < $matchi;++$i ) {
			$imgname = 'capture_' . sha1( $matches[2][$i][0] ) . '.png';
			$text = $matches[1][$i][0];
			$img = $parser->recursiveTagParse(
				"<span class=\"wigocapture\"><sup>[[:Image:$imgname|img]]</sup></span>", $frame );
			if ( $i == $matchi - 1 ) {
				$nextlength = strlen( $output ) - ( $matches[1][$i][1] + strlen( $text ) );
			} else {
				$nextlength = $matches[1][$i + 1][1] - ( $matches[1][$i][1] + strlen( $text ) );
			}
			$newoutput .= substr( $output, $matches[1][$i][1], strlen( $text ) ) . $img .
				substr( $output, $matches[1][$i][1] + strlen( $text ), $nextlength );
		}
		if ( $matchi > 0 ) {
			$output = $newoutput;
		}
		return $output;
	}

	/**
	 * the parser function, returns the encoded image filename
	 *
	 * @param \Parser $parser
	 * @param string $param1
	 * @return string
	 */
	public static function captureencode( $parser, $param1 ) {
		return 'capture_' . sha1( $param1 ) . '.png';
	}
}
