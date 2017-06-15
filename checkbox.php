<?php
#include ("/home/rationa1/public_html/bar.php");

// Extension credits that show up on Special:Version
$wgExtensionCredits['parserhook'][] = array(
        'name' => 'Checkbox',
        'author' => '[http://rationalwiki.com/wiki/User:Nx Nx]',
        'url' => 'http://rationalwiki.com/',
        'description' => 'Checkbox voting. Requires the wigo and slider extensions'
);

//Avoid unstubbing $wgParser on setHook() too early on modern (1.12+) MW versions, as per r35980
if ( defined( 'MW_SUPPORTS_PARSERFIRSTCALLINIT' ) ) {
  $wgHooks['ParserFirstCallInit'][] = 'checkboxinit';
} else { // Otherwise do things the old fashioned way
  $wgExtensionFunctions[] = 'checkboxinit';
}

function checkboxinit() {
  global $wgParser;
  $wgParser->setHook('checkbox','checkboxrender');
  $wgParser->setHook('checkboxes','checkboxesrender');
  return true;    
}

function checkboxesrender($input, $args, $parser)
{
  $voteid = $args['poll'];
  $voteid = str_replace( ' ', '_', $voteid );
  if (!$voteid)
  {
    static $err = null;
    if (is_null($err)) {
      wfLoadExtensionMessages('wigo3');
      $err = wfMsg('wigoerror');
    }
    $output = $parser->recursiveTagParse($input);
    return "<p><span style='color:red;'>{$err}</span> {$output}</p>";
  }
  
  #avoid conflicts - checkbox will add check prefix
  $voteid = "set" . $voteid;
  $htmlVoteId = htmlspecialchars( $voteid );
  $jsVoteId = htmlspecialchars( Xml::encodeJsVar( $voteid ) );
  
  $set = $args['set'];
  if (!$set)
  {
  	//fail silently
    return '';
  }
	$closed = false;
	if (array_key_exists('closed',$args) && strcasecmp($args['closed'],"yes") === 0) {
		$closed = true;
	}
	$embedded = false;
	if (array_key_exists('embedded',$args) && strcasecmp($args['embedded'],"yes") === 0) {
		$embedded = true;
	}
	
	//Get the checkbox set
	$list = wfMsg("checkboxes/{$set}");
  $list = preg_replace("/\*/","",$list);
  $options = split("\n",$list);
	
	$output = '<div class="checkboxset">';
	$ids = array();
	$jshacka = array();
	foreach ($options as $option) {
		$parts = explode( ';', $option);
		if ( count($parts) >= 2 ) {
			$title = $parts[0];
			$id = $parts[1];
			$id = Sanitizer::escapeClass(str_replace(' ','_',$id));
		} else {
			//someone forgot the id, generate one for them
			$title = $parts[0];
			$id = Sanitizer::escapeClass($parts[0]);
		}
		$jsId = Xml::encodeJsVar( "check{$voteid}-check-{$id}" );
		$ids[] = $jsId;
		$htmlId = htmlspecialchars( "check{$voteid}-check-{$id}" );
		$htmlTitle = htmlspecialchars( $title );
		$output .= "<p><checkbox poll=\"$htmlId\" closed=" . ($closed ? "yes" : "no") ." bulkmode=yes>{$htmlTitle}</checkbox></p>";
		$jshacka[] = "$jsId : document.getElementById(" . Xml::encodeJsVar( "checkbox-input-check{$voteid}-check-{$id}" ) . ").checked?1:0";
	}
	$jshack = "{" . implode(',',$jshacka) . "}";
	$output .= "</div>";
	wfLoadExtensionMessages('slider');
	$votebutton = "<p>" .
                (($closed || $embedded) ? "" : "  <a class=\"votebutton\" href=\"javascript:wigovotesendarray({$jshack},0,1,true)\" title=\"" . wfMsg("slider-votetitle") . "\">" . wfMsg("slider-votebutton") . "</a>") .
                "</p>";
	
	//get all the votes in one request
	$ids_l = implode(",",$ids);
	$myvotesscript = "<script type=\"text/javascript\">" .
                  "sajax_do_call('wigogetmyvotes',[{$ids_l}],function (req) {" .
                      "if (req.readyState == 4) if (req.status == 200)" .
                      "{".
                      	"var res = eval('(' + req.responseText + ')');" .
                      	"for (voteid in res) {" .
                      	  "if (res[voteid]!==false) {" .
                      	    "c = document.getElementById(\"checkbox-input-\" + voteid);" .
                      	    "c.checked=(res[voteid] == 1);" .
                      	  "}" .
                      	"}" .
                      "}" .
                  "});"  . "</script>";
	
	return $parser->recursiveTagParse($output) . $votebutton . $myvotesscript;
}

function checkboxrender($input, $args, $parser)
{

  $voteid = $args['poll'];
  $voteid = str_replace( ' ', '_', $voteid );
  if (!$voteid)
  {
    static $err = null;
    if (is_null($err)) {
      wfLoadExtensionMessages('wigo3');
      $err = wfMsg('wigoerror');
    }
    $output = $parser->recursiveTagParse($input);
    return "<p><span style='color:red;'>{$err}</span> {$output}</p>";
  }
  
  //inject js
  global $wgJsMimeType, $wgScriptPath;
  $parser->mOutput->addHeadItem("<script type=\"{$wgJsMimeType}\" src=\"{$wgScriptPath}/extensions/wigo3/js/wigo3.js\"></script>",'wigo3js');
  
  if (array_key_exists('bulkmode',$args) && strcasecmp($args['bulkmode'],"yes") === 0) {
  	$bulkmode = true;
  } else {
  	$bulkmode = false;
  }

  #avoid conflicts
  $voteid = "check" . $voteid;
  $dbr = wfGetDB(DB_SLAVE);
  $res = $dbr->select('wigovote',array('count(vote)','sum(vote)'),array('id' => $voteid, "vote >= 0", "vote <= 1"),__FUNCTION__,array('GROUP BY' => 'id'));
  $countvotes = 0;
  $votes = 0;
  if ($row = $res->fetchRow())
  {
  	$votes = $row['sum(vote)'];
    $countvotes = $row['count(vote)'];
  }
  $res->free();

  #get my vote
  $myvote = null;
/*  global $wgUser, $wgWigo3ConfigStoreIPs;
  $voter = $wgWigo3ConfigStoreIPs ? getenv ("REMOTE_ADDR") : $wgUser->getName();
  $res = $dbr->select('wigovote','vote',array('id' => $voteid, 'voter_name' => $voter),'wigo3render');
  if ($row = $res->fetchRow())
  {
    $myvote = $row['vote'];
  }
  $res->free();*/

  $output = $parser->recursiveTagParse($input);

  //Store in database - not needed for now, might be if/when bestof feature is integrated
  /*$dbw = wfGetDB(DB_MASTER);
  $dbw->replace('wigotext','vote_id',array('vote_id' => $voteid, 'text' => $output),__METHOD__);*/

  //parse magic only, to allow plural
  wfLoadExtensionMessages('wigo3');
  $totalvotes = wfMsgExt('wigovotestotal',array('parsemag'),array($countvotes));

  $jsVoteId = Xml::encodeJsVar( $voteid );
  $htmlVoteId = htmlspecialchars( $voteid );

  # script to get my vote
  if (!$bulkmode) {
  $myvotescript = "<script type=\"text/javascript\">" .
                  "sajax_do_call('wigogetmyvotes',[$jsVoteId],function (req) {" .
                      "if (req.readyState == 4) if (req.status == 200)" .
                      "{".
                      	"var res = eval('(' + req.responseText + ')');" .
                      	"if ( res[$jsVoteId]!== false ) {" .
                      		"c = document.getElementById(\"checkbox-input-\" + $jsVoteId);" .
                      	  "c.checked=(res[$jsVoteId] == 1);" .
                      	"}" .
                      "}" .
                  "});"  . "</script>";
	}
	
  wfLoadExtensionMessages('slider');
  if (array_key_exists('closed',$args) && strcasecmp($args['closed'],"yes") === 0) {
  	return "<input type=\"checkbox\" class=\"checkbox-input\" id=\"checkbox-input-{$htmlVoteId}\" name=\"checkbox-input-{$htmlVoteId}\" disabled=\"disabled\"" . ($myvote === 1 ? " checked=\"checked\"" : "") . "/> " .
           "<label for=\"checkbox-input-{$htmlVoteId}\">$output</label> (<span id=\"{$htmlVoteId}\" title=\"{$totalvotes}\">{$votes}</span>)" .
            $myvotescript;
  } else {
    	return "<input type=\"checkbox\" class=\"checkbox-input\" id=\"checkbox-input-{$htmlVoteId}\" name=\"checkbox-input-{$htmlVoteId}\"" . ($myvote === 1 ? " checked=\"checked\"" : "") . "/> " .
    			   "<label for=\"checkbox-input-{$htmlVoteId}\">$output</label> (<span id=\"{$htmlVoteId}\" title=\"{$totalvotes}\">{$votes}</span>)" .
    			   ($bulkmode ? "" : "  <a class=\"votebutton\" href=\"javascript:wigovotesend($jsVoteId,document.getElementById('checkbox-input-' + $jsVoteId).checked?1:0,0,1,true)\" title=\"" . wfMsg("slider-votetitle") . "\">" . wfMsg("slider-votebutton") . "</a>") .
             $myvotescript;
  }
}
