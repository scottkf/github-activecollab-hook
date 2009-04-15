<?php

include ('spyc/spyc.php');


class GithubActiveCollab {
	protected $config;

	function __construct($payload) {
		$this->config = Spyc::YAMLLoad('config.yml');

		if (!$res = json_decode(stripslashes($payload), true))
			exit;
		print_r($res);
		foreach ($res['commits'] as &$commit) {
			$this->process_commit($commit);
		}

	}
	
	function process_commit($commit) {

		$message = $commit['message'];
		$files = implode(", ",$commit['removed']) . implode(", ",$commit['added']) . implode(", ",$commit['modified']);

	    $url  = $this->config['submit_url'].'/'.$this->config['type'].'s/add?token='.$this->config['token'];
		$post = '"submitted=submitted&'.$this->config['type'].'[name]='.$commit['id'].' | '.$message.'&'.$this->config['type'].'[body]=Files: '.$files.'"';
		$curl = $this->config['curl'].' --insecure --silent -d '.$post.' -X POST -H "Accept:application/json" '.$url;
		// echo $curl;
	    //echo system($curl);
	}
	
		
}

function ob_callback($buffer) {
	global $ob_file;
	fwrite($ob_file, $buffer);
}

$ob_file = fopen('development.log', 'a');
ob_start();

$gh = new GithubActiveCollab($_POST['payload']);

fwrite($ob_file, ob_get_contents());
ob_end_clean();


?>