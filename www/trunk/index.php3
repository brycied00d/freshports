<?
require( "./_private/commonlogin.php3");
require( "./_private/getvalues.php3");
require( "./_private/freshports.php3");

if (!$StartAt) {
   if ($Debug) {
      echo "setting StartAt to zero<br>\n";
   }
   $StartAt = 0;
} else {
   $NewStart = floor($StartAt / $MaxNumberOfPorts) * $MaxNumberOfPorts;
   if ($NewStart != $StartAt) {
      $URL = basename($PHP_SELF);
      if ($NewStart > 0) {
         $URL .= "?StartAt=$NewStart";
      } else {
         $URL = "/";
      }
      header("Location: " . $URL );
      // Make sure that code below does not get executed when we redirect.
      exit;
   }
}

if ($Debug) {
   echo "StartAt = $StartAt<br>\n";
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 3.2//EN">
<html>

<head>
<meta name="description" content="freshports - new ports, applications">
<meta name="keywords" content="FreeBSD, index, applications, ports">  
<!--// DVL Software is a New Zealand company specializing in database applications. //-->
<title>freshports</title>
</head>

<body bgcolor="#ffffff" link="#0000cc">
  <? include("./_private/header.inc") ?>
<table width="100%" border="0">
<tr><td colspan="2">Welcome to the freshports.org where you can find the latest information on your favourite
ports.
</td></tr>
  <tr>
    <td colspan="2">Note: <font size="-1">[refresh]</font> indicates a port for which the Makefile, 
                  pkg/DESC, or pkg/COMMENT has changed and has not yet been updated within FreshPorts.
    </td>
  </tr>
<tr><td valign="top" width="100%">
<table width="100%" border="0">
<tr>
    <td colspan="5" bgcolor="#AD0040" height="30">
        <font color="#FFFFFF" size="+1">freshports - most recent commits
        <? echo ($StartAt + 1) . " - " . ($StartAt + $MaxNumberOfPorts) ?></font></td>
  </tr>
<tr>
<script language="php">

$DESC_URL = "ftp://ftp.freebsd.org/pub/FreeBSD/branches/-current/ports";

// make sure the value for $sort is valid

switch ($sort) {
/* sorting by port is disabled. Doesn't make sense to do this
   case "port":
      $sort = "version, updated desc";
      $cache_file .= ".port";
      break;
*/
//   case "updated":
//      $sort = "updated desc, port";
//      break;

   default:
      $sort ="updated desc, category, version";
      $cache_file .= ".updated";
}

$cache_file .= "." . $StartAt;

srand((double)microtime()*1000000);
$cache_time_rnd =       300 - rand(0, 600);

//$Debug=1;
if ($Debug) {
echo '<br>';
echo '$cache_file=', $cache_file, '<br>';
echo '$LastUpdateFile=', $LastUpdateFile , '<br>';
echo '!(file_exists($cache_file))=',     !(file_exists($cache_file)), '<br>';
echo '!(file_exists($LastUpdateFile))=', !(file_exists($LastUpdateFile)), "<br>";
echo 'filectime($cache_file)=',          filectime($cache_file), "<br>";
echo 'filectime($LastUpdateFile)=',      filectime($LastUpdateFile), "<br>";
echo '$cache_time_rnd=',                 $cache_time_rnd, '<br>';
echo 'filectime($cache_file) - filectime($LastUpdateFile) + $cache_time_rnd =', filectime($cache_file) - filectime($LastUpdateFile) + $cache_time_rnd, '<br>';
}

$UpdateCache = 0;
if (!file_exists($cache_file)) {
//   echo 'cache does not exist<br>';
   // cache does not exist, we create it
   $UpdateCache = 1;
} else {
//   echo 'cache exists<br>';
   if (!file_exists($LastUpdateFile)) {
      // no updates, so cache is fine.
//      echo 'but no update file<br>';
   } else {
//      echo 'cache file was ';
      // is the cache older than the db?
      if ((filectime($cache_file) + $cache_time_rnd) < filectime($LastUpdateFile)) {
//         echo 'created before the last database update<br>';
         $UpdateCache = 1;
      } else {
//         echo 'created after the last database update<br>';
      }
   }
}

$UpdateCache = 1;

if ($UpdateCache == 1) {
//   echo 'time to update the cache';

$sql = "select ports.id, ports.name as port, change_log.commit_date as updated, categories.name as category, " .
       "ports.committer, ports.last_update_description as update_description, " .
       "ports.maintainer, ports.short_description, UNIX_TIMESTAMP(ports.date_created) as date_created, " .
       "date_format(date_created, '$FormatDate $FormatTime') as date_created_formatted, ".
       "ports.package_exists, ports.extract_suffix, ports.needs_refresh, ports.homepage, ports.status, " .
       "date_format(change_log.commit_date, '$FormatDate $FormatTime') as updated, change_log.committer, " .
       "change_log.update_description, " .
       "ports.last_change_log_detail_id " .
       "from ports, categories, change_log, change_log_port  ".
       "WHERE ports.system = 'FreeBSD' ".
       "  and ports.primary_category_id       = categories.id " .
       "  and change_log_port.port_id         = ports.id " .
       "  and change_log.id                   = change_log_port.change_log_id ";

$sql .= " order by $sort ";

$sql .= " limit 20 ";

echo $sql;

$result = mysql_query($sql, $db);

if (!$result) {
   echo mysql_errno().": ".mysql_error()."<BR>";
}

$HTML = "</tr></td>";

$HTML .= '<tr><td>';

$i=0;
$GlobalHideLastChange = "N";
while ($myrow = mysql_fetch_array($result)) {
//   $HTML .= $i++;
   include("./_private/port-basics.inc");
//   $HTML .= $myrow["port"] . "<br>";
}

  $HTML .= "</td></tr>\n";


echo $HTML;

   $fpwrite = fopen($cache_file, 'w');
   if(!$fpwrite) {
      echo 'error on open<br>';
      echo "$errstr ($errno)<br>\n";
      exit;
   } else {
//      echo 'written<br>';
      fputs($fpwrite, $HTML);
      fclose($fpwrite);
   }

} else {
//   echo 'looks like I\'ll read from cache this time';
   if (file_exists($cache_file)) {
      include($cache_file);
   }
}

echo '<tr><td height="40" colspan="2" valign="bottom">';

if ($StartAt == 0) {
   echo 'Previous Page';
} else {
   echo '<a href="' . basename($PHP_SELF);
   if ($StartAt > $MaxNumberOfPorts) {
      echo '?StartAt=' . ($Start + $MaxNumberOfPorts);
   }
   echo '">Previous Page</a>';
}

echo '  <a href="' . basename($PHP_SELF) . "?StartAt=" . ($StartAt + $MaxNumberOfPorts) . '">Next Page</a>';

echo '</td></tr>';

//$HTML .= "</table>\n";
</script>
</table>
</td>
  <td valign="top" width="*">
   <? include("./_private/side-bars.php3") ?>
 </td>
</tr>
</table>
</tr>
</table>
<? include("./_private/footer.inc") ?>
</body>
</html>
