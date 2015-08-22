<?php
/**
 *  Faster Branch Switcher & Regenerator
 *  by boyernotseaboyer
 *  v 0.1 - 21 Aug 2015
 *  Use this script to quickly switch branches (or create a new one) and renenerate your environment
 *  I recommend creating a script called "swb" (for SWitch Branch), give it exec prems, move it to /usr/bin:
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

class brancher {	
	// Path to your regenerate directory
	private static $regenPath = ""; // add trailing slash


	## Don't alter below this, unless, ya know.... ya wanna...
	##########################################################

	private static $gitPath;
	private $configPath;
	private static $gitNoBranchMsg = "/error: pathspec '(\w*)' did not match/";
	private static $pipeSettings = array(
	   0 => array("pipe", "r"), //stdin
	   1 => array("pipe", "w"), //stout
	   2 => array("pipe", "w")  //sterr - where the git commands write to
	);

	private $branch;

	public function brancher() {
		$this->test();
		$this->init();

		$this->doCheckout();
		$this->doPull();
		$this->doModifyConfig();
		$this->doRegenerate();

		self::wl("A'ight, we done, we audi 5000! Please out!");
	}

	/**
	 * Do the various initialization stuffs
	 * @return type
	 */
	private function init() {
		global $argv;

		$this->configurePaths();

		// No args gives you a usage message
		if (empty($argv[1])) {
			self::wl("Usage: brancher.php branch_name [origin_name=\"master\"]");
			exit;
		}

		// figure out the argument(s)
		$this->branch = $argv[1];
		$this->upstream = @$argv[2];
		
	}

	private function doCheckout() {
		self::wl("Gonna try to check out the branch. If you wanted, I'll create it if it doesn't exist.");

		// try to checkout branch
		$process = proc_open("git checkout " . $this->branch, self::$pipeSettings, $pipes, self::$gitPath, null);
		$retVal  = stream_get_contents($pipes[2]);
		$gitRet  = proc_close($process);

		// check the output from the checkout
		if (preg_match(self::$gitNoBranchMsg, $retVal) === 1) {
			// branch doesn't exist, so let's create it if we're supposed to
			self::wl("T'ain't no branch by that name. Wanna make one? (y/n): [y]  ");
			$anser = self::readKeyboard();

			if ($answer == "y" || $answer == "") {
				$process = proc_open("git checkout -b " . $this->branch, self::$pipeSettings, $pipes, getcwd(), null);
				$retVal = stream_get_contents($pipes[2]);
				$gitRet = proc_close($process);
				self::wl("Done creating branch \"" . $this->branch . "\"!!!");
			} else if ($answer == "n") {
				self::wl("The branch \"" . $this->branch . "\" doesn't exist, and you didn't want it created, so we audi 5000! Please out!");
				exit;
			} else {
				self::wl("I said type 'y' or 'n'... Sheesh, that too much for ya???");
				die;
			}
			
			$this->setUpstreamBranch();
		}
	}

	private function setUpstreamBranch() {
		// set upstream
		if (isset($this->upstream)) {
			self::wl("Setting the upstream branch.");
		} else {
			self::wl("Enter your upstream branch name: [master] ");
			$answer = self::readKeyboard();
			if (!empty($answer)) {
				$this->upstream = $answer;
			} else {
				$this->upstream = "master";
			}
		}

		$process = proc_open("git branch -u origin/" . $this->upstream, self::pipeSettings, $pipes, getcwd(), null);
		$retVal = stream_get_contents($pipes[2]);
		
		$gitRet = proc_close($process);
	}

	private function doPull() {
		// pull
		self::wl("Pulling...");
		$process = proc_open("git pull" . $this->upstream, self::pipeSettings, $pipes, getcwd(), null);
		$retVal = stream_get_contents($pipes[2]);
		
		$gitRet = proc_close($process);
	}

	private function doModifyConfig() {
		// open & modify the configuration.json
		self::wl("Now we'll set the branch on the regen config.");

		$config = json_decode(file_get_contents($this->configPath));
		$config->{"branch"} = $this->branch;
		file_put_contents($this->configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}

	private function doRegenerate() {
		// run regenerate
		self::wl("Going to Run regenerate now... Hold on, it takes awhile (1-3 minutes).");
		$process = proc_open(self::$regenPath . "regenerate", self::pipeSettings, $pipes, self::$regenPath, null);
		$retVal = stream_get_contents($pipes[2]);
		
		$gitRet = proc_close($process);
	}

	private function configurePaths() {
		// Get the current user
		$me = self::exec("whoami");

		if ($me == "root") {
			self::wl("You're running as root for some reason. Please su to your normal user.");
			die;
		}

		// Try to guess the path
		if (is_dir(self::$regenPath)) {
			// Do nothing, we're golden
		} else if (is_dir("/Users/" . $me . "/Development/regenerate")) {
			self::$regenPath = "/Users/" . $me . "/Development/regenerate";
		} else if (is_dir("/Users/" . $me . "/Dev/regenerate")) {
			self::$regenPath = "/Users/" . $me . "/Dev/regenerate";
		} else {
			self::wl("Dude! You need to set the self::\$regenPath in this script! Do that first, homie!");
			die;
		}

		$this->gitPath = self::$regenPath . "../";
		$this->configPath = self::$regenPath . "configuration.json";
	}

	/**
	 * Read input off the keyboard, single line only.
	 * @return type
	 */
	private static function readKeyboard() {
		$fp = fopen('php://stdin', 'r');
		while (true) {
		    $line = fgets($fp, 1024);
		    if (stripos($line, PHP_EOL)) {
		    	fclose($fp);
		    	return trim($line);
		    }
		    usleep(20000);
		}
	}

	/**
	 * Write a line with a bunch of linebreaks
	 * @param type $str 
	 * @return type
	 */
	private static function wl($str) {
		echo ($str . PHP_EOL . PHP_EOL);
	}

	/**
	 * Write an object to the error log, optionally
	 * prefixing with a string
	 * @param type $obj 
	 * @param type $prefixStr 
	 * @return type
	 */
	private static function l($obj, $prefixStr = "") {
		error_log($previxStr . print_r($obj, true));
	}

	private function test() {
		self::l(exec("whoami"));
		die;
	}
}

$brancher = new brancher();