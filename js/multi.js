/*<![CDATA[*/ 

function multiupdate(req,voteid,val,count)
{
  if (req.readyState == 4 && req.status == 200)
  {
    results = req.responseText.split(':');
    for (i=0, sum=0; i<results.length; sum+=parseInt(results[i++],10));
    invalidate = false;
    for (i=0; i<count; ++i) {
      span = document.getElementById(voteid + "-" + i + "-result");
      titlespan = document.getElementById(voteid + "-" + i);
      if (span) {
        //get number of votes
        currentvotes = parseInt(span.innerHTML,10);
        //update and invalidate if number of votes changed
        newvotes = parseInt(results[i],10);
        if (newvotes != currentvotes) {
          invalidate = true;
          span.innerHTML = newvotes;
        }  
        //calculate column width and update
        columndiv = document.getElementById(voteid + "-" + i + "-column");
        if (columndiv) {
          //removeSpinner(voteid+'-'+i);
          if (sum == 0) {
            percent = 0;
          } else {
            percent = newvotes/sum * 100;
          }
          oldwidth = columndiv.style.width;
          newwidth = percent + "%";
          if (oldwidth != newwidth) {
            invalidate = true;
            columndiv.style.width = newwidth;
          }
        }
        //add class and bolding if my vote
        if (i == val) {
          //add class
          if (!span.className || span.className == "") {
            span.className = "myvote";
          } else {
            span.className += " myvote";
          }
          span.style.fontWeight = "bold";
          if (!titlespan.className || titlespan.className == "") {
            titlespan.className = "myvote";
          } else {
            titlespan.className += " myvote";
          }
          titlespan.style.fontWeight = "bold";
        } else {
          //remove class and bolding
          span.style.fontWeight = "normal";
          if (span.className.match(/(\s*|^)myvote(\s*|$)/)) {
            span.className = span.className.replace(/(\s*|^)myvote(\s*|$)/g,' ')
          }
          titlespan.style.fontWeight = "normal";
          if (titlespan.className.match(/(\s*|^)myvote(\s*|$)/)) {
            titlespan.className = titlespan.className.replace(/(\s*|^)myvote(\s*|$)/g,' ')
          }
        }
      }
    }
    if (invalidate) { wigoinvalidate(); }
  } else {
    alert('An error occured: ' + req.responseText);
  }
}

function multivotesend(voteid,val,count)
{
  if (voteid.substr(0,5) != 'multi') {
    alert('Invalid vote id');
  } else {
    //removeSpinner(voteid+'-'+val);
    //injectSpinner(document.getElementById(voteid + '-' + val + '-column').parentNode.parentNode.previousSibling.firstChild,voteid+'-'+val);
    sajax_do_call('multivote',[voteid,val,count],function(req) { multiupdate(req,voteid,val,count); });
  }
}

/*]]>*/
