<?php
//IMathAS:  Handle options for print layout
//(c) 2006 David Lippman
	require("../validate.php");
	$pagetitle = "Print Layout";
	$placeinhead = "<link rel=\"stylesheet\" type=\"text/css\" href=\"$imasroot/assessment/mathtest.css\"/>\n";
	$placeinhead .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"$imasroot/assessment/print.css\"/>\n";
	$placeinhead .= "<script src=\"$imasroot/javascript/AMhelpers.js\" type=\"text/javascript\"></script>\n";
	require("../header.php");

	if (!(isset($teacherid))) {
		echo "You need to log in as a teacher to access this page";
		require("../footer.php");
		exit;
	}
	$cid = $_GET['cid'];
	$aid = $_GET['aid'];
	if (isset($_POST['vert'])) {
		$ph = 11 - $_POST['vert'];
		$pw = 8.5 - $_POST['horiz'];
		if ($_POST['browser']==1) {
			$ph -= .5;
			$pw -= .5;
		}
	} else if (isset ($_POST['pw'])) {
		$ph = $_POST['ph'];
		$pw = $_POST['pw'];
	}
	$isfinal = isset($_GET['final']);
	
	$query = "SELECT itemorder,shuffle,defpoints,name,intro FROM imas_assessments WHERE id='$aid'";
	$result = mysql_query($query) or die("Query failed : " . mysql_error());
	$line = mysql_fetch_array($result, MYSQL_ASSOC);
	
	$questions = explode(",",$line['itemorder']);
	foreach($questions as $k=>$q) {
		if (strpos($q,'~')!==false) {
			$sub = explode('~',$q);
			$questions[$k] = $sub[array_rand($sub,1)];
		}
	}
	//if ($line['shuffle']&1) {shuffle($questions);}
	
	
	
	$points = array();
	$qn = array();
	$qlist = "'".implode("','",$questions)."'";
	$query = "SELECT id,points,questionsetid FROM imas_questions WHERE id IN ($qlist)";
	$result = mysql_query($query) or die("Query failed : " . mysql_error());
	while ($row = mysql_fetch_row($result)) {
		if ($row[1]==9999) {
			$points[$row[0]] = $line['defpoints'];
		} else {
			$points[$row[0]] = $row[1];
		}
		$qn[$row[0]] = $row[2];
	}
	
	
	$numq = count($questions);
	$phs = $ph-0.6;
	$pws = $pw-0.5;
	$pwss = $pw-0.6;
	echo <<<END
<style type="text/css">
div.a,div.b {
  position: absolute;
  left: 0px;
  border: 1px solid;
  width: {$pwss}in;
  height: {$phs}in;
}
div.a {
  border: 3px double #33f;
}
div.b {
  border: 3px double #0c0;
}

END;
	if ($isfinal) {
		$heights = explode(',',$_POST['heights']);
		for ($i=0;$i<count($heights);$i++) {
			echo "div.trq$i {float: left; width: {$pw}in; height: {$heights[$i]}in; padding: 0px; overflow: hidden;}\n";
		}
		echo "div.hdrm {width: {$pw}in; padding: 0px; overflow: hidden;}\n";
	} else {
		$pt = 0;
		for ($i=0;$i<ceil($numq/3)+1;$i++) {
			echo "div#pg$i { top: {$pt}in;}\n";
			$pt+=$ph;
			if ($_POST['browser']==1) {$pt -= .4;}
		}
	}
	echo <<<END
div.floatl {
	float: left;
}
div.qnum {
	float: left;
	text-align: right;
	padding-right: 5px;
}
div#headerleft {
	float: left;
}
div#headerright {
	float: right;
	text-align: right;
}
div#intro {
	clear: both;
	padding-top: 5px;
	padding-bottom: 5px;
}
div.q {
	clear: both;
	padding: 0px;
	margin: 0px;
}
div.m {
	float: left;
	width: {$pws}in;
	border-bottom: 1px dashed #aaa;
	padding: 0px;
	overflow: hidden;
}

div.cbutn {
	float: left;
	padding-left: 5px;
}
body {
	padding: 0px;
	margin: 0px;
}
form {
	padding: 0px;
	margin: 0px;
}
div.maintest {
	position: absolute;
	top: 0px;
	left: 0px;
}
.pageb {
		clear: both;
		padding: 0px;
		margin: 0px;
		page-break-after: always;
		border-bottom: 1px dashed #aaa;
	}
div.mainbody {
	margin: 0px;
	padding: 0px;
}
</style>
<style type="text/css" media="print">

	div.a,div.b {
		display: none;
	}
	div.m {
		width: {$pw}in;
		border: 0px;
	}
	div.cbutn {
		display: none;
	}
	.pageb {
		border: 0px;
	}
	
</style>

END;
	if (!$isfinal) {
		for ($i=0;$i<ceil($numq/3)+1;$i++) { //print page layout divs
			echo "<div id=\"pg$i\" ";
			if ($i%2==0) {
				echo "class=a";
			} else {
				echo "class=b";
			}
			echo ">&nbsp;</div>\n";
		}
	}
	include("../assessment/displayq2.php");
	function printq($qn,$qsetid,$seed,$pts) {
		global $isfinal;
		srand($seed);
	
		$query = "SELECT qtype,control,qcontrol,qtext,answer FROM imas_questionset WHERE id='$qsetid'";
		$result = mysql_query($query) or die("Query failed : " . mysql_error());
		$qdata = mysql_fetch_array($result, MYSQL_ASSOC);
		
		eval(interpret('control',$qdata['qtype'],$qdata['control']));
		eval(interpret('qcontrol',$qdata['qtype'],$qdata['qcontrol']));
		$toevalqtxt = interpret('qtext',$qdata['qtype'],$qdata['qtext']);
		srand($seed+1);
		eval(interpret('answer',$qdata['qtype'],$qdata['answer']));
		srand($seed+2);
		$la = '';
		
		//pack options
		if (isset($ansprompt)) {$options['ansprompt'] = $ansprompt;}
		if (isset($displayformat)) {$options['displayformat'] = $displayformat;}
		if (isset($answerformat)) {$options['answerformat'] = $answerformat;}
		if (isset($questions)) {$options['questions'] = $questions;}
		if (isset($answers)) {$options['answers'] = $answers;}
		if (isset($answer)) {$options['answer'] = $answer;}
		if (isset($questiontitle)) {$options['questiontitle'] = $questiontitle;}
		if (isset($answertitle)) {$options['answertitle'] = $answertitle;}
		if (isset($answersize)) {$options['answersize'] = $answersize;}
		if (isset($variables)) {$options['variables'] = $variables;}
		if (isset($domain)) {$options['domain'] = $domain;}	
		if (isset($answerboxsize)) {$options['answerboxsize'] = $answerboxsize;}
		if (isset($hidepreview)) {$options['hidepreview'] = $hidepreview;}
		if (isset($matchlist)) {$options['matchlist'] = $matchlist;}
		if (isset($noshuffle)) {$options['noshuffle'] = $noshuffle;}
		
		if ($qdata['qtype']=="multipart") {
			if (!is_array($anstypes)) {
				$anstypes = explode(",",$anstypes);
			}
			$laparts = explode("&",$la);
			foreach ($anstypes as $kidx=>$anstype) {
				list($answerbox[$kidx],$tips[$kidx],$shans[$kidx]) = makeanswerbox($anstype,$kidx,$laparts[$kidx],$options,$qn+1);
			}
		} else {
			list($answerbox,$tips[0],$shans[0]) = makeanswerbox($qdata['qtype'],$qn,$la,$options,0);
		}
		
		echo "<div class=q>";
		if ($isfinal) {
			echo "<div class=\"trq$qn\">\n";
		} else {
			echo "<div class=m id=\"trq$qn\">\n";
		}
		echo "<div class=qnum>".($qn+1).") ";
		if (isset($_POST['points'])) {
			echo '<br/>'.$pts.'pts';
		}
		echo "</div>\n";//end qnum div
		echo "<div class=floatl><div>\n";
		//echo $toevalqtext;
		eval("\$evaledqtext = \"$toevalqtxt\";");
		echo filter($evaledqtext);
		echo "</div>\n"; //end question div
		
		if (strpos($toevalqtxt,'$answerbox')===false) {
			if (is_array($answerbox)) {
				foreach($answerbox as $iidx=>$abox) {
					echo "<div>$abox</div>\n";
					echo "<div class=spacer>&nbsp;</div>\n";
				}
			} else {  //one question only
				echo "<div>$answerbox</div>\n";
			}
			
			
		} 
		
		echo "</div>\n"; //end floatl div
		
		echo "</div>";//end m div
		if (!$isfinal) {
			echo "<div class=cbutn>\n";  
			echo "<p><input type=button value=\"+1\" onclick=\"incspace($qn,1)\"><input type=button value=\"+.5\" onclick=\"incspace($qn,.5)\"><input type=button value=\"+.25\" onclick=\"incspace($qn,.25)\"><input type=button value=\"+.1\" onclick=\"incspace($qn,.1)\"><br/>";
			echo "<input type=button value=\"-1\" onclick=\"incspace($qn,-1)\"><input type=button value=\"-.5\" onclick=\"incspace($qn,-.5)\"><input type=button value=\"-.25\" onclick=\"incspace($qn,-.25)\"><input type=button value=\"-.1\" onclick=\"incspace($qn,-.1)\"></p>";
			echo "</div>\n"; //end cbutn div
		}
		echo "&nbsp;";
		echo "</div>\n"; //end q div
		if (!isset($showanswer)) {
			return $shans;
		} else {
			return $showanswer;
		}
	}
	
	//echo "<div class=maintest>\n";
	echo "<form method=post action=\"printtest.php?cid=$cid&aid=$aid\" onSubmit=\"return packheights()\">\n";
	
	if ($isfinal) {
		$copies = $_POST['versions'];
	} else {
		$copies = 1;
	}
	for ($j=0; $j<$copies; $j++) {		
		$seeds = array();
		if ($line['shuffle']&2) {  //set rand seeds
			$seeds = array_fill(0,count($questions),rand(1,9999));	
		} else {
			for ($i = 0; $i<count($questions);$i++) {
				$seeds[] = rand(1,9999);
			}
		}
		
		$headerleft = '';
		if (isset($_POST['aname'])) {
			$headerleft .= $line['name'];
		}
		if ($copies>1) {
			$headerleft .= ' - Form ' . ($j+1);
		}
		if ((isset($_POST['iname']) || isset($_POST['cname'])) && isset($_POST['aname'])) {
			$headerleft .= "<br/>";
		}
		if (isset($_POST['cname'])) {
			$query = "SELECT name FROM imas_courses WHERE id=$cid";
			$result = mysql_query($query) or die("Query failed : " . mysql_error());
			$headerleft .= mysql_result($result,0,0);
			if (isset($_POST['iname'])) { $headerleft .= ' - ';}
		}
		if (isset($_POST['iname'])) {
			$query = "SELECT LastName FROM imas_users WHERE id=$userid";
			$result = mysql_query($query) or die("Query failed : " . mysql_error());
			$headerleft .= mysql_result($result,0,0);
		}
		$headerright = '';
		if (isset($_POST['sname'])) {
			$headerright .= 'Name ____________________________';
			if (isset($_POST['otherheader'])) {
				$headerright .= '<br/>';
			}
		}
		if (isset($_POST['otherheader'])) {
			$headerright .= $_POST['otherheadertext'] . '____________________________';
		}
		
		echo "<div class=q>\n";
		if ($isfinal) {
			echo "<div class=hdrm>\n";
		} else {
			echo "<div class=m>\n";
		}
		echo "<div id=headerleft>$headerleft</div><div id=headerright>$headerright</div>\n";
		echo "<div id=intro>{$line['intro']}</div>\n";
		echo "</div>\n";
		if (!$isfinal) {
			echo "<div class=cbutn><a href=\"printtest.php?cid=$cid&aid=$aid\">Cancel</a></div>\n";
		} 
		echo "</div>\n";
		
		
		for ($i=0; $i<$numq; $i++) {
			$sa[$j][$i] = printq($i,$qn[$questions[$i]],$seeds[$i],$points[$questions[$i]]);
		}
		if ($isfinal) {
			echo "<p class=pageb>&nbsp;</p>\n";
		}
	}
	//echo "</table>\n";
	if (!$isfinal) {
		echo <<<END
<script type="text/javascript">
var heights = new Array();
for (var i=0; i<$numq; i++) {
	heights[i] = 2.5;
	document.getElementById("trq"+i).style.height = "2.5in";
}
function incspace(id,sp) {
	if (heights[id]+sp>.5) {
		heights[id] += sp;
		document.getElementById("trq"+id).style.height = heights[id]+"in";
	} 
	
}
function packheights() {
	document.getElementById("heights").value = heights.join(",");
	return true;
}
</script>
END;
	
	
		echo "<input type=hidden id=heights name=heights value=\"\">\n";
		echo "<input type=hidden name=pw value=\"$pw\">\n";
		echo "<input type=hidden name=ph value=\"$ph\">\n";
		if (isset($_POST['points'])) {
			echo "<input type=hidden name=points value=1>\n";
		}
		if (isset($_POST['aname'])) {
			echo "<input type=hidden name=aname value=1>\n";
		}
		if (isset($_POST['iname'])) {
			echo "<input type=hidden name=iname value=1>\n";
		}
		if (isset($_POST['cname'])) {
			echo "<input type=hidden name=cname value=1>\n";
		}
		if (isset($_POST['sname'])) {
			echo "<input type=hidden name=sname value=1>\n";
		}
		if (isset($_POST['otherheader'])) {
			echo "<input type=hidden name=otherheader value=1>\n";
			echo "<input type=hidden name=otherheadertext value=\"{$_POST['otherheadertext']}\">\n";
		}
		echo "<div class=q><div class=m>&nbsp;</div><div class=cbutn><input type=submit value=\"Continue\"></div></div>\n";
	} else if ($_POST['keys']>0) { //print answer keys
		for ($j=0; $j<$copies; $j++) {
			echo '<b>Key - Form ' . ($j+1) . "</b>\n";
			echo "<ol>\n";
			for ($i=0; $i<$numq; $i++) {
				echo '<li>';
				if (is_array($sa[$j][$i])) {
					echo filter(implode(' ~ ',$sa[$j][$i]));
				} else {
					echo filter($sa[$j][$i]);
				}
				echo "</li>\n";
			}
			echo "</ol>\n";
			if ($_POST['keys']==2) {
				echo "<p class=pageb>&nbsp;</p>\n";
			}
		}
	}
	if ($isfinal) {
		echo "<div class=cbutn><a href=\"course.php?cid=$cid\">Return to course page</a></div>\n";
	}
	echo "</form>\n";
	//echo "</div>\n"; //end maintest div
	require("../footer.php");
?>
