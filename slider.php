<?php
#include ("/home/rationa1/public_html/bar.php");

// Extension credits that show up on Special:Version
$wgExtensionCredits['parserhook'][] = array(
        'name' => 'Generic slider',
        'author' => '[http://rationalwiki.com/wiki/User:Nx Nx]',
        'url' => 'http://rationalwiki.com/',
        'description' => 'Creates a slider for voting. Requires the wigo extension'
);

$wgHooks['ParserFirstCallInit'][] = 'sliderinit';

//$wgHooks['BeforePageDisplay'][] = 'slideraddjscss';

$wgSliderIP = dirname( __FILE__ );
$wgExtensionMessagesFiles['slider'] = "$wgSliderIP/slider.i18n.php";

function sliderinit( &$parser ) {
  $parser->setHook('slider','sliderrender');
  $parser->setHook('sliders','slidersrender');
  return true;    
}

function slideraddjscss(&$out, &$sk) 
{
  global $wgJsMimeType, $wgScriptPath;
  /*$out->addScript("<script type=\"{$wgJsMimeType}\" src=\"{$wgScriptPath}/extensions/slider/js/range.js\"></script>");
  $out->addScript("<script type=\"{$wgJsMimeType}\" src=\"{$wgScriptPath}/extensions/slider/js/timer.js\"></script>");
  $out->addScript("<script type=\"{$wgJsMimeType}\" src=\"{$wgScriptPath}/extensions/slider/js/slider.js\"></script>");*/
  $out->addScript("<script type=\"{$wgJsMimeType}\" src=\"{$wgScriptPath}/extensions/wigo3/js/slider-combined.js\"></script>");
  $out->addStyle("{$wgScriptPath}/extensions/wigo3/css/rational/rational.css");
  //$out->addStyle("{$wgScriptPath}/extensions/wigo3/css/luna/luna.css");
  return true;
}

function slidersrender($input, $args, $parser)
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
  
  #avoid conflicts - sliders will add slider prefix
  $voteid = "set" . $voteid;
  
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
	
	//get minimum and maximum values if supplied
  if (array_key_exists('min',$args) && (intval($args['min'],10) !== 0 || $args['min'] == '0')) {
    $minvalue = intval( $args['min'] );
  } else {
    $minvalue = 0;
  }
  if (array_key_exists('max',$args) && (intval($args['max'],10) !== 0 || $args['max'] == '0')) {
    $maxvalue = intval( $args['max'] );
  } else {
    $maxvalue = 10;
  }
	
	//Get the slider set
	$list = wfMessage("sliders/{$set}")->text();
  $list = preg_replace("/\*/","",$list);
  $options = explode("\n",$list);
	
	$output = '<div class="sliderset">' . "<table class=\"slidervote\" cellspacing=\"2\" cellpadding=\"2\" border=\"0\">";
	$ids = array();
	foreach ($options as $option) {
		$parts = explode( ';', $option);
		if ( count($parts) >= 2 ) {
			$title = $parts[0];
			$id = $parts[1];
			$id = str_replace(' ','_',$id);
		} else {
			//someone forgot the id, generate one for them
			$title = $parts[0];
			$id = Sanitizer::escapeClass($parts[0]);
		}
		$optionId = "slider{$voteid}-slider-{$id}";
		$ids[] = Xml::encodeJsVar( $optionId );
		$output .= "<p><slider poll=\"" . htmlspecialchars( "{$voteid}-slider-{$id}" ) . " min={$minvalue} max={$maxvalue} closed=" . ($closed ? "yes" : "no") ." bulkmode=yes>" . htmlspecialchars( $title ) . "</slider></p>";
	}
	$output .= "</table></div>";

	//get all the votes in one request
	$ids_l = implode(",",$ids);
	$myvotesscript = "<script type=\"text/javascript\">" .
                  "sajax_do_call('wigogetmyvotes',[{$ids_l}],function (req) {" .
                      "if (req.readyState == 4) if (req.status == 200)" .
                      "{".
                      	"var res = eval('(' + req.responseText + ')');" .
                      	"for (voteid in res) {" .
                      	  "if (res[voteid]!==false) {" .
                      	    "s = document.getElementById(\"slider-input-\" + voteid);" .
                      	    "s.value=res[voteid]; s.onchange();" .
                      	  "}" .
                      	"}" .
                      "}" .
                  "});"  . "</script>";
	
	return $parser->recursiveTagParse($output) /*. $votebutton*/ . $myvotesscript;
}

function sliderrender($input, $args, $parser)
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
  
  //inject js and css
  global $wgJsMimeType, $wgScriptPath;  
  $parser->mOutput->addHeadItem("<script type=\"{$wgJsMimeType}\" src=\"{$wgScriptPath}/extensions/wigo3/js/wigo3.js\"></script>",'wigo3js');
  $parser->mOutput->addHeadItem("<script type=\"{$wgJsMimeType}\" src=\"{$wgScriptPath}/extensions/wigo3/js/slider-combined.js\"></script>",'sliderjs');
  global $wgOut;
  $wgOut->addStyle("{$wgScriptPath}/extensions/wigo3/css/rational/rational.css");
  
  //get minimum and maximum values if supplied
  if (array_key_exists('min',$args) && (intval($args['min'],10) !== 0 || $args['min'] == '0')) {
    $minvalue = intval( $args['min'] );
  } else {
    $minvalue = 0;
  }
  if (array_key_exists('max',$args) && (intval($args['max'],10) !== 0 || $args['max'] == '0')) {
    $maxvalue = intval( $args['max'] );
  } else {
    $maxvalue = 10;
  }
  
  if (array_key_exists('bulkmode',$args) && strcasecmp($args['bulkmode'],"yes") === 0) {
  	$bulkmode = true;
  } else {
  	$bulkmode = false;
  }

  #avoid hacking wigo votes
  $voteid = "slider" . $voteid;
  $dbr = wfGetDB(DB_SLAVE);
  $res = $dbr->select('wigovote','sum(vote), count(vote)',array('id' => $voteid, "vote >= {$minvalue}", "vote <= {$maxvalue}"),'sliderrender',array('GROUP BY' => 'id'));
  $votes = 0;
  $countvotes = 0;
  if ($row = $res->fetchRow())
  {
    $votes = $row['sum(vote)'];
    $countvotes = $row['count(vote)'];
  }
  $res->free();

  #get my vote
  $myvote = null;

  if ($countvotes != 0) {
    $voteaverage = round($votes/$countvotes,2);
  } else {
    $voteaverage = "no votes";
  }

  $output = $parser->recursiveTagParse($input);

  //parse magic only, to allow plural
  $totalvotes = wfMessage('wigovotestotal')->params($countvotes)->text();

  $jsVoteId = Xml::encodeJsVar( $voteid );
  $htmlVoteId = htmlspecialchars( $voteid );
  $htmlJsVoteId = htmlspecialchars( $jsVoteId );

  # script to get my vote
  if (!$bulkmode) {
  $myvotescript = 
                  "sajax_do_call('wigogetmyvotes',[$jsVoteId],function (req) {" .
                      "if (req.readyState == 4) if (req.status == 200)" .
                      "{".
                      	"var res = eval('(' + req.responseText + ')');" .
                      	"if ( res[$jsVoteId]!== false ) {" .
                      		"s = document.getElementById(\"slider-input-\" + $jsVoteId);" .
                      	  "s.value=res[$jsVoteId]; s.onchange();" .
                      	"}" .
                      "}" .
                  "});";
	}

  if (array_key_exists('closed',$args) && strcasecmp($args['closed'],"yes") === 0) {
    return ($bulkmode ? "" : "<table class=\"slidervote\" cellspacing=\"2\" cellpadding=\"2\" border=\"0\">") .
              "<tr>" .
                "<td>" .
                  "<!--$htmlVoteId-->$output" .
                "</td>" .
                "<td style=\"min-width:25px; text-align:center;\">" .
                  "<div class=\"slider\" id=\"slider-{$htmlVoteId}\"></div>" .
                "</td>" .
                "<td style=\"min-width:25px; text-align:center;\">" .
                	"<input class=\"slider-input\" id=\"slider-input-{$htmlVoteId}\" name=\"slider-input-{$htmlVoteId}\" size=\"1\" disabled=\"disabled\" maxlength=\"3\"" . ($myvote !== null ? "value=\"{$myvote}\"" : "") . "/> " .
                  "<span id=\"{$htmlVoteId}\" title=\"{$totalvotes}\">{$voteaverage}</span>" .
                "</td>" .
              "</tr>" .
            ($bulkmode ? "" : "</table>") .
            "<script type=\"text/javascript\">" .
              "var s = new Slider(document.getElementById(\"slider-\" + {$jsVoteId})," .
                                 "document.getElementById(\"slider-input-\" + {$jsVoteId}),'horizontal',true);" .
              ($minvalue !== null ? "s.setMinimum({$minvalue});" : "") .
              ($maxvalue !== null ? "s.setMaximum({$maxvalue});" : "") .
            $myvotescript . "</script>";
  } else {
    return ($bulkmode ? "" : "<table class=\"slidervote\" cellspacing=\"2\" cellpadding=\"2\" border=\"0\">") .
              "<tr>" .
                "<td>" .
                  "<!--$htmlVoteId-->$output" .
                "</td>" .
                "<td style=\"min-width:25px; text-align:center;\">" .
                  "<div class=\"slider\" id=\"slider-{$htmlVoteId}\"></div>" .
                "</td>" .
                "<td>" .
                  "<input class=\"slider-input\" id=\"slider-input-{$htmlVoteId}\" name=\"slider-input-{$htmlVoteId}\" size=\"1\" maxlength=\"3\"" . ($myvote !== null ? "value=\"{$myvote}\"" : "") . "/> " .
                  "<span id=\"{$htmlVoteId}\" title=\"{$totalvotes}\">{$voteaverage}</span> " .
                  /*Sliders still get their own vote buttons in bulk mode, we don't want to force anyone to vote on everything if they don't want to*/
                "</td>" .
                "<td>" .
                  "<a class=\"votebutton\" href=\"javascript:wigovotesend($htmlJsVoteId,document.getElementById('slider-input-' + $htmlJsVoteId).value,$minvalue,$maxvalue)\" title=\"" . wfMessage("slider-votetitle")->escaped() . "\">" . wfMessage("slider-votebutton")->escaped() . "</a>" .
                "</td>" .
              "</tr>" .
            ($bulkmode ? "" : "</table>") . 
            "<script type=\"text/javascript\">" .
              "var s = new Slider(document.getElementById(\"slider-\" + $jsVoteId)," .
                                 "document.getElementById(\"slider-input-\" + $jsVoteId));" .
              ($minvalue !== null ? "s.setMinimum({$minvalue});" : "") .
              ($maxvalue !== null ? "s.setMaximum({$maxvalue});" : "") .
            $myvotescript . "</script>";
  }
}

