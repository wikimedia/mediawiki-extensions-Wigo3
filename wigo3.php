<?php

# This extension requires a table in the database, create it with the following mysql command:
# replace varbinary with varchar depending on wiki setup
# create table /*$wgDBprefix*/wigovote (id varbinary(255) NOT NULL, voter_name varbinary(255) NOT NULL, vote int NOT NULL default 0, timestamp varbinary(14), PRIMARY KEY (id,voter_name)) /*$wgDBTableOptions*/;

if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

# store ips instead of usernames for logged in users too
$wgWigo3ConfigStoreIPs = true;

$wgExtensionCredits['parserhook'][] = array(
        'name' => 'WIGO Voting Extension',
        'author' => '[http://rationalwiki.com/wiki/User:Nx Nx]',
        'url' => 'http://rationalwiki.com/',
        'description' => 'Up/down voting system.',
        'version' => '3.5'
);

# register api query module
$wgAPIListModules['wigo'] = 'ApiWigo';
$wgAPIListModules['wigovotes'] = 'ApiWigoVotes';

$wgWigoIP = dirname( __FILE__ );
$wgExtensionMessagesFiles['wigo3'] = "$wgWigoIP/wigo3.i18n.php";

# Load classes
$wgAutoloadClasses['ApiWigo'] =  "$wgWigoIP/wigo3.body.php";
$wgAutoloadClasses['ApiWigoVotes'] =  "$wgWigoIP/wigo3.body.php";

$wgHooks['ParserFirstCallInit'][] = 'wigo3init';

$wgHooks['LanguageGetMagic'][]       = 'wigo3magic';

//$wgHooks['AjaxAddScript'][] = 'wigo3addjs';

global $wgUseAjax;
if ($wgUseAjax)
{
  $wgAjaxExportList[] = "wigovote";
  $wgAjaxExportList[] = "wigovote2";
  $wgAjaxExportList[] = "wigovotebatch";
  $wgAjaxExportList[] = "wigoinvalidate";
  $wgAjaxExportList[] = "wigogetmyvotes";
}

function wigo3init( &$parser ) {
  wfLoadExtensionMessages('wigo3');
  $parser->setHook('vote','wigo3render');
  $parser->setHook('votecp','wigo3rendercp');
  $parser->setHook('capture','wigo3rendercapture');
  $parser->setFunctionHook( 'captureencode', 'wigo3rendercaptureencode' );
  return true;
}

function wigo3magic( &$magicWords, $langCode ) {
  $magicWords['captureencode'] = array( 0, 'captureencode' );
  return true;
}

function wigo3addjs($out) {
  global $wgJsMimeType, $wgScriptPath;
  $out->addScript("<script type=\"{$wgJsMimeType}\" src=\"{$wgScriptPath}/extensions/wigo3/js/wigo3.js\"></script>");
  return true;
}

function wigoinvalidate($pagename)
{
  //$pagename is wgPageName from javascript  
  $title = Title::newFromText($pagename);
  if ($title->invalidateCache() === true) {
    $dbw = wfGetDB(DB_MASTER);
    $dbw->commit();
    return "ok";
  } else {
    return "notok";
  }
}

/*
kept for compatibility
*/
function wigovote($pollid, $vote, $min=-1, $max=1)
{
  $dbw = wfGetDB(DB_MASTER);
  global $wgUser, $wgWigo3ConfigStoreIPs, $wgRequest;
  $voter = $wgWigo3ConfigStoreIPs ? $wgRequest->getIP() : $wgUser->getName();
  $result = $dbw->replace('wigovote',array('id','voter_name'),array('id' => $pollid, 'voter_name' => $voter, 'vote' => $vote, 'timestamp' => wfTimestampNow()),__FUNCTION__);
  $res = $dbw->select('wigovote',array('sum(vote)','count(vote)'),array('id' => $pollid, "vote >= {$min}", "vote <= {$max}"),__FUNCTION__,array('GROUP BY' => 'id'));
  $row = $res->fetchRow();
  $vote = $row['sum(vote)'];
  $countvotes = $row['count(vote)'];
  wfLoadExtensionMessages('wigo3');
  $totalvotes = wfMsgExt('wigovotestotal',array('parsemag'),array($countvotes));
  $res->free();
  $dbw->commit();
  return "$pollid:$vote:$countvotes:$totalvotes:$result";
}

/*
batch voting - parameters should be even, in the form pollid, vote
*/
function wigovotebatch( $min=-1, $max=1 /*,...*/ ) {
	$params = func_get_args();
	array_shift($params);
	array_shift($params);
	if ((count($params) % 2) !== 0){
		return 'false: ' . count($params);
	}
  $dbw = wfGetDB(DB_MASTER);
  global $wgUser, $wgWigo3ConfigStoreIPs,$wgRequest;
	$voter = $wgWigo3ConfigStoreIPs ? $wgRequest->getIP() : $wgUser->getName();
 	$votes = array();
 	$pollids = array();
	for ($i = 0; $i < count($params); $i += 2) {
		$pollids[] = $params[$i];
    $votes[] = array('id' => $params[$i], 'voter_name' => $voter, 'vote' => $params[$i+1], 'timestamp' => wfTimestampNow());
	}
  $result = $dbw->replace('wigovote',array('id','voter_name'),$votes,__FUNCTION__);
  $res = $dbw->select('wigovote',array('id','sum(vote)','count(vote)'),array('id' => $pollids, "vote >= {$min}", "vote <= {$max}"),__FUNCTION__,array('GROUP BY' => 'id'));
  
  wfLoadExtensionMessages('wigo3');
  $resultvotes = array();
  while ($row = $res->fetchRow())
  {
    $vote = $row['sum(vote)'];
  	$countvotes = $row['count(vote)'];
  	$totalvotes = wfMsgExt('wigovotestotal',array('parsemag'),array($countvotes));
    $resultvotes[$row['id']] = array($vote,$countvotes,$totalvotes);
  }
  $res->free();
  $dbw->commit();
  return json_encode($resultvotes);
}

/*
voting function for up-down wigo only
*/
function wigovote2($pollid, $vote)
{
  /*store the vote*/
  $dbw = wfGetDB(DB_MASTER);
  global $wgUser, $wgWigo3ConfigStoreIPs, $wgRequest;
  $voter = $wgWigo3ConfigStoreIPs ? $wgRequest->getIP() : $wgUser->getName();
  $result = $dbw->replace('wigovote',array('id','voter_name'),array('id' => $pollid, 'voter_name' => $voter, 'vote' => $vote, 'timestamp' => wfTimestampNow()),__FUNCTION__);
  $dbw->commit();
  
  /*get updated data to update page*/
  $plus = 0;
  $minus = 0;
  $zero = 0;
  $myvote = -2;

  wigo3getvotes($pollid, $plus, $minus, $zero,$voter,$myvote);
  
  wfLoadExtensionMessages('wigo3');
  $totalvotes = $plus + $minus + $zero;
  $totaltooltip = wfMsgExt('wigovotestotald',array('parsemag'),array($totalvotes,$plus,$zero,$minus));
/*  $totalup = wfMsgExt('wigovotestotal',array('parsemag'),array($plus));
  $totaldown = wfMsgExt('wigovotestotal',array('parsemag'),array($minus));
  $totalneutral = wfMsgExt('wigovotestotal',array('parsemag'),array($zero));*/
  $distribtitle = wfMsgExt('wigovotedistrib',array('parsemag'),array($plus,$zero,$minus));
  return "$pollid:$plus:$minus:$zero:$totalvotes:$totaltooltip:$distribtitle:$myvote:$result";
}

function wigo3getvotes($voteid, &$plus, &$minus, &$zero, $voter, &$myvote) {
  $dbr = wfGetDB(DB_SLAVE);
  $res = $dbr->select('wigovote',
                      array('sum(case vote when 1 then 1 else 0 end) as plus',
                            'sum(case vote when -1 then 1 else 0 end) as minus',
                            'sum(case vote when 0 then 1 else 0 end) as zero'),
                      array('id' => $voteid),__FUNCTION__);
  if ($row = $res->fetchRow())
  {
    $plus = $row['plus'];
    $minus = $row['minus'];
    $zero = $row['zero'];
  }
  if ($plus == null) {
    $plus = 0;
  }
  if ($minus == null) {
    $minus = 0;
  }
  if ($zero == null) {
    $zero = 0;
  }
  $res->free();
  //myvote does not work with caching
/*  $myvote = $dbr->selectField('wigovote','vote',array('id' => $voteid, 'voter_name' => $voter),__FUNCTION__);
  if ($myvote === false) {
    $myvote = -2;
  }*/
  $myvote = -2;
}

//get votes for multiple polls, to cut down on AJAX requests
function wigogetmyvotes( /*...*/ ) {
  $dbr = wfGetDB(DB_SLAVE);
  $args = func_get_args();
  global $wgUser, $wgWigo3ConfigStoreIPs,$wgRequest;
  $voter = $wgWigo3ConfigStoreIPs ? $wgRequest->getIP() : $wgUser->getName();
  $res = $dbr->select('wigovote',array('id','vote'),array('id' => $args, 'voter_name' => $voter),__METHOD__);
  $myvotes = array();
  foreach($args as $a) {
  	$myvotes[$a] = false;
  }
  while ($row = $res->fetchRow())
  {
    $myvotes[$row['id']] = $row['vote'];
  }
  $res->free();
  return json_encode($myvotes);
}

function wigo3rendercp($input, $args, $parser, $frame) {
  return wigo3render($input, $args, $parser,$frame,true);
}

function wigo3render($input, $args, $parser, $frame, $cp = false) {
  //disabled to improve performance
  //$parser->disableCache();
  $voteid = $args['poll'];
  $voteid = str_replace( ' ', '_', $voteid );
  if (!$voteid)
  {
    static $err = null;
    if (is_null($err)) {
      wfLoadExtensionMessages('wigo3');
      $err = wfMsg('wigoerror');
    }
    $output = $parser->recursiveTagParse($input, $frame);
    return "<p><span style='color:red;'>{$err}</span> {$output}</p>";
  }
  
  //inject js
  global $wgJsMimeType, $wgScriptPath;
  $parser->mOutput->addHeadItem("<script type=\"{$wgJsMimeType}\" src=\"{$wgScriptPath}/extensions/wigo3/js/wigo3.js\"></script>",'wigo3js');
  
  $plus = 0;
  $minus = 0;
  $zero = 0;
  $myvote = -2;
  global $wgUser, $wgWigo3ConfigStoreIPs,$wgRequest;
  $voter = $wgWigo3ConfigStoreIPs ? $wgRequest->getIP() : $wgUser->getName(); 
  wigo3getvotes($voteid,$plus,$minus,$zero,$voter,$myvote);
  $votes = $plus - $minus;
  $countvotes = $plus + $minus + $zero;

  //backwards compatible img tag handling
  if (!$cp && array_key_exists('img',$args) && (!strcmp($args['img'],'on') || !strcmp($args['img'],'expanded'))) {
    $input = preg_replace('/\[[^\]]*conservapedia\.com[^\]]*\]/i',
                          "$0<sup>[[:Image:{$args['poll']}_x.png|img]]</sup>",$input);
    $x = 0;
    do {
      $input = preg_replace('/(<sup>\[\[:Image:' . $args['poll'] . '_)x(\.png\|img\]\]<\/sup>)/',
                          "$1 {$x}$2", $input,1,$count);
      ++$x;
    } while ($count);
  }

  $output = $parser->recursiveTagParse($input, $frame);  
  //votecp img tag handling
  if ($cp)
  {
    $matchi = preg_match_all('/(<a[^>]*href="([^"]*conservapedia\.com[^"]*)"[^>]*>(?:[^<]|<[^\/]|<\/[^a]|<\/a[^>])*<\/a>)(?!<span class="wigocapture">)/i', $output,$matches,PREG_OFFSET_CAPTURE);
    if ($matchi > 0) $newoutput = substr($output,0,$matches[1][0][1]);
    for ($i=0; $i<$matchi;++$i) {
      //performance impact of sha1 is 1.2x
      $imgname = 'capture_' /*. $args['poll'] . '_'*/ . /*crc32*/ /*md5*/ sha1($matches[2][$i][0]) . '.png';
      $text = $matches[1][$i][0];
      $img = $parser->recursiveTagParse("<span class=\"wigocapture\"><sup>[[:Image:$imgname|img]]</sup></span>", $frame);
      $nextlength = (($i == $matchi-1) ? (strlen($output) - ($matches[1][$i][1] + strlen($text))) : ($matches[1][$i+1][1] - ($matches[1][$i][1] + strlen($text))));
      $newoutput .= substr($output,$matches[1][$i][1],strlen($text)) . $img . 
                    substr($output,$matches[1][$i][1]+strlen($text),$nextlength);
    }
    if ($matchi > 0) $output = $newoutput;  
  }  

  //wfMsgExt resets the parser state if the message contains a parser function, breaking for example references. recursiveTagParse doesn't.
  wfLoadExtensionMessages('wigo3');
  //$totalvotes = wfMsgExt('wigovotestotald',array('parsemag'),array($countvotes,$plus,$zero,$minus));
  $totalvotes = htmlspecialchars($parser->recursiveTagParse(wfMsgNoTrans('wigovotestotald',array($countvotes,$plus,$zero,$minus)), $frame));
/*  $totalup = wfMsgExt('wigovotestotal',array('parsemag'),array($plus));
  $totaldown = wfMsgExt('wigovotestotal',array('parsemag'),array($minus));
  $totalneutral = wfMsgExt('wigovotestotal',array('parsemag'),array($zero));*/
  //$distribtitle = wfMsgExt('wigovotedistrib',array('parsemag'),array($plus,$zero,$minus));
  $distribtitle = htmlspecialchars($parser->recursiveTagParse(wfMsgNoTrans('wigovotedistrib',array($plus,$zero,$minus)), $frame));
  if ($countvotes != 0) {
    $uppercent = ($plus / $countvotes) * 100;
    $downpercent = ($minus / $countvotes) * 100;
    $neutralpercent = ($zero / $countvotes) * 100;
  } else {
    $uppercent = 0;
    $downpercent = 0;
    $neutralpercent = 0;
  }
  
  if ($uppercent != 0) {
    $uppercent .= "%";
  }
  if ($neutralpercent != 0) {
    $neutralpercent .= "%";
  }
  if ($downpercent != 0) {
    $downpercent .= "%";
  }
  
  if (array_key_exists('closed',$args) && strcasecmp($args['closed'],"yes") === 0) {
    return "<table class=\"vote\" cellspacing=\"2\" cellpadding=\"2\" border=\"0\">" .
              "<tr>" .
                "<td style=\"white-space:nowrap;\">" .
                    "<table id=\"{$voteid}-dist\" class=\"wigodistribution\" style=\"width:48px; height:6px; border:1px solid grey; margin:0; padding:0; border-spacing:0;\" title=\"{$distribtitle}\">" .
                      "<tr>" .
                        "<td class=\"wigodist-up\" style=\"border:none; background-color:limegreen; margin:0; padding:0; width:{$uppercent}; height:100%; " . ($totalvotes == 0 ? "display:none;" : "") . "\"></td>" . 
                        "<td class=\"wigodist-neutral\" style=\"border:none; background-color:orange; margin:0; padding:0; width:{$neutralpercent}; height:100%; " . ($totalvotes == 0 ? "display:none;" : "") . "\"></td>" . 
                        "<td class=\"wigodist-down\" style=\"border:none; background-color:red; margin:0; padding:0; width:{$downpercent}; height:100%; " . ($totalvotes == 0 ? "display:none;" : "") . "\"></td>" . 
                    "</tr></table>" .
                "</td>" .
                "<td style=\"min-width:25px; text-align:center;\">" .
                  "<span id=\"{$voteid}\" title=\"{$totalvotes}\">{$votes}</span>" .
                "</td>" .
                "<td style=\"vertical-align:middle;\">" .
                  "<!--$voteid-->$output" .
                "</td>" .
              "</tr>" .
            "</table>";
  } else {
    //get up-down images
    //static improves performance a bit
    static $up = null;
    static $down = null;
    static $reset = null;
    static $altup = null;
    static $altdown = null;
    static $altreset = null;
    static $titleup = null;
    static $titledown = null;
    static $titlereset = null;

    if ( is_null($up) || is_null($down) || is_null($reset) 
         || is_null($altup) || is_null($altdown) || is_null($altreset) 
         || is_null($titleup) || is_null($titledown) || is_null($titlereset) ) {
      wfLoadExtensionMessages('wigo3');
      $up = wfFindFile(wfMsg('wigouparrow'));
      $up = $up ? $up->getFullUrl() : '';
      $down = wfFindFile(wfMsg('wigodownarrow'));
      $down = $down ? $down->getFullUrl() : '';
      $reset = wfFindFile(wfMsg('wigoresetvote'));
      $reset = $reset ? $reset->getFullUrl() : '';
      $altup = wfMsgHtml('wigoaltup');
      $altdown = wfMsgHtml('wigoaltdown');
      $altreset = wfMsgHtml('wigoaltreset');
      $titleup = wfMsgHtml('wigotitleup');
      $titledown = wfMsgHtml('wigotitledown');
      $titlereset = wfMsgHtml('wigotitlereset');
    }

    return "<table class=\"vote\" cellspacing=\"2\" cellpadding=\"2\" border=\"0\">" .
              "<tr>" .
                "<td style=\"white-space:nowrap;\">" .
                  "<a href=\"javascript:wigovoteup('{$voteid}')\" id=\"{$voteid}-up\" class=\"wigobutton wigoupbutton " . ($myvote == 1 ? "myvotebutton" : "") . " \">" .
                    "<img alt=\"{$altup}\" title=\"{$titleup}\" src=\"$up\"></img></a>" .
                  "<a href=\"javascript:wigovotereset('{$voteid}')\" id=\"{$voteid}-neutral\" class=\"wigobutton wigoneutralbutton " . ($myvote == 0 ? "myvotebutton" : "") . " \">" .
                    "<img alt=\"{$altreset}\" title=\"{$titlereset}\" src=\"$reset\"></img></a>" .
                  "<a href=\"javascript:wigovotedown('{$voteid}')\" id=\"{$voteid}-down\" class=\"wigobutton wigodownbutton " . ($myvote == -1 ? "myvotebutton" : "") . " \">" .
                    "<img alt=\"{$altdown}\" title=\"{$titledown}\" src=\"$down\"></img></a>" .
                    "<table id=\"{$voteid}-dist\" class=\"wigodistribution\" style=\"width:100%; height:6px; border:1px solid grey; margin:0; padding:0; border-spacing:0;\" title=\"{$distribtitle}\">" .
                      "<tr>" .
                        "<td class=\"wigodist-up\" style=\"border:none; background-color:limegreen; margin:0; padding:0; width:{$uppercent}; height:100%; " . ($totalvotes == 0 ? "display:none;" : "") . "\"></td>" . 
                        "<td class=\"wigodist-neutral\" style=\"border:none; background-color:orange; margin:0; padding:0; width:{$neutralpercent}; height:100%; " . ($totalvotes == 0 ? "display:none;" : "") . "\"></td>" . 
                        "<td class=\"wigodist-down\" style=\"border:none; background-color:red; margin:0; padding:0; width:{$downpercent}; height:100%; " . ($totalvotes == 0 ? "display:none;" : "") . "\"></td>" . 
                    "</tr></table>" .
                "</td>" .
                "<td style=\"min-width:25px; text-align:center;\">" .
                  "<span id=\"{$voteid}\" title=\"{$totalvotes}\">{$votes}</span>" .
                "</td>" .
                "<td style=\"vertical-align:middle;\">" .
                  "<!--$voteid-->$output" .
                "</td>" .
              "</tr>" .
            "</table>";
  }
}

function wigo3rendercapture($input, $args, $parser, $frame) {
  $output = $parser->recursiveTagParse($input, $frame);
  $matchi = preg_match_all('/(<a[^>]*href="([^"]*)"[^>]*>(?:[^<]|<[^\/]|<\/[^a]|<\/a[^>])*<\/a>)(?!<span class="wigocapture">)/i', $output,$matches,PREG_OFFSET_CAPTURE);
  if ($matchi > 0) $newoutput = substr($output,0,$matches[1][0][1]);
  for ($i=0; $i<$matchi;++$i) {
    $imgname = 'capture_' /*. $args['poll'] . '_'*/ . /*crc32*/ /*md5*/ sha1($matches[2][$i][0]) . '.png';
    $text = $matches[1][$i][0];
    $img = $parser->recursiveTagParse("<span class=\"wigocapture\"><sup>[[:Image:$imgname|img]]</sup></span>", $frame);
    $nextlength = (($i == $matchi-1) ? (strlen($output) - ($matches[1][$i][1] + strlen($text))) : ($matches[1][$i+1][1] - ($matches[1][$i][1] + strlen($text))));
    $newoutput .= substr($output,$matches[1][$i][1],strlen($text)) . $img . 
                  substr($output,$matches[1][$i][1]+strlen($text),$nextlength);
  }
  if ($matchi > 0) $output = $newoutput;
  return $output;
}

//the parser function, returns the encoded image filename
function wigo3rendercaptureencode( $parser, $param1 ) {
  return 'capture_' . /*crc32*/ /*md5*/ sha1($param1) . '.png';
}

