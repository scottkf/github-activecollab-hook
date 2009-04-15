<?php

include ('spyc/spyc.php');


class GithubActiveCollab {
	protected $config;

	function __construct($payload) {
		$this->config = Spyc::YAMLLoad('config.yml');

		if (!$res = json_decode(stripslashes($payload), true))
			exit;
		//print_r($res);
		foreach ($res['commits'] as &$commit) {
			$this->process_commit($commit);
		}

	}
	
	function _post($url, $header, $data) {
		$options = array(
			CURLOPT_RETURNTRANSFER 	=> true,
			CURLOPT_HEADER			=> false,
			CURLOPT_CONNECTTIMEOUT 	=> 120,
	        CURLOPT_TIMEOUT        	=> 120,
			CURLOPT_POST			=> 1,
			CURLOPT_POSTFIELDS		=> $data,
			CURLOPT_SSL_VERIFYHOST 	=> 0,
	        CURLOPT_SSL_VERIFYPEER	=> false,
			CURLOPT_HTTPHEADER 		=> array($header),
	        CURLOPT_VERBOSE       	=> 1		
			);
		$curl = curl_init($url);
		curl_setopt_array($curl, $options);
		$content = curl_exec($curl);
		if (!$errno = curl_errno($curl)) {
		}
		$errmsg = curl_error($curl);
		echo $errno.' '.$errmsg."\n";
		echo $data; 
		$response = curl_getinfo($curl);
		curl_close($curl);
		echo $content;


	}
	
	
	function process_commit($commit) {

		$message = $commit['message'];
		$files = implode(", ",$commit['removed']) . implode(", ",$commit['added']) . implode(", ",$commit['modified']);

	    $url  = $this->config['submit_url'].'/'.$this->config['type'].'s/add?token='.$this->config['token'];
		$post = 'submitted=submitted&'.$this->config['type'].'[name]='.urlencode($message).'+|+'.$commit['id'].' by '.urlencode($commit['author']['name']).'&'.$this->config['type'].'[body]='.urlencode('Files: '.$files);
		$curl = $this->config['curl'].' --insecure --silent -d '.$post.' -X POST -H "Accept:application/json" '.$url;
		$this->_post($url, 'Accept: application/json', $post);
		// echo $curl;
	    //echo system($curl);
	}
	
		
}

if (count($_POST) <= 0)
	die ('Hi!');
	
$ob_file = fopen('development.log', 'a');
ob_start();

$gh = new GithubActiveCollab($_POST['payload']);

fwrite($ob_file, ob_get_contents());
ob_end_clean();


?>