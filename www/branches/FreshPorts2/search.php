<?
	# $Id: search.php,v 1.1.2.9 2002-03-19 12:56:39 dan Exp $
	#
	# Copyright (c) 1998-2001 DVL Software Limited

	require("./include/common.php");
	require("./include/freshports.php");
	require("./include/databaselogin.php");
	require("./include/getvalues.php");

	require("../classes/ports.php");

	freshports_Start("Search",
					"freshports - new ports, applications",
					"FreeBSD, index, applications, ports");

	$Debug = 0;
?>
<TABLE WIDTH="<? echo $TableWidth; ?>" BORDER="0" ALIGN="center">
<tr><td valign="top" width="100%">                    
<table width="100%" border="0">                       
  <tr>
	<? freshports_PageBannerText("Search"); ?>
  </tr>
<tr><td valign="top">
<tr><td>
<?

if ($Debug) {
	echo "$query && $stype && $num && $method\n<BR>";

	if ($query && $stype && $num) {
		echo "yes, we have parameters\n<BR>";
	}
}

#
# we can take parameters.  if so, make it look like a post
#
if (!$search && ($query && $stype && $num && $method)) {
	$search = TRUE;
}

if ($search) {

	if ($Debug) echo "into search stuff<BR>\n";

/*
   while (list($name, $value) = each($HTTP_POST_VARS)) {
      echo "$name = $value<br>\n";
   }

   echo "you submitted<br>\n";
*/

$logfile = $DOCUMENT_ROOT . "/../configuration/searchlog.txt";

	// avoid nasty problems by adding slashes
	$query		= AddSlashes($query);
	$stype		= AddSlashes($stype);
	$num		= AddSlashes($num);
	$category	= AddSlashes($category);
	$port		= AddSlashes($port);

$fp = fopen($logfile, "a");
if ($fp) {
	if ($method == 'match') {
		fwrite($fp, date("Y-m-d H:i:s") . " " . $stype    . ':' . $query . "\n");
	} else {
		fwrite($fp, date("Y-m-d H:i:s") . " " . $category . '/' . $port  . "\n");
	}
	fclose($fp);
} else {
	print "Please let postmaster@freshports.org know that the search log could not be opened.  This does not affect the search results.\n";
	define_syslog_variables();
	syslog(LOG_ERR, "FreshPorts could not open the search log file: $logfile");
}

$sql = "select ports.id, element.name as port, " .
       "categories.name as category, categories.id as category_id, ports.version as version, ".
       "ports.maintainer, ports.short_description, ".
       "ports.package_exists, ports.extract_suffix, ports.homepage, element.status, ports.element_id, " .
       "ports.broken, ports.forbidden ";

if ($WatchListID) {
    $sql .= ",
       CASE when watch_list_element.element_id is null
          then 0
          else 1
       END as onwatchlist ";
}

	$sql .= "from ports, categories, element  ";

if ($WatchListID) {
    $sql .="
            left outer join watch_list_element
            on element.id                       = watch_list_element.element_id
           and watch_list_element.watch_list_id = $WatchListID ";
}




	$sql .= "WHERE ports.category_id = categories.id " .
	        "  and ports.element_id  = element.id " ;

if ($method == 'match') {
	switch ($stype) {
		case "name":
			$sql .= "and element.name like '%$query%'";
			break;

		case "shortdescription":
			$sql .= "and ports.short_description like '%$query%'";
			break;
      
		case "maintainer":
			$sql .= "and ports.maintainer like '%$query%'";
			break;
	}
} else {
	switch ($stype) {
		case "name":
			$sql .= "and levenshtein(element.name, '$query') < 4";
			break;

		case "shortdescription":
			$sql .= "and evenshtein(ports.short_description, '$query') < 4";
			break;
      
		case "maintainer":
			$sql .= "and evenshtein(ports.maintainer, '$query') < 4";
			break;
	}
}

$sql .= " order by categories.name, element.name";

if ($num < 1 or $num > 500) {
	$num = 10;
}

$sql .= " limit $num";

$AddRemoveExtra  = "&&origin=$SCRIPT_NAME?query=" . $query. "+stype=$stype+num=$num+method=$method";
if ($Debug) echo "\$AddRemoveExtra = '$AddRemoveExtra'\n<BR>";
$AddRemoveExtra = AddSlashes($AddRemoveExtra);
if ($Debug) echo "\$AddRemoveExtra = '$AddRemoveExtra'\n<BR>";

if ($Debug) {
	echo "$sql<br>\n";

#	print "now exitting....";
#	exit;
}


$result  = pg_exec($db, $sql);
if (!$result) {
	echo pg_errormessage() . $sql;
	exit;
}
$NumRows = pg_numrows($result);

$Port = new Port($db);
$Port->LocalResult = $result;

}
?>
<form METHOD="POST" ACTION="<? echo $PHP_SELF ?>">
Search for:<BR>
	<SELECT NAME="stype" size="1">
		<option VALUE="name"             <? if ($stype == "name")             echo 'SELECTED'?>>Port Name</option>
		<option VALUE="maintainer"       <? if ($stype == "maintainer")       echo 'SELECTED'?>>Maintainer</option>
		<option VALUE="shortdescription" <? if ($stype == "shortdescription") echo 'SELECTED'?>>Short Description</option>
	</SELECT> 

	<SELECT name=method>
		<OPTION value="match"   <?if ($method == "match"  ) echo 'SELECTED' ?>>containing
		<OPTION value="soundex" <?if ($method == "soundex") echo 'SELECTED' ?>>sounding like
	</SELECT>

	<input NAME="query" size="20"  value="<? echo stripslashes($query)?>">

	<SELECT name=num>
		<option value="10"  <?if ($num == 10)  echo 'SELECTED' ?>>10 results
		<option value="20"  <?if ($num == 20)  echo 'SELECTED' ?>>20 results
		<option value="30"  <?if ($num == 30)  echo 'SELECTED' ?>>30 results
		<option value="50"  <?if ($num == 50)  echo 'SELECTED' ?>>50 results
		<option value="100" <?if ($num == 100) echo 'SELECTED' ?>>100 results
		<option value="500" <?if ($num == 500) echo 'SELECTED' ?>>500 results
	</SELECT> 

	<input TYPE="submit" VALUE="search"> </p>
  <input type="hidden" name="search" value="1">
</form>

</td></tr>
<?
if ($search) {
echo "<tr><td>\n";
if ($NumRows == 0) {
   $HTML .= " no results found<br>\n";
} else {

//   echo "retrieving $NumRows rows<br>\n";

	$HTML .= "Number of ports found: $NumRows<BR>";

$ShowCategories		= 1;
GLOBAL	$ShowDepends;
$ShowDepends		= 1;
$DaysMarkedAsNew= $DaysMarkedAsNew= $GlobalHideLastChange= $ShowChangesLink= $ShowDescriptionLink= $ShowDownloadPortLink= $ShowHomepageLink= $ShowLastChange= $ShowMaintainedBy= $ShowPortCreationDate= $ShowPackageLink= $ShowShortDescription =1;
$HideDescription = 1;
$ShowEverything  = 1;
$ShowShortDescription = "Y";
$ShowMaintainedBy     = "Y";
$GlobalHideLastChange = "Y";
$ShowDescriptionLink  = "N";

	for ($i = 0; $i < $NumRows; $i++) {
		$Port->FetchNth($i);
		$HTML .= freshports_PortDetails($Port, $Port->dbh, $DaysMarkedAsNew, $DaysMarkedAsNew, $GlobalHideLastChange, $HideCategory, $HideDescription, $ShowChangesLink, $ShowDescriptionLink, $ShowDownloadPortLink, $ShowEverything, $ShowHomepageLink, $ShowLastChange, $ShowMaintainedBy, $ShowPortCreationDate, $ShowPackageLink, $ShowShortDescription, 1, $AddRemoveExtra);
   }

}

$HTML .= "Number of ports found: $NumRows<BR>";

echo $HTML;
echo "</td></tr>\n";
}
?>
</table>

</td>
  <td valign="top" width="*">
    <? include("./include/side-bars.php") ?>
 </td>
</tr>
</table>

<TABLE WIDTH="<? echo $TableWidth; ?>" BORDER="0" ALIGN="center">
<TR><TD>
<? include("./include/footer.php") ?>
</TD></TR>
</TABLE>

</body>
</html>
