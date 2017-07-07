<?php

// Extension credits that show up on Special:Version
$wgExtensionCredits['parserhook'][] = array(
        'name' => 'Multiple choice voting',
        'author' => '[http://rationalwiki.com/wiki/User:Nx Nx]',
        'url' => 'http://rationalwiki.com/',
        'description' => 'Requires the WIGO extension.',
        'version' => '3.5'
);

$wgHooks['ParserFirstCallInit'][] = 'multiinit';

$wgResourceModules['ext.wigo3.multi'] = [
  'scripts' => 'js/multi.js',
  'dependencies' => 'ext.wigo3.wigo3',
  'localBasePath' => __DIR__,
  'remoteExtPath' => 'Wigo3',
];

$wgMultiIP = dirname( __FILE__ );
$wgExtensionMessagesFiles['multi'] = "$wgMultiIP/multi.i18n.php";

global $wgUseAjax;
if ($wgUseAjax)
{
  $wgAjaxExportList[] = "multivote";
  $wgAjaxExportList[] = "multigetmyvote";
}

function multiinit( &$parser ) {
  $parser->setHook('multi','multirender');
  return true;    
}

function multivote($pollid, $vote, $countoptions)
{
  $dbw = wfGetDB(DB_MASTER);
  global $wgUser, $wgWigo3ConfigStoreIPs, $wgRequest;
  $voter = $wgWigo3ConfigStoreIPs ? $wgRequest->getIP() : $wgUser->getName();
  $result = $dbw->replace('wigovote',array('id','voter_name'),array('id' => $pollid, 'voter_name' => $voter, 'vote' => $vote, 'timestamp' => wfTimestampNow()),__METHOD__);
  $dbw->commit();
  //get the number of votes for each option
  //$dbr = wfGetDB(DB_SLAVE);
  $res = $dbw->select('wigovote',array('vote' , 'count(vote)'),array('id' => $pollid),__METHOD__,array('GROUP BY' => 'id', 'GROUP BY' => 'vote'));
  //fill with zeroes
  for ($i=0; $i<$countoptions; ++$i) {
    $results[$i] = 0;
  }
  //now store values for options that have received votes
  while ($row = $res->fetchRow())
  {
    $results[$row['vote']] = $row['count(vote)'];
  }
  $res->free();
  return implode(":",$results);
}

function multigetmyvote($pollid) {
  $dbr = wfGetDB(DB_SLAVE);
  global $wgUser, $wgWigo3ConfigStoreIPs, $wgRequest;
  $voter = $wgWigo3ConfigStoreIPs ? $wgRequest->getIP()  : $wgUser->getName();
  $res = $dbr->select('wigovote',array('vote'),array('id' => $pollid, 'voter_name' => $voter),__METHOD__);
  $myvote = -1;
  if ($row = $res->fetchRow())
  {
    $myvote = $row['vote'];
  }
  $res->free();
  if ($myvote === null) $myvote = -1;
  return "-{$myvote}";
}

function multirender($input, $args, $parser)
{
  $voteid = $args['poll'];
  $voteid = str_replace( ' ', '_', $voteid );
  if (!$voteid)
  {
    static $err = null;
    if (is_null($err)) {
      $err = wfMessage('wigoerror')->text();
    }
    $output = $parser->recursiveTagParse($input);
    return "<p><span style='color:red;'>{$err}</span> {$output}</p>";
  }

  //inject js
  $parserOutput = $parser->getOutput();
  $parserOutput->addModules( 'ext.wigo3.multi' );
  $parserOutput->addJsConfigVars( 'wigo3MultiVoteId', $voteid );

  #avoid hacking wigo votes
  $voteid = "multi" . $voteid;

  $dbr = wfGetDB(DB_SLAVE);
  #get the total number of votes
  $res = $dbr->select('wigovote','count(vote)',array('id' => $voteid),'multirender',array('GROUP BY' => 'id'));
  $sum = 0;
  if ($row = $res->fetchRow())
  {
    $sum = intval($row['count(vote)'],10);
  }
  $res->free();

  $lines = preg_split('/\n+/',trim($input));
  foreach ($lines as $i => $line) {
    # get the result for this option
    $res = $dbr->select('wigovote','count(vote)',array('id' => $voteid, "vote" => $i),'multirender',array('GROUP BY' => 'id', 'GROUP BY' => 'vote'));
    if ($row = $res->fetchRow())
    {
      $results[$i] = $row['count(vote)'];
    } else {
      $results[$i] = "0";
    }
    $res->free();
	#format my vote - doesn't work with caching
	
	$jsVoteId = Xml::encodeJsVar( $voteid );
	$htmlVoteId = htmlspecialchars( $voteid );
	$htmlJsVoteId = htmlspecialchars( $jsVoteId );

	$line = "<span id=\"{$htmlVoteId}-{$i}\">{$line}</span>";
	$resultstr[$i] = "<span id=\"{$htmlVoteId}-{$i}-result\">" . $results[$i] . "</span>";
    $outputlines[] = $parser->recursiveTagParse($line);
  }

  if (array_key_exists('closed',$args) && strcasecmp($args['closed'],"yes") === 0) {
    $output = "<table class=\"multivote\" cellspacing=\"2\" cellpadding=\"2\" border=\"0\">";
    foreach ($outputlines as $i => $line) {
      if ($sum == 0) {
        $percent = 0;
      } else {
        $percent = $results[$i]/$sum * 100;
      }
      $output .=
      "<tr>" .
        "<td class=\"multioption\" style=\"width:20em;\">" . 
          $line .
        "</td>" .
        "<td class=\"multiresult\" style=\"width:2em;\">" .
          $resultstr[$i] .
        "</td>" .
        "<td style=\"margin:0; padding:0;\">" .
          "<div class=\"votecolumnback\" style=\"border: 1px solid black; background:#F0F0F0; width:220px; height:1em;\">" .
          "<div id=\"$htmlVoteId-{$i}-column\" class=\"votecolumnfront\" style=\"background:blue; width:{$percent}%; height:100%;\"></div>" .
          "</div>" .          
        "</td>" .
      "</tr>";
    }
    $output .= "</table>";
    return $output . $boldscript;
  } else {
    $output = "<table class=\"multivote\" cellspacing=\"2\" cellpadding=\"2\" border=\"0\">";
    foreach ($outputlines as $i => $line) {
      if ($sum == 0) {
        $percent = 0;
      } else {
        $percent = $results[$i]/$sum * 100;
      }
      $output .=
      "<tr>" .
        "<td class=\"multioption\" style=\"width:14em;\">" . 
          $line .
        "</td>" .
        "<td class=\"multiresult\" style=\"width:2em;\">" .
          $resultstr[$i] .
        "</td>" .
        "<td class=\"multibutton\" style=\"padding-left:1em; padding-right:1em;\">" . 
          "<a href=\"javascript:mediaWiki.multivote.send($htmlJsVoteId),$i," . count($outputlines) . ")\" title=\"" . wfMessage("multi-votetitle")->escaped() . "\">" . wfMessage("multi-votebutton")->escaped() . "</a>" .
        "</td>" .
        "<td style=\"margin:0; padding:0;\">" .
          "<div class=\"votecolumnback\" style=\"border: 1px solid black; background:#F0F0F0; width:220px; height:1em;\">" .
          "<div id=\"{$htmlVoteId}-{$i}-column\" class=\"votecolumnfront\" style=\"background:blue; width:{$percent}%; height:100%;\"></div>" .
          "</div>" .          
        "</td>" .
      "</tr>";
    }
    $output .= "</table>";
    return $output . $boldscript;
  }
}
