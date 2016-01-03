#!/usr/bin/php
<?php

// this should be 2x of RAM
$SIZE_GB=16;
$SIZE_MB=$SIZE_GB*1024;
$SIZE_B=$SIZE_MB*1024;
$SIZE_B_AS_4k=$SIZE_B/4;
$HDPARM = "hdparm -tT --direct";
$DISK_INDEX_CMD = "fdisk -l 2>&1 | grep \"^Disk\"";
$DISK_LIST_CMD = "fdisk -l 2>/dev/null | grep \"Disk \/\" | grep -v \"\/dev\/md\" | awk '{print $2}' | sed -e 's/://g'";
$TANK_SIZE_CMD = "du -sh /tank";
$DATE = "date";
$FREE = "free -h";
$HOSTNAME = "hostname";
$UNAME = "uname -a";
$TOP="(top -b -n 1 | head -n 20) ";
$CAT="/bin/cat";
$CPUINFO="cat /proc/cpuinfo";
$DMESG="dmesg";
$LSPCI="lspci";

$DD_READ="(time sh -c \"dd if=/tank/tmp of=/dev/null bs=4k\") ";
$DD_WRITE="(time sh -c \"dd if=/dev/zero of=/tank/tmp bs=4k count=".$SIZE_B_AS_4k." && sync\") ";
$BOONIE="/usr/sbin/bonnie++ -d /tank -r ".$SIZE_MB." -u root -n 128";
$BON_CSV2HTML = "/usr/bin/bon_csv2html";

$ZPOOL = "/sbin/zpool";
$ZFS = "/sbin/zfs";

$ZPOOL_DESTROY_CMD = "destroy -f tank";
$ZPOOL_STATUS = "status";
$ZPOOL_LIST = "list";
$ZFS_LIST = "list";

$TOOLS = array(
	"zfs",
	"zpool",
	"dmesg",
	"hdparm",
	"lspci",
	"free",
	"fdisk",
	"du",
	"bonnie++"
);

$DISKS = getDisks();

$ZPOOL_CREATE_CMDS = array(
	"1.1" => "create tank /dev/sda",
	"1.2" => "create tank /dev/sda /dev/sdb",
	"1.3" => "create tank /dev/sda /dev/sdb /dev/sde",
	"1.4" => "create tank /dev/sda /dev/sdb /dev/sde /dev/sdf",
	"1.5" => "create tank /dev/sda /dev/sdb /dev/sdc /dev/sdd /dev/sde /dev/sdf /dev/sdg",

	"2.1" => "create tank mirror /dev/sda /dev/sdb",
	"2.2" => "create tank mirror /dev/sda /dev/sde",

	"2.3" => "create tank mirror /dev/sda /dev/sdb mirror /dev/sde /dev/sdf",
	"2.4" => "create tank mirror /dev/sda /dev/sdb mirror /dev/sde /dev/sdf",
	"2.5" => "create tank mirror /dev/sda /dev/sdb mirror /dev/sde /dev/sdf",
	"2.6" => "create tank mirror /dev/sda /dev/sdb mirror /dev/sde /dev/sdf",

	"3.1" => "create tank raidz /dev/sda /dev/sdb /dev/sde /dev/sdf",
	"3.2" => "create tank raidz /dev/sda /dev/sdb /dev/sde /dev/sdf",
	"3.3" => "create tank raidz /dev/sda /dev/sdb /dev/sde /dev/sdf",
	"3.4" => "create tank raidz /dev/sda /dev/sdb /dev/sde /dev/sdf",

	"4.1" => "create tank raidz2 /dev/sda /dev/sdb /dev/sde /dev/sdf",
	"4.2" => "create tank raidz2 /dev/sda /dev/sdb /dev/sde /dev/sdf",
	"4.3" => "create tank raidz2 /dev/sda /dev/sdb /dev/sde /dev/sdf",
	"4.4" => "create tank raidz2 /dev/sda /dev/sdb /dev/sde /dev/sdf"
);

$ZPOOL_CACHE = array(
	"2.4" => "add tank cache /dev/sdc",
	"2.5" => "add tank cache /dev/sdc /dev/sdg",
	"2.6" => "add tank cache /dev/sdc /dev/sdg",

	"3.2" => "add tank cache /dev/sdc",
	"3.3" => "add tank cache /dev/sdc /dev/sdg",
	"3.4" => "add tank cache /dev/sdc /dev/sdg",

	"4.2" => "add tank cache /dev/sdc",
	"4.3" => "add tank cache /dev/sdc /dev/sdg",
	"4.4" => "add tank cache /dev/sdc /dev/sdg"
);

$ZPOOL_LOG = array(
	"2.6" => "add -f tank log /dev/sdd",
	"3.4" => "add -f tank log /dev/sdd",
	"4.4" => "add -f tank log /dev/sdd"
);


$COPY = "&copy; 2016 Philipp Haussleiter";
$RUN_BENCH = true;

##############################################
#
#	Config End
#
##############################################

exec("mkdir -p bench");
$indexFile = fopen("bench/index.html", "w") or die("Unable to open index file!");
addHeader($indexFile, "ZFS Benchmark");

openChapter($indexFile, "Index", "Details");
runCmd($DATE, $indexFile);
runCmd($HOSTNAME, $indexFile);
runCmd($UNAME, $indexFile);
runCmd($FREE, $indexFile);
runCmd($DISK_INDEX_CMD, $indexFile);

addSystemFile();

fwrite($indexFile, "<p><a href=\"sys/system.html\">...more</a></p>\n");
fwrite($indexFile, "<p><kbd>Test Size</kbd><br /><samp>".$SIZE_GB." GB</samp></p>\n");
closeChapter($indexFile);

addDiskBenchmark($indexFile);

openChapter($indexFile, "Index", "Benchmarks");
fwrite($indexFile, "<dl>\n");

$i = 0;
foreach ($ZPOOL_CREATE_CMDS as $key => $cmd) {
	$i++;
	exec("mkdir -p bench/".$key);
	$poolCmds = "";
	$benchFile = fopen("bench/".$key."/result.html", "w") or die("Unable to open ".$key." file!");
	$nav = array("Details", "Pool Setup", "DD", "Boonie", "Cleanup");
	addSubHeader($benchFile, $key, $nav);

	openChapter($benchFile, $key, "Details");
	runCmd("echo \"init \" && date", $benchFile);
	closeChapter($benchFile);

	openChapter($benchFile, $key, "Pool Setup");
	runCmd($ZPOOL." ".$cmd, $benchFile);
	$poolCmds .= $ZPOOL." ".$cmd."\n";
	if(isset($ZPOOL_CACHE[$key])) {
		runCmd($ZPOOL." ".$ZPOOL_CACHE[$key], $benchFile);
		$poolCmds .= $ZPOOL." ".$ZPOOL_CACHE[$key]."\n";
	}
	if(isset($ZPOOL_LOG[$key])) {
		runCmd($ZPOOL." ".$ZPOOL_LOG[$key], $benchFile);
		$poolCmds .= $ZPOOL." ".$ZPOOL_LOG[$key]."\n";
	}
	runCmd($ZPOOL." ".$ZPOOL_STATUS, $benchFile);
	shell_exec($ZPOOL." ".$ZPOOL_STATUS." > bench/".$key."/zpool.status.txt");
	runCmd($ZPOOL." ".$ZPOOL_LIST, $benchFile);
	runCmd($ZFS." ".$ZFS_LIST, $benchFile);		
	closeChapter($benchFile);

	if($RUN_BENCH){
		runBoonie($benchFile, $key);

		openChapter($benchFile, $key, "DD");
		runCmd("echo \"start \" && date", $benchFile);
		runCmd($TOP, $benchFile);
		runCmd($DD_WRITE, $benchFile);
		runCmd($TOP, $benchFile);
		runCmd($DD_READ, $benchFile);
		runCmd($TOP, $benchFile);
		runCmd("echo \"end \" && date", $benchFile);
		closeChapter($benchFile);
	}
	openChapter($benchFile, $key, "Cleanup");
	runCmd($ZPOOL." ".$ZPOOL_DESTROY_CMD, $benchFile);
	runCmd("echo \"done \" && date", $benchFile);
	closeChapter($benchFile);

	addSubFooter($benchFile);
	
	fclose($benchFile);
	addIndexLink($indexFile, $i.". Benchmark:", $poolCmds, $key."/result.html");	
}

fwrite($indexFile, "</dl>\n");
closeChapter($indexFile);

openChapter($indexFile, "Index", "Benchmark Matrix");
createTestMatrix($indexFile);
closeChapter($indexFile);

addFooter($indexFile);
fclose($indexFile);


function checkTools() {
	global $TOOLS;
}

function getDisks() {
	global $DISK_LIST_CMD;
	$output = shell_exec($DISK_LIST_CMD);
	return split("\n", trim($output));
}

function runBoonie($benchFile, $key) {
	global $TOP, $BOONIE, $BON_CSV2HTML, $CAT;
	
	openChapter($benchFile, $key, "Boonie");
	runCmd("echo \"start \" && date", $benchFile);	
	runCmd($TOP, $benchFile);

	fwrite($benchFile, "<p class=\"cmd\">\n");
	$cmd = $BOONIE." >> bench/".$key."/boonie.csv";
	$output = shell_exec($cmd." 2>&1");
	fwrite($benchFile, "<kbd>".$cmd."</kbd>\n<br />\n");
	$cmd = $BON_CSV2HTML." bench/".$key."/boonie.csv > bench/".$key."/boonie.html";
	$output = shell_exec($cmd." 2>&1");
	fwrite($benchFile, "<kbd>".$cmd."</kbd>\n<br />\n");
	$cmd = $CAT." bench/".$key."/boonie.html";
	$output = shell_exec($cmd." 2>&1");
	fwrite($benchFile, splitBoonieOutput($output)."\n");
	fwrite($benchFile, "</p>\n");
	runCmd($TOP, $benchFile);
	runCmd("echo \"end \" && date", $benchFile);
	closeChapter($benchFile);
}

function runCmd($cmd, $benchFile) {
	$output = shell_exec($cmd." 2>&1");
	fwrite($benchFile, "<p class=\"cmd\">\n<kbd>".$cmd."</kbd>\n<br />\n");
	fwrite($benchFile, "<samp><pre>".$output."</pre></samp>\n</p>\n");
}

function writeOutput($cmd, $output, $benchFile) {
	fwrite($benchFile, "<p class=\"cmd\">\n<kbd>".$cmd."</kbd>\n<br />\n");
	fwrite($benchFile, "<samp><pre>".$output."</pre></samp>\n</p>\n");
}

function addHeader($benchFile, $key) {
	fwrite($benchFile, "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n<title>Benchmark ".$key."</title>\n");
	fwrite($benchFile, "\n<!-- Latest compiled and minified CSS -->\n");
	fwrite($benchFile, "<link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css\" integrity=\"sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7\" crossorigin=\"anonymous\">\n");
	fwrite($benchFile, "\n<!-- Optional theme -->\n");
	fwrite($benchFile, "<link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap-theme.min.css\" integrity=\"sha384-fLW2N01lMqjakBkx3l/M9EahuwpSfeNvV63J5ezn3uZzapT0u7EYsXMjQV+0En5r\" crossorigin=\"anonymous\">\n");
	addStyles($benchFile);
	fwrite($benchFile, "</head>\n<body>\n");
	fwrite($benchFile, "<div class=\"container\">\n");
	fwrite($benchFile, "<div class=\"row\">\n");
	fwrite($benchFile, "<div class=\"col-md-12\">\n");
	fwrite($benchFile, "<h1>".$key."</h1>\n");
}

function addFooter($benchFile) {
	global $COPY;
	fwrite($benchFile, "</div>\n");
	fwrite($benchFile, "</div>\n");
	fwrite($benchFile, "</div>\n");
	fwrite($benchFile, "<div class=\"container-fluid\">\n");
	fwrite($benchFile, "<div class=\"center row\">".$COPY."</div>\n");
	fwrite($benchFile, "</div>\n");
	fwrite($benchFile, "\n</body>\n</html>\n");
}

function addSubHeader($benchFile, $key, $nav) {
	fwrite($benchFile, "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n<title>Benchmark ".$key."</title>\n");
	fwrite($benchFile, "\n<!-- Latest compiled and minified CSS -->\n");
	fwrite($benchFile, "<link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css\" integrity=\"sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7\" crossorigin=\"anonymous\">\n");
	fwrite($benchFile, "\n<!-- Optional theme -->\n");
	fwrite($benchFile, "<link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap-theme.min.css\" integrity=\"sha384-fLW2N01lMqjakBkx3l/M9EahuwpSfeNvV63J5ezn3uZzapT0u7EYsXMjQV+0En5r\" crossorigin=\"anonymous\">\n");
	addStyles($benchFile);
	fwrite($benchFile, "</head>\n<body>\n");
	fwrite($benchFile, "<nav class=\"navbar navbar-default navbar-fixed-top\">\n");
	  fwrite($benchFile, "<div class=\"container-fluid\">\n");
	    fwrite($benchFile, "<div class=\"navbar-header\">\n");
	      fwrite($benchFile, "<a class=\"navbar-brand\" href=\"../index.html\">\n");
	        fwrite($benchFile, "back to Index\n");
	      fwrite($benchFile, "</a>\n");
	    fwrite($benchFile, "</div>\n");
	    fwrite($benchFile, "<div class=\"collapse navbar-collapse\">\n");
		  fwrite($benchFile, "<ul class=\"nav navbar-nav\">\n");
		  foreach ($nav as $entry) {
		  	fwrite($benchFile, "<li><a href=\"#".getAnchor($key, $entry)."\">".$entry."</a></li>\n"); 
		  }	    
		  fwrite($benchFile, "</ul>\n");      
	    fwrite($benchFile, "</div>\n");      
	  fwrite($benchFile, "</div>\n");
	fwrite($benchFile, "</nav>\n");
	fwrite($benchFile, "<div class=\"container\">\n");
	fwrite($benchFile, "<div class=\"row\">\n");
	fwrite($benchFile, "<div class=\"col-md-10\">\n");
	fwrite($benchFile, "<h1>".$key."</h1>\n");
}

function addSubFooter($benchFile) {
	global $COPY;
	fwrite($benchFile, "</div>\n");
	fwrite($benchFile, "<div class=\"col-md-2\">\n");
	fwrite($benchFile, "</div>\n");
	fwrite($benchFile, "</div>\n");
	fwrite($benchFile, "</div>\n");
	fwrite($benchFile, "<div class=\"container-fluid\">\n");
	fwrite($benchFile, "<div class=\"center row\">".$COPY."</div>\n");
	fwrite($benchFile, "</div>\n");
	fwrite($benchFile, "\n</body>\n</html>\n");
}

function addStyles($file) {
	fwrite($file, "<style type\"text/css\">");
	fwrite($file, "body { padding-top: 70px; }");
	fwrite($file, "a.anchor { margin-top:60px; top: -60px; position: relative; visibility: hidden;}"); 
	fwrite($file, ".center { text-align:center; }"); 
	// styles for Boonie
	fwrite($file, "td.header {text-align: center; backgroundcolor: \"#CCFFFF\" }td.rowheader {text-align: center; backgroundcolor: \"#CCCFFF\" }td.size {text-align: center; backgroundcolor: \"#CCCFFF\" }td.ksec {text-align: center; fontstyle: italic }");
	fwrite($file, "</style>\n");
}

function openChapter($benchFile, $key, $name) {
	$anchor = getAnchor($key, $name);
	fwrite($benchFile, "<a class=\"anchor\" aria-hidden=\"true\" id=\"".$anchor."\"></a> <h2><small><a href=\"#".$anchor."\">#</a></small>".$name."</h2>\n");
}

function closeChapter($benchFile) {

}

function getAnchor($key, $name) {
	return strtolower(str_replace(" ", "_",$key." ".$name));
}

function addIndexLink($indexFile, $title, $poolCmds, $file) {
	fwrite($indexFile, "\t<dt><a href=\"".$file."\">".$title."</a></dt>\n");
	fwrite($indexFile, "\t<dd><pre>".$poolCmds."</pre></dd>\n");
}

function createTestMatrix($indexFile) {
	global $ZPOOL, $ZPOOL_CREATE_CMDS, $ZPOOL_CACHE, $ZPOOL_LOG;
	fwrite($indexFile, "<table class=\"table table-striped\">\n");
	fwrite($indexFile, "<tr>\n");
	fwrite($indexFile, "<th>Benchmark</th>\n");
	fwrite($indexFile, "<th>Pool</th>\n");				
	fwrite($indexFile, "</tr>\n");
	foreach ($ZPOOL_CREATE_CMDS as $key => $cmd) {
		fwrite($indexFile, "<tr>\n");
		fwrite($indexFile, "<th><a href=\"".$key."/result.html\">".$key."</a></th>\n");
		fwrite($indexFile, "<td><pre>".shell_exec("cat bench/".$key."/zpool.status.txt")."</pre></td>\n");
		fwrite($indexFile, "</tr>\n");
	}
	fwrite($indexFile, "</table>\n");
}

function addSystemFile() {
	global $DMESG, $CPUINFO, $LSPCI;
	exec("mkdir -p bench/sys");
	$sysFile = fopen("bench/sys/system.html", "w") or die("Unable to open system file!");
	addSubHeader($sysFile, "System", array("Dmesg", "CPU Info", "lspci"));
	
	openChapter($sysFile, "System", "dmesg");
	runCmd($DMESG, $sysFile);
	closeChapter($sysFile);

	openChapter($sysFile, "System", "CPU Info");
	runCmd($CPUINFO, $sysFile);
	closeChapter($sysFile);
	
	openChapter($sysFile, "System", "lspci");
	runCmd($LSPCI, $sysFile);
	closeChapter($sysFile);
	
	addSubFooter($sysFile);
	fclose($sysFile);
}

function addDiskBenchmark($indexFile){
	global $DISKS, $HDPARM;
	openChapter($indexFile, "Index", "Disks");
	fwrite($indexFile, "<table class=\"table table-striped\">\n");
	fwrite($indexFile, "<tr>\n");
	fwrite($indexFile, "<th>Disk</th>\n");
	fwrite($indexFile, "<th>Benchmark</th>\n");				
	fwrite($indexFile, "</tr>\n");
	foreach ($DISKS as $disk){
		fwrite($indexFile, "<tr>\n");
		fwrite($indexFile, "<th>".$disk."</th>\n");
		fwrite($indexFile, "<td><pre>".shell_exec($HDPARM." ".$disk)."</pre></td>\n");
		fwrite($indexFile, "</tr>\n");
	}
	fwrite($indexFile, "</table>\n");
	closeChapter($indexFile);
}

function splitBoonieOutput($output) {
	$start = strripos($output, "<table");
	$end = strripos($output, "</table>");
	return $start && $end ? substr($output, $start, $end-$start)."</table>" : "";
}

function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
}
?>
