<?php
/**
 *  Faster Branch Switcher & Regenerator
 *  by boyernotseaboyer
 *  v 0.1 - 21 Aug 2015
 *  Use this script to quickly switch branches (or create a new one) and renenerate your environment
 *  
 *  Script Usage: > swb branch_name [upstream_branch_name]
 * 
 *  Note: Needs at least PHP 5.4 (because of JSON pretty print option, of all things...)
 *
 * */
error_reporting(E_ERROR);

class brancher {	
	// Path to your Development directory if different than /Users/[username]/Development/
	// or /Users/[username]/Dev/
	private static $gitPath = ""; // add trailing slash


	## Don't alter below this, unless, ya know.... ya wanna...
	##########################################################

	private static $regenPath;
	private $configPath;
	private static $gitNoBranchMsg = "/error: pathspec '(\w*)' did not match/";
	private static $gitNoRemoteMsg = "There is no tracking information for the current branch.";
	private static $pipeSettings = array(
	   0 => array("pipe", "r"), //stdin
	   1 => array("pipe", "w"), //stout - where regenerate writes to
	   2 => array("pipe", "w")  //sterr - where the git commands write to
	);

	private $branch;

	/**
	 * Constructor
	 * This is the controller for the script.
	 * You could easily add a "module" and plug it in here.
	 */
	public function brancher() {
		// $this->test();
		$this->init();

		$this->doCheckout();
		$this->doSetOrigin();
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

		self::wl(self::$ascii);

		$this->configurePaths();

		// No args gives you a usage message
		if (empty($argv[1])) {
			self::wl("Usage: swb branch_name [origin_name=\"master\"]");
			exit;
		} else if ($argv[1] == "help" || $argv[1] == "--help" || $argv[1] == "-help" || $argv[1] == "-h") {
			self::wl("Help: You're on your own. Read the src, toughguy! ;)");
			die;
		}

		// figure out the argument(s)
		$this->branch = $argv[1];
		$this->upstream = @$argv[2];
	}

	/**
	 * Checkout the branch
	 * This attempts to check out the desired branch.
	 * If it doesn't exist, and you want to, it will create it for you.
	 * It will also attempt to set the upstream branch for you, if you've
	 * passed opted not to skip that step.
	 * @return void
	 */
	private function doCheckout() {
		self::wl("Gonna try to check out the branch.");

		// switch to upstream first
		$this->setUpstreamBranch();

		// try to checkout branch
		self::wl("Switching branch to '" . $this->upstream . "'");
		$process = proc_open("git checkout " . $this->upstream, self::$pipeSettings, $pipes, self::$gitPath, null);
		$retVal  = stream_get_contents($pipes[2]);
		$exitCode  = proc_close($process);

		// try to checkout branch
		$process = proc_open("git checkout " . $this->branch, self::$pipeSettings, $pipes, self::$gitPath, null);
		$retVal  = stream_get_contents($pipes[2]);
		$exitCode  = proc_close($process);

		// check the output from the checkout
		if (preg_match(self::$gitNoBranchMsg, $retVal) === 1) {
			// branch doesn't exist, so let's create it if we're supposed to
			echo("T'ain't no branch by that name. Wanna make one? (y/n): [y] ");
			$answer = self::readKeyboard();

			if ($answer == "y" || empty($answer)) {
				$process = proc_open("git checkout -b " . $this->branch, self::$pipeSettings, $pipes, self::$gitPath, null);
				$retVal = stream_get_contents($pipes[2]);
				$exitCode = proc_close($process);
				self::wl("Done creating branch \"" . $this->branch . "\"!!!");
			} else if ($answer == "n") {
				self::wl("The branch \"" . $this->branch . "\" doesn't exist, and you didn't want it created, so we audi 5000! Please out!");
				exit;
			} else {
				self::wl("I said type 'y' or 'n'... Sheesh, that too much for ya???");
				die;
			}	
		} else {
			self::wl("Switched branch to " . $this->branch);
		}
	}

	private function setUpstreamBranch() {
		// set upstream
		if (isset($this->upstream)) {
			self::wl("Setting the upstream branch.");
		} else {
			echo("Enter your upstream branch name, or 'skip' to skip this step: [master] ");
			$answer = self::readKeyboard();
			if (empty($answer)) {
				$this->upstream = "master";
			} else if ($answer == "skip") {
				self::wl("Not setting an upstream.");
				return;
			} else {
				$this->upstream = $answer;
			}
		}
	}

	private function doSetOrigin() {
		// set the origin branch
		self::wl("Setting the origin branch...");
		$process = proc_open("git push --set-upstream origin " . $this->branch, self::$pipeSettings, $pipes, self::$gitPath, null);
		$retVal = stream_get_contents($pipes[2]);
		$exitCode = proc_close($process);
	}

	private function doPull() {
		// pull
		self::wl("Pulling...");
		$process = proc_open("git pull", self::$pipeSettings, $pipes, self::$gitPath, null);
		$retVal = stream_get_contents($pipes[2]);
		$exitCode = proc_close($process);
	}

	private function doModifyConfig() {
		// open & modify the configuration.json
		self::wl("Now we'll set the branch on the regenerate config.");

		$config = json_decode(file_get_contents($this->configPath));
		$config->{"branch"} = $this->branch;
		file_put_contents($this->configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}

	private function doRegenerate() {
		// run regenerate
		self::wl("Going to Run regenerate now... Hold on, it takes awhile (1-3 minutes).");
		$streams = array (2 => array("pipe", "w")); // only capture STDERR, allowing STDOUT to pass thru
		$process = proc_open(self::$regenPath . "regenerate", $streams, $pipes, self::$regenPath, null);
		$retVal = stream_get_contents($pipes[2]);
		$exitCode = proc_close($process);
	}

	/**
	 * Configure paths
	 * Attempt to guess the user's development path.
	 * If we can't guess it here, the user will have to manually
	 * mod the script to include their path.
	 * @return void
	 */
	private function configurePaths() {
		global $argv;

		// Get the current user
		$me = exec("whoami");

		// don't worry, I forget to not hangout as root sometimes, too.
		if ($me == "root") {
			self::wl("You're running as root for some reason. Please su to your normal user.");
			die;
		}

		// gag greeting
		if (isset($argv[1])) {
			self::wl("Greetings " . $me . ". How about a nice game of chess?");
		}

		// Try to guess the path
		if (is_dir(self::$gitPath)) {
			// Do nothing, we're golden
		} else if (is_dir("/Users/" . $me . "/Development/")) {
			self::$gitPath = "/Users/" . $me . "/Development/";
		} else if (is_dir("/Users/" . $me . "/Dev/")) {
			self::$gitPath = "/Users/" . $me . "/Dev/";
		} else {
			self::wl("Dude! You need to set the self::\$gitPath in this script! Do that first, homie!");
			die;
		}

		self::$regenPath = self::$gitPath . "regenerate/";
		self::$gitPath = self::$gitPath . "AirSemblyV2";
		$this->configPath = self::$regenPath . "configuration.json";

		// CD to the user's Dev directory
		chdir($this->gitPath);
	}

	/**
	 * Read input off the keyboard, single line only.
	 * @return type
	 */
	private static function readKeyboard() {
		$fp = fopen('php://stdin', 'r');
		while (true) { // Don't you LOVE seeing this line?
		    $line = fgets($fp, 1024);
		    if (stripos($line, PHP_EOL) !== false) {
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
		// put test crap here
	}

	private static $ascii = <<<EOV
     ''  '''''''     ''  '''    ''' '''''''' '''    :::.              ::::   ,::::::::::::::::::::         
     ''  '''''''     ''  '''    ''' '''''''' '''    :::.              ::::   ::::::::::::::::::::::        
     ''  '''''''     ''  '''    ''' '''''''' '''    :::.              ::::   :::::::::::::::::::::::       
                                                    :::.              ::::   ::::::::::::::::::::::::      
                                                    :::.              ::::   ::::       ,:::    `:::::     
                                                    :::.              ::::   ::::       ,:::     `:::::    
                                                    ::::              ::::   ::::       ,:::      `:::::   
      :'''''''''''''      ::    '   ''''''''''`     :::::             ::::   ::::       ,:::       `:::::  
     ''''''''''''''''     ''    '. ''''''''''''.     :::::            ::::   ::::       ,:::        `::::  
     ';             ''    ''    '.''`         ''      :::::           ::::   ::::       ,:::         ::::  
                    `'`   ''    '''`                   :::::          ::::   ::::       ,:::         ::::  
                     '`   ''    ''`                     :::::         ::::   ::::       ,:::         ::::  
                    `'`   ''    '.                       :::::        ::::   ::::       ,:::         ::::  
    ''''''''''''''''''`   ''    '.                        :::::       ::::   ::::       ,:::         ::::  
   '':,,,,,,,,,,,,,,;'`   ''    '.                         :::::      ::::   ::::       ,:::         ::::  
  ''`                '`   ''    '.                          :::::     ::::   ::::       ,:::         ::::  
  ''                 '`   ''    '.                           :::::    ::::   ::::       ,:::         ::::  
  ''                 '`   ''    '.                            :::::   ::::   ::::       ,:::         ::::  
  ''                 '`   ''    '.                             :::::::::::   ::::       ,:::         ::::  
  ;',                '`   ''    '.                              ::::::::::   ::::       ,:::         ::::  
   '''''''''''''''''''`   ''    '.                               :::::::::   ::::       ,:::         ::::  
    ''''''''''''''''''    ''    '`                                ::::::::   ::::       ,:::         ::::  
   ****************************************************************************************************** 
   *******************************     Branch Switcher & Regenerator      *******************************
   ******************************************************************************************************
EOV;
}

$brancher = new brancher();