<?php
/**
 *  Faster Branch Switcher & Regenerator
 *  by boyernotseaboyer
 *  v 0.1 - 21 Aug 2015
 *  Use this script to quickly switch branches (or create a new one) and renenerate your environment
 *  I recommend creating a script called "swb", give it exec prems, move it to /usr/bin:
 * 
 *      #!/bin/sh
 *      php /path/to/brancher.php "$@"
 *  
 *  Script Usage: > swb new_branch_name [upstream_branch_name] [new]
 * 
 *  Note: Needs at least PHP 5.4 (because of JSON pretty print option, of all things...)
 *  Note: Put this file in your Development dir; the same one that contains your AirSemblyV2 and regenerate directories
 *
 * */
error_reporting(E_ERROR);

## Setup Crap
#############

// Path to your regenerate directory
$regenPath = "/Users/seanboyer/Dev/regenerate/"; // add trailing slash


## Don't alter below this, unless, ya know.... ya wanna...
##########################################################

$configPath = $regenPath . "configuration.json";
$gitNoBranchMsg = "/error: pathspec '(\w*)' did not match/";

if (empty($regenPath)) {
	wl ("Dude! You need to set the \$regenPath in this script! Do that first, homie!");
	die;
}

// No args gives you a usage message
if (empty($argv[1])) {
	wl ("Usage: brancher.php branch_name [origin_name=\"master\"] [new]");
	exit;
}

$descriptorspec = array(
   0 => array("pipe", "r"), //stdin
   1 => array("pipe", "w"), //stout
   2 => array("pipe", "w")  //sterr - where the git commands write to
);

// figure out the argument(s)
$branch = $argv[1];
$create = (isset($argv[3]) && ($argv[3] == "new")) || $argv[2] == "new";
$upstream = !empty($argv[2]) ? $argv[2] : "master";

##  Doing Stuff...
##################

wl ("Gonna try to check out the branch. If you wanted, I'll create it if it doesn't exist.");

// try to checkout branch
$process = proc_open("git checkout " . $branch, $descriptorspec, $pipes, getcwd(), null);
$retVal = stream_get_contents($pipes[2]);
$gitRet = proc_close($process);

// check the output from the checkout
if (preg_match($gitNoBranchMsg, $retVal) === 1) {
	// branch doesn't exist, so let's create it if we're supposed to
	if ($create) {
		wl ("T'ain't no branch existing, so we makin' it, baby!");
		$process = proc_open("git checkout -b " . $branch, $descriptorspec, $pipes, getcwd(), null);
		$retVal = stream_get_contents($pipes[2]);
		$gitRet = proc_close($process);
		wl ("Done creating branch \"" . $branch . "\"!!!");
	} else {
		wl ("The branch \"" . $branch . "\" doesn't exist, and you didn't want it created, so we audi 5000! Please out!");
		exit;
	}

	// set upstream
	wl ("Setting the upstream branch.");

	$process = proc_open("git branch -u origin/" . $upstream, $descriptorspec, $pipes, getcwd(), null);
	$retVal = stream_get_contents($pipes[2]);
	$gitRet = proc_close($process);
} 

// pull
wl ("Pulling...");
$process = proc_open("git pull" . $upstream, $descriptorspec, $pipes, getcwd(), null);
$retVal = stream_get_contents($pipes[2]);
$gitRet = proc_close($process);

// open & modify the configuration.json
wl ("Now we'll set the branch on the regen config.");

$str = file_get_contents($configPath);
$config = json_decode($str);
$config->{"branch"} = $branch;
file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
wl ("Holy crap, that was easy!");

// run regenerate
wl ("Going to Run regenerate now... Hold on, it takes awhile (1-3 minutes).");
$process = proc_open($regenPath . "regenerate", $descriptorspec, $pipes, $regenPath, null);
$retVal = stream_get_contents($pipes[2]);
$gitRet = proc_close($process);
wl ("A'ight, we done, we audi 5000! Please out!");


/**
 * Write a line with a bunch of linebreaks
 * @param type $str 
 * @return type
 */
function wl($str) {
	echo ($str . PHP_EOL . PHP_EOL);
}

function l($obj, $prefixStr = "") {
	error_log($previxStr . print_r($obj, true));
}
