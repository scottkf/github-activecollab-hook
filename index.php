<?php

include ('spyc/spyc.php');


class GithubActiveCollab {
	protected $config;


	function __construct($payload) {

		$this->config = Spyc::YAMLLoad('config.yml');


		if (!$res = json_decode(stripslashes($payload), true))
			die('your json was ;(');

		foreach ($res['commits'] as &$commit) {
			$this->process_commit($commit);
		}

	}
	
	function _post($url, $data, $header = 'Accept: application/json') {
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
		if ($errno = curl_errno($curl)) {
			$errmsg = curl_error($curl);
			echo $errno.' '.$errmsg."\n";
		}
		//$response = curl_getinfo($curl);
		curl_close($curl);
		return $content;

	}

	function process_commit($commit) {
		// need to clean this up to it constructs automatically off of an array
		$type = $this->config['type'];
		$token = $this->config['token'];
		// this is the user who owns the token (first 2 digits of the token), hackish way to do it now but it works, read below to fix
		$user = substr($token, 0, 2);

		// process this thing looking for a lighthouse like thing
		$message = $commit['message'];
	
		
		$files = "<b>Removed</b>:\n\t".implode(", ",$commit['removed']) ."\n<b>Added</b>\n\t". implode(", ",$commit['added']) ."\n<b>Modified</b>\n\t". implode(", ",$commit['modified']);

	    $url  = $this->config['submit_url'].'/'.$type.'s/add?token='.$token;


		$post = 'submitted=submitted&'.
				$type.'[name]='.urlencode($message).'+|+'.$commit['id'].' by '.urlencode($commit['author']['name']).'&'.
				$type.( $this->config['type'] != 'discussion' ? '[body]' : '[message]').'='.urlencode($commit['url'])."\n".urlencode("<b>Files:</b>\n".$files).
				($this->config['category'] > 0 ? '&'.$type.'[parent_id]='.$this->config['category'] : '').
				($type == 'ticket' ? '&ticket[assignees][0][]='.$user.'&ticket[assignees][1]='.$user : '');
			// assign whoever owns the token to the ticket, change this to use the yml config, 
			//    or eventually set it up to pull the user if it exists and use that user
			//    ie, /people, then search through the list


		// need to implement a real pluralize function above to resolve any idiocy which might occur
		$response = json_decode(stripslashes($this->_post($url, $post)), true);
		print_r($response);
		if ($type == 'ticket' || $type == 'checklist') {
		 	$complete_url = $this->config['submit_url'].'/objects/'.$response['id'].'/complete?token='.$token;
		 	$this->_post($complete_url, 'submitted=submitted');
		}

	}
	
		
}

if (count($_POST) <= 0)
	die ('Hi!');

$ob_file = fopen('development.log', 'a+');
ob_start();

$gh = new GithubActiveCollab($_POST['payload']);

fwrite($ob_file, ob_get_contents());
ob_end_clean();


?>