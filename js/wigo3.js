
( function ( $, mw ) {

function wigo_ajax(name, args, callback) {
  $.ajax( mw.util.wikiScript(), { data: {
      action: 'ajax',
      rs: name,
      rsargs: args
  } } )
  .done( function ( data, textStatus, jqXHR  ) {
    callback( jqXHR );
  } );
}

//kept for compatibility
function wigoupdate(req,avg)
{
  if (req.readyState == 4 && req.status == 200)
  {
    arr = req.responseText.split(':');
    span = document.getElementById(arr[0]);
    if (span)
    {
      origvote = parseInt(span.innerHTML,10);
      if (avg != null && avg) {
        newvote = Math.round((arr[1]/arr[2])*100)/100;
      } else {
        newvote = arr[1];
      }
      if (origvote != newvote || span.title != arr[3]) {
        span.innerHTML = newvote;
        span.title = arr[3];
        wigoinvalidate();
      }
    }
  } else {
    alert('An error occured: ' + req.responseText);
  }
}

//for new up-down voting
//ajax return value is in the form of "$pollid:$plus:$minus:$zero:$totalvotes:$totaltooltip:$distribtitle:$myvote:$result"
function wigoupdate2(req)
{
  if (req.readyState == 4 && req.status == 200)
  {
    arr = req.responseText.split(':');
    span = document.getElementById(arr[0]);
    if (span)
    {
      origvote = parseInt(span.innerHTML,10);
      plus = arr[1];
      minus = arr[2];
      zero = arr[3];
      newvote = plus - minus;
      newtotal = plus + minus + zero;
      newtotalvotes = arr[4];
      newtotaltooltip = arr[5];
      newdistribtitle = arr[6];
      myvote = arr[7];

      upbutton = document.getElementById(arr[0] + "-up");
      neutralbutton = document.getElementById(arr[0] + "-neutral");
      downbutton = document.getElementById(arr[0] + "-down");
 
      if (upbutton && upbutton.style.display=='none' ) {
        upbutton.style.display='';
      }
        
      if (neutralbutton && neutralbutton.style.display=='none' ) {
        neutralbutton.style.display='';
      }
      
      if (downbutton && downbutton.style.display=='none' ) {
        downbutton.style.display='';
      }
 
      if (origvote != newvote || span.title != newtotaltooltip) {
        span.innerHTML = newvote;
        span.title = newtotaltooltip;
        //update buttons

        //up
        if (upbutton) {
          if (myvote == 1) {
            //add class
            if (!upbutton.className || upbutton.className == "") {
              upbutton.className = "myvotebutton";
            } else {
              upbutton.className += " myvotebutton";
            }
          } else {
            //remove class
            if (upbutton.className.match(/(\s*|^)myvotebutton(\s*|$)/)) {
              upbutton.className = upbutton.className.replace(/(\s*|^)myvotebutton(\s*|$)/g,' ')
            }
          }
        }
        //neutral
        if (neutralbutton) {
          if (myvote == 0) {
            //add class
            if (!neutralbutton.className || neutralbutton.className == "") {
              neutralbutton.className = "myvotebutton";
            } else {
              neutralbutton.className += " myvotebutton";
            }
          } else {
            //remove class
            if (neutralbutton.className.match(/(\s*|^)myvotebutton(\s*|$)/)) {
              neutralbutton.className = neutralbutton.className.replace(/(\s*|^)myvotebutton(\s*|$)/g,' ')
            }
          }
        }
        //down
        if (downbutton) {
          if (myvote == -1) {
            //add class
            if (!downbutton.className || downbutton.className == "") {
              downbutton.className = "myvotebutton";
            } else {
              downbutton.className += " myvotebutton";
            }
          } else {
            //remove class
            if (downbutton.className.match(/(\s*|^)myvotebutton(\s*|$)/)) {
              downbutton.className = downbutton.className.replace(/(\s*|^)myvotebutton(\s*|$)/g,' ')
            }
          }
        }
        //update distribution bar
        distribtable = document.getElementById(arr[0] + "-dist");
        if (distribtable) {
          distribtable.title = newdistribtitle;
          //calculate percentages
          if (newtotalvotes != 0) {
            uppercent = (plus / newtotalvotes) * 100;
            downpercent = (minus / newtotalvotes) * 100;
            neutralpercent = (zero / newtotalvotes) * 100;
          } else {
            uppercent = 0;
            downpercent = 0;
            neutralpercent = 0;
          }
          if (uppercent != 0) {
            uppercent += "%";
          }
          if (neutralpercent != 0) {
            neutralpercent += "%";
          }
          if (downpercent != 0) {
            downpercent += "%";
          }
          //update widths
          tr = distribtable.getElementsByTagName("tr")[0]
          //tr.style.display = "";
          tds = tr.getElementsByTagName("td");
          tds[0].style.display = "";
          tds[1].style.display = "";
          tds[2].style.display = "";
          tds[0].style.width = uppercent;
          tds[1].style.width = neutralpercent;
          tds[2].style.width = downpercent;
        }
        wigoinvalidate();
      }
    }
  } else {
    alert('An error occured: ' + req.responseText);
  }
}

function wigoinvalidated(req)
{
  if (req.readyState == 4 && req.status == 200)
  {
    if (req.responseText.substring(0,2) != "ok") {
      alert('An error occured while invalidating the cache' + req.responseText);
    }
  } else {
    alert('An error occured: ' + req.responseText);
  }

}

function wigoinvalidate() {
  wigo_ajax('wigoinvalidate',[wgPageName],wigoinvalidated);
}

function wigovoteup(voteid)
{
  button = document.getElementById(voteid+'-up');
  button.style.display = 'none';
  wigo_ajax('wigovote2',[voteid,1],wigoupdate2);
}

function wigovotedown(voteid)
{
  button = document.getElementById(voteid+'-down');
  button.style.display = 'none';
  wigo_ajax('wigovote2',[voteid,-1],wigoupdate2);
}

function wigovotereset(voteid)
{
  button = document.getElementById(voteid+'-neutral');
  button.style.display = 'none';
  wigo_ajax('wigovote2',[voteid,0],wigoupdate2);
}

function wigoupdateavg(req)
{
  wigoupdate(req,true);
}

function wigovotesend(voteid,val,min,max,total)
{
  if (val < min || val > max) {
    alert('Invalid value');
  } else {
        if (total != null && total) {
          wigo_ajax('wigovote',[voteid,val,min,max],wigoupdate);
        } else {
        wigo_ajax('wigovote',[voteid,val,min,max],wigoupdateavg);
      }
  }
}

function wigoupdatearray(req,avg)
{
  if (req.readyState == 4 && req.status == 200)
  {
    var invalidate = false;
        var res = eval('(' + req.responseText + ')');
        for (voteid in res) {
            span = document.getElementById(voteid);
            arr = res[voteid];
        if (span)
        {
          origvote = parseInt(span.innerHTML,10);
          if (avg != null && avg) {
            newvote = Math.round((arr[0]/arr[1])*100)/100;
          } else {
            newvote = arr[0];
          }
          if (origvote != newvote || span.title != arr[2]) {
            span.innerHTML = newvote;
            span.title = arr[2];
            invalidate = true;
          }
        }			
        }
        if (invalidate) wigoinvalidate();
  } else {
    alert('An error occured: ' + req.responseText);
  }
}

function wigoupdatearrayavg(req)
{
  wigoupdatearray(req,true);
}

function wigovotesendarray(a,min,max,total)
{
  var params = new Array(min,max);
  for (voteid in a) {
    params.push(voteid, a[voteid])
  }
  if (total != null && total) {
    wigo_ajax('wigovotebatch',params,wigoupdatearray);
  } else {
    wigo_ajax('wigovotebatch',params,wigoupdatearrayavg);
  }
}

mw.wigo = {
  voteup: wigovoteup,
  votereset: wigovotereset,
  votedown: wigovotedown,
};

} )( jQuery, mediaWiki )
