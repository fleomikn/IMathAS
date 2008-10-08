<?php
//IMathAS:  Main admin page
//(c) 2006 David Lippman

/*** master php includes *******/
require("../validate.php");

/*** pre-html data manipulation, including function code *******/
 
 //set some page specific variables and counters
$overwriteBody = 0;
$body = "";
$page_hasSearchResults = 0;
$pagetitle = $installname . " Question Export";

 
//data manipulation here
$isadmin = false;
$isgrpadmin = false; 

	//CHECK PERMISSIONS AND SET FLAGS
if (!(isset($teacherid)) && $myrights<75) {
 	$overwriteBody = 1;
	$body = "You need to log in as a teacher to access this page";
} elseif (isset($_GET['cid']) && $_GET['cid']=="admin" && $myrights <75) {
 	$overwriteBody = 1;
	$body = "You need to log in as an admin to access this page";
} elseif (!(isset($_GET['cid'])) && $myrights < 75) {
 	$overwriteBody = 1;
	$body = "Please access this page from the menu links only.";		
} else {	
	
	$cid = (isset($_GET['cid'])) ? $_GET['cid'] : "admin" ;

	if ($myrights < 100) {
		$isgrpadmin = true;
	} else if ($myrights == 100) {
		$isadmin = true;
	}
	
	if ($isadmin || $isgrpadmin) {
		$curBreadcrumb =  "<div class=breadcrumb>$breadcrumbbase <a href=\"admin.php\">Admin</a> &gt; Export Question Set</div>\n";
	} else {
		$curBreadcrumb =  "<div class=breadcrumb>$breadcrumbbase <a href=\"../course/course.php?cid=$cid\">$coursename</a> &gt; Export Question Set</div>\n";
	}
	

	
	//remember search, USED FOR ALL STEPS
	if (isset($_POST['search'])) {
		$safesearch = $_POST['search'];
		$search = stripslashes($safesearch);
		$search = str_replace('"','&quot;',$search);
		$sessiondata['lastsearch'] = str_replace(" ","+",$safesearch);
		writesessiondata();
	} else if (isset($sessiondata['lastsearch'])) {
		$safesearch = str_replace("+"," ",$sessiondata['lastsearch']);
		$search = stripslashes($safesearch);
		$search = str_replace('"','&quot;',$search);
	} else {
		$search = '';
	}
	if (isset($_POST['libs'])) {
		if ($_POST['libs']=='') {
			$_POST['libs'] = '0';
		}
		$searchlibs = $_POST['libs'];
		//$sessiondata['lastsearchlibs'] = implode(",",$searchlibs);
		$sessiondata['lastsearchlibs'] = $searchlibs;
		writesessiondata();
	} else if (isset($sessiondata['lastsearchlibs'])) {
		//$searchlibs = explode(",",$sessiondata['lastsearchlibs']);
		$searchlibs = $sessiondata['lastsearchlibs'];
	} else {
		$searchlibs = '0';
	}
	
	//get list of items already checked for export
	//USED FOR STEP 2
	$checked = array_merge((array)$_POST['pchecked'],(array)$_POST['nchecked']);
	$clist = "'".implode("','",$checked)."'";
	$now = time();
	
	$query = "SELECT id,description,qtype FROM imas_questionset WHERE id IN ($clist)";
	$result = mysql_query($query) or die("Query failed : " . mysql_error());
	$i=0;
	$page_pChecked = array();
	
	while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$page_pChecked[$i]['id'] = $line['id'];
		$page_pChecked[$i]['description'] = $line['description'];
		$page_pChecked[$i]['qtype'] = $line['qtype'];
		$i++;
	}	
	
	//GRAB LIST OF LIBS/QUESTIONS, USED IN STEP 1 AND 2
	$llist = "'".implode("','",explode(',',$searchlibs))."'";
	
	if (substr($searchlibs,0,1)=="0") 	
		$lnames[] = "Unassigned";
	
	$query = "SELECT name FROM imas_libraries WHERE id IN ($llist)";
	$result = mysql_query($query) or die("Query failed : " . mysql_error());
	while ($row = mysql_fetch_row($result)) {
		$lnames[] = $row[0];
	}
	$lnames = implode(", ",$lnames);
	
	if (isset($search)) {
		if ($isadmin) {
			$query = "SELECT DISTINCT imas_questionset.id,imas_questionset.description,imas_questionset.qtype ";
			$query .= "FROM imas_questionset,imas_library_items WHERE imas_questionset.description LIKE '%$safesearch%' ";
			$query .= "AND imas_library_items.qsetid=imas_questionset.id AND imas_library_items.libid IN ($llist)";
		} else if ($isgrpadmin) {
			$query = "SELECT DISTINCT imas_questionset.id,imas_questionset.description,imas_questionset.qtype ";
			$query .= "FROM imas_questionset,imas_library_items,imas_users WHERE imas_questionset.description LIKE '%$safesearch%' ";
			$query .= "AND imas_questionset.ownerid=imas_users.id ";
			$query .= "AND (imas_users.groupid='$groupid' OR imas_questionset.userights>0) "; 
			$query .= "AND imas_library_items.qsetid=imas_questionset.id AND imas_library_items.libid IN ($llist)";
		} else {
			$query = "SELECT DISTINCT imas_questionset.id,imas_questionset.description,imas_questionset.qtype ";
			$query .= "FROM imas_questionset,imas_library_items WHERE imas_questionset.description LIKE '%$safesearch%' ";
			$query .= "AND (imas_questionset.ownerid='$userid' OR imas_questionset.userights>0) "; 
			$query .= "AND imas_library_items.qsetid=imas_questionset.id AND imas_library_items.libid IN ($llist)";
		}
		
		if (count($checked)>0) { $query .= "AND imas_questionset.id NOT IN ($clist);"; }
		
		$result = mysql_query($query) or die("Query failed : $query" . mysql_error());
		
		if (mysql_num_rows($result) != 0) {
			$page_hasSearchResults = 1;		
			$i=0;
			$page_nChecked = array();
	
			while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
				$page_nChecked[$i]['id'] = $line['id'];
				$page_nChecked[$i]['description'] = $line['description'];
				$page_nChecked[$i]['qtype'] = $line['qtype'];
				$i++;
			}	
		}
	}

	
	
	//output export file here
	if (isset($_POST['export'])) {
		header('Content-type: text/imas');
		header("Content-Disposition: attachment; filename=\"imasexport.imas\"");
		echo "PACKAGE DESCRIPTION\n";
		echo $_POST['libdescription'];
		echo "\n";
		echo "\nSTART LIBRARY\nID\n1\nUID\n0\nLASTMODDATE\n$now\nNAME\n{$_POST['libname']}\nPARENT\n0\n";
		$qsetlist = implode(',',range(0,count($checked)-1));
		echo "\nSTART LIBRARY ITEMS\nLIBID\n1\nQSETIDS\n$qsetlist\n";
		$query = "SELECT * FROM imas_questionset WHERE id IN ($clist)";
		$result = mysql_query($query) or die("Query failed : " . mysql_error());
		$qcnt = 0;
		while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
			echo "\nSTART QUESTION\n";
			echo "QID\n";
			echo "$qcnt\n";
			$qcnt++;
			echo "\nUQID\n";
			echo rtrim($line['uniqueid']) . "\n";
			echo "\nLASTMOD\n";
			echo rtrim($line['lastmoddate']) . "\n";
			echo "\nDESCRIPTION\n";
			echo rtrim($line['description']) . "\n";
			echo "\nAUTHOR\n";
			echo rtrim($line['author']) . "\n";
			echo "\nCONTROL\n";
			echo rtrim($line['control']) . "\n";
			echo "\nQCONTROL\n";
			echo rtrim($line['qcontrol']) . "\n";
			echo "\nQTYPE\n";
			echo rtrim($line['qtype']) . "\n";
			echo "\nQTEXT\n";
			echo rtrim($line['qtext']) . "\n";
			echo "\nANSWER\n";
			echo rtrim($line['answer']) . "\n";
		}
		exit;
	}
}	

/******* begin html output ********/
require("../header.php");

if ($overwriteBody==1) {
	echo $body;
} else {
?>

<script type="text/javascript">
function chkAll(frm, arr, mark) {
  for (i = 0; i <= frm.elements.length; i++) {
   try{
     if(frm.elements[i].name == arr) {
       frm.elements[i].checked = mark;
     }
   } catch(er) {}
  }
}

var curlibs = '<?php echo $searchlibs ?>';
function libselect() {
	window.open('../course/libtree.php?libtree=popup&cid=<?php echo $cid ?>&libs='+curlibs,'libtree','width=400,height='+(.7*screen.height)+',scrollbars=1,resizable=1,status=1,top=20,left='+(screen.width-420));
}
function setlib(libs) {
	document.getElementById("libs").value = libs;
	curlibs = libs;
}
function setlibnames(libn) {
	document.getElementById("libnames").innerHTML = libn;
}
</script>

<?php echo $curBreadcrumb; ?>
	
	<form method=post action="export.php?cid=<?php echo $cid ?>">	
	<h3>Questions Marked for Export</h3>
	
<?php	
	if (count($checked)==0) {
		echo "<p>No Questions currently marked for export</p>\n";
	} else {
?>	

		Check/Uncheck All: <input type="checkbox" name="ca" value="1" onClick="chkAll(this.form, 'pchecked[]', this.checked)" checked=checked>
		<table cellpadding=5 class=gb>
		<thead>
			<tr>
				<th></th><th>Description</th><th>Type</th>
			</tr>
		</thead>
		<tbody>
		
<?php		
		$alt = 0;
		for ($i=0;$i<count($page_pChecked);$i++) {
			if ($alt==0) {echo "			<tr class=even>"; $alt=1;} else {echo "			<tr class=odd>"; $alt=0;}		
?>	

				<td>
				<input type=checkbox name='pchecked[]' value='<?php echo $page_pChecked[$i]['id'] ?>' checked=checked>
				</td>
				<td><?php echo $page_pChecked[$i]['description'] ?></td>
				<td><?php echo $page_pChecked[$i]['qtype'] ?></td>
			</tr>
	
<?php
		} 
?>

		</tbody>
		</table>
		
<?php		
	}
	
	if (isset($_POST['finalize'])) { // step 2, initial list to export has already been posted
?>	
			<h3>Export Settings</h3>
			<span class=form>Library Description</span>
			<span class=formright><textarea name="libdescription" rows=4 cols=60>
				</textarea></span><br class=form>
			<span class=form>Library Name</span>
			<span class=formright><input type=text name="libname" size="40"/></span><br class=form>
			
			<div class=submit><input name="export" type=submit value="Export"></div>
			</form>
<?php
	} else { //step 1, initial load
?>		

		<h3>Potential Questions</h3>	
		In Libraries: <span id="libnames"><?php echo $lnames ?></span>
		<input type=hidden name="libs" id="libs"  value="<?php echo $searchlibs ?>">
		<input type=button value="Select Libraries" onClick="libselect()"> <br> 
		Search: <input type=text size=15 name=search value="<?php echo $search ?>">
		<input type=submit value="Update and Search">
		<input type=submit name="finalize" value="Finalize"><BR>
		
<?php
		if ($page_hasSearchResults==0) {
			echo "<p>No Questions matched search</p>\n";
		} else {
?>
		<script type="text/javascript" src="<?php echo $imasroot ?>/javascript/tablesorter.js"></script>
		Check/Uncheck All: <input type="checkbox" name="ca2" value="1" onClick="chkAll(this.form, 'nchecked[]', this.checked)">
		<table cellpadding=5 id=myTable class=gb>
		<thead>
			<tr><th></th><th>Description</th><th>Type</th></tr>
		</thead>
		<tbody>
		
		
<?php		
			$alt = 0;
			for ($i=0;$i<count($page_nChecked);$i++) {
				if ($alt==0) {echo "			<tr class=even>"; $alt=1;} else {echo "			<tr class=odd>"; $alt=0;}		
?>	

				<td>
				<input type=checkbox name='pchecked[]' value='<?php echo $page_nChecked[$i]['id'] ?>'>
				</td>
				<td><?php echo $page_nChecked[$i]['description'] ?></td>
				<td><?php echo $page_nChecked[$i]['qtype'] ?></td>
			</tr>
	
<?php
			} 
?>		
		
		</tbody></table>
				
		<script type="text/javascript">
			initSortTable('myTable',Array(false,'S','S'),true);
		</script>
		
<?php		
		}
		echo "<p>Note: Export of questions with static image files is not yet supported</p>";
	}
	echo "</form>";
}	
	require("../footer.php");
?>
