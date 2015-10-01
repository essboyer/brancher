<?php
/**
 *  Faster Branch Switcher & Regenerator
 *  by boyernotseaboyer
 *  v 0.3 - 01 Oct 2015
 *  v 0.2 - 25 Sep 2015
 *  v 0.1 - 21 Aug 2015
 *  Use this script to quickly switch branches (or create a new one) and renenerate your environment
 *
 *  Script Usage: > swb branch_name [baseBranch_branch_name]
 *
 *  Note: Needs at least PHP 5.4 (because of JSON pretty print option, of all things...)
 *
 * */
error_reporting(E_ERROR);

class brancher {
	// Path to your Development directory if different than /Users/[username]/Development/
	// or /Users/[username]/Dev/
	private static $gitPath = ""; // add trailing slash

	// Name of the repository to work on. Probably this won't change
	private static $repository = "AirSemblyV2";

	## Don't alter below this, unless, ya know.... ya wanna...
	##########################################################

	private static $regenPath;
	private $configPath;
	private static $gitNoBranchMsg = "/error: pathspec '(\w*)' did not match/";
	private static $gitNoRemoteMsg = "There is no tracking information for the current branch.";
	private static $pipeSettings   = array(
		0 => array("pipe", "r"), //stdin
		1 => array("pipe", "w"), //stout - where regenerate writes to
		2 => array("pipe", "w"), //sterr - where the git commands write to
	);

	private $branch;

	/**
	 * Constructor
	 * This is the controller for the script.
	 * You could easily add a "module" and plug it in here.
	 */
	public function brancher() {
		//$this->test();
		$this->init();

		$this->doSetBaseBranch();
		$this->doCheckout();
		$this->doSetOrigin();
		$this->doPull();
		$this->doModifyConfig();
		$this->doRegenerate();
		$this->doRunBranchSQL();

		self::wl("A'ight, we done, we audi 5000! Please out!");
		$this->tellMeWeAreDone();
	}

	/**
	 * Do the various initialization stuffs
	 * @return type
	 */
	private function init() {
		global $argv;

		echo self::$ascii . "\n\n";

		$this->configurePaths();

		// No args gives you a usage message
		if (empty($argv[1])) {
			self::wl("Usage: swb branch_name [base_branch_name=\"master\"]");
			self::wl("\t\t'branch_name' is the branch you want to do your work in");
			self::wl("\t\t'base_branch_name' is the base branch you want to create the working branch on. Don't worry. You can skip this if you need to.");
			exit;
		} else if ($argv[1] == "help" || $argv[1] == "--help" || $argv[1] == "-help" || $argv[1] == "-h") {
			self::wl("Help: You're on your own. Read the src, toughguy! ;)");
			die;
		}

		// figure out the argument(s)
		$this->branch     = $argv[1];
		$this->baseBranch = @$argv[2];
	}

	/**
	 * Checkout the branch
	 * This attempts to check out the desired branch.
	 * If it doesn't exist, and you want to, it will create it for you.
	 * It will also attempt to set the base branch branch for you, if you've
	 * passed opted not to skip that step.
	 * @return void
	 */
	private function doCheckout() {

		// try to checkout branch
		self::wl("Gonna try to check out the branch.");
		$process  = proc_open("git checkout " . $this->branch, self::$pipeSettings, $pipes, self::$gitPath, null);
		$retVal   = stream_get_contents($pipes[2]);
		$exitCode = proc_close($process);

		// check the output from the checkout
		if (preg_match(self::$gitNoBranchMsg, $retVal) === 1) {
			// branch doesn't exist, so let's create it if we're supposed to
			echo "T'ain't no branch by that name. Wanna make one? (y/n): [y] ";
			$answer = self::readKeyboard();

			if ($answer == "y" || empty($answer)) {
				$process  = proc_open("git checkout -b " . $this->branch, self::$pipeSettings, $pipes, self::$gitPath, null);
				$retVal   = stream_get_contents($pipes[2]);
				$exitCode = proc_close($process);
				self::wl("Done creating new branch \"" . $this->branch . "\"!!!");
			} else if ($answer == "n") {
				self::wl("The branch \"" . $this->branch . "\" doesn't exist, and you didn't want it created, so we audi 5000! Please out!");
				exit;
			} else {
				self::wl("I said type 'y' or 'n'... Sheesh, that too much for ya???");
				die;
			}
		} else {
			self::wl("Switched branch to \e[1;4;44m" . $this->branch . "\e[0m");
		}
	}

	/**
	 * Set the base branch before we switch to our new branch
	 * and then pull the branch
	 * @return void
	 */
	private function doSetBaseBranch() {
		// set base branch
		if (isset($this->baseBranch)) {
			self::wl("Setting the base branch to " . $this->baseBranch . ".");
		} else {
			echo "Enter your base branch branch name, or 'skip' to skip this step: [master] ";
			$answer = self::readKeyboard();
			if (empty($answer)) {
				$this->baseBranch = "master";
			} else if ($answer == "skip") {
				self::wl("Not setting a base branch. You must already be where you want to be, eh?");
				return;
			} else {
				$this->baseBranch = $answer;
			}
		}

		// try to checkout branch
		self::wl("Switching branch to '" . $this->baseBranch . "'");
		$process  = proc_open("git checkout " . $this->baseBranch, self::$pipeSettings, $pipes, self::$gitPath, null);
		$retVal   = stream_get_contents($pipes[2]);
		$exitCode = proc_close($process);

		// Make sure the branch exists, otherwise we probably have a typo
		if (preg_match(self::$gitNoBranchMsg, $retVal) === 1) {
			echo "Uh oh... There's no branch called '" . $this->baseBranch . "'. Please type the name again: [quit] ";
			$answer = self::readKeyboard();

			if (empty($answer) || $answer == "quit") {
				self::wl("Quitters never prosper, says mom...");
				exit;
			} else {
				$this->baseBranch = $answer;
				$this->doSetBaseBranch();
			}
		}

		// pull the base branch
		self::wl("Pulling '" . $this->baseBranch . "'...");
		$process  = proc_open("git pull " . $this->baseBranch, self::$pipeSettings, $pipes, self::$gitPath, null);
		$retVal   = stream_get_contents($pipes[2]);
		$exitCode = proc_close($process);

		//TODO: what kinda errors can we get here?
	}

	/**
	 * Set the upstream origin branch
	 * @return void
	 */
	private function doSetOrigin() {
		// set the origin branch
		self::wl("Setting the origin branch...");
		$process  = proc_open("git push --set-upstream origin " . $this->branch, self::$pipeSettings, $pipes, self::$gitPath, null);
		$retVal   = stream_get_contents($pipes[2]);
		$exitCode = proc_close($process);
	}

	/**
	 * Ya know... do a pull...
	 * @return void
	 */
	private function doPull() {
		// pull
		self::wl("Pulling...");
		$process  = proc_open("git pull", self::$pipeSettings, $pipes, self::$gitPath, null);
		$retVal   = stream_get_contents($pipes[2]);
		$exitCode = proc_close($process);
	}

	/**
	 * Open the Regenerate configuration JSON file
	 * and update it with the current branch
	 * @return void
	 */
	private function doModifyConfig() {
		// open & modify the configuration.json
		self::wl("Now we'll set the branch on the regenerate config.");

		$config             = json_decode(file_get_contents($this->configPath));
		$config->{"branch"} = $this->branch;
		file_put_contents($this->configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}

	/**
	 * Run Regenerate
	 * Spits out the application output (STDOUT) to our STDERR
	 * @return void
	 */
	private function doRegenerate() {
		// run regenerate
		self::wl("Going to Run regenerate now... Hold on, it takes awhile (1-3 minutes).");
		echo "\n";
		echo "    ******************************************************************************************************\n";
		echo "    *******************************            Regenerate Output           *******************************\n";
		echo "    ******************************************************************************************************\n";
		$streams  = array(2 => array("pipe", "w")); // only capture STDERR, allowing STDOUT to pass thru
		$process  = proc_open(self::$regenPath . "regenerate", $streams, $pipes, self::$regenPath, null);
		$retVal   = stream_get_contents($pipes[2]);
		$exitCode = proc_close($process);
		echo "\n\n";
	}

	/**
	 * Automaticall run the SQL file with the same name as the branch
	 * This will grab the current server you're regenerate is talking to
	 * @return void
	 */
	private function doRunBranchSQL() {

		// See if there is a branch SQL file to run
		if (!is_file(self::$gitPath . "/sql/" . $this->branch . ".sql")) {
			return;
		}

		// Check to see if we have ssh2 installed, and if not, brew it
		$result = exec("brew ls --versions php56-ssh2");

		if (empty($result)) {
			self::wl("Looks like PHP's *ssh2_exec* isn't installed, so let's brew it up!");

			$process  = proc_open("brew install php56-ssh2", self::$pipeSettings, $pipes, self::$gitPath, null);
			$retVal   = stream_get_contents($pipes[2]);
			$exitCode = proc_close($process);

			if (strpos($retVal, "Error:") !== false) {
				self::wl("Something went wrong brewing the SSH stuff, see?");
				print_r($retVal);
				self::wl("We're gonna bail out of this, but continue with anything else there is to do.");
				self::waitFor(5);
				return;
			}
	
		}

		echo "Branch SQL exists. Want to run it? (y/n): [y] ";
		$answer = self::readKeyboard();

		if ($answer == "y" || empty($answer)) {
			$config = json_decode(file_get_contents($this->configPath));
			$cmd = "mysql -p" . $config->{"password"} . " airsembly < " . $config->{"remote_path"} . "/sql/" . $this->branch . ".sql";

			$connection = ssh2_connect($config->{"hostname"}, $config->{"port"});
			ssh2_auth_password($connection, $config->{"username"}, $config->{"password"});

			$stream = ssh2_exec($connection, $cmd);
			$errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);

			stream_set_blocking($errorStream, true);

			$err = stream_get_contents($errorStream);

			if (strpos($err, "ERROR") === false) {
				self::wl("Ran branch SQL successfully!");
			} else {
				self::wl("Uh-oh... Running branch SQL bomed with the following message:");
				print_r($err);
				self::wl("We're gonna bail out of this, but continue with anything else there is to do.");
				self::waitFor(5);
				return;
			}
		}
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

		// War Games gag greeting
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

		self::$regenPath  = self::$gitPath . "regenerate/";
		self::$gitPath    = self::$gitPath . self::$repository;
		$this->configPath = self::$regenPath . "configuration.json";

		// CD to the user's Dev directory so the commands make sense
		chdir($this->gitPath);
	}

	/**
	 * Read input off the keyboard, single line only.
	 * @return void
	 */
	private static function readKeyboard() {
		$fp = fopen('php://stdin', 'r');
		// Don't you LOVE seeing this line?
		while (true) {
			$line = fgets($fp, 1024);
			if (stripos($line, PHP_EOL) !== false) {
				fclose($fp);
				return trim($line);
			}
			usleep(20000);
		}
	}

	/**
	 * Why Not?
	 * @return void
	 */
	private function tellMeWeAreDone() {
		if (file_exists(exec("which espeak"))) {
			exec("echo 'Branching done. Get back to work' | espeak -v en-gb -a 200");
		}
	}

	/**
	 * Write a line with a bunch of linebreaks
	 * @param type $str
	 * @return type
	 */
	private static function wl($str) {
		echo "--->\t" . $str . PHP_EOL;
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

/**
 * Sleep for x number of seconds, printing a catty message along the way.
 * @param type $seconds 
 * @return void
 */
	private static function waitFor($seconds) {
		self::wl("and continuing in...");
		for ($i = $seconds; $i > 0 ; $i--) {
			self::wl("$i...");
			sleep(1);
		}
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