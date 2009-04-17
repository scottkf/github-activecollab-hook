<?php


include ('spyc/spyc.php');


class GithubActiveCollab {


	protected $config;
	protected $keywords = array(
		'responsible' => '((\".*\")|(^[^\s]+$))', // anything quoted or, then a single word
		'tagged' => '((^[^\s]+$)((\,)(^[^\s]+$))*)', // ^-- followed by , and ^--
		'milestone' => '((\".*\")|next|none)', // anything quote, or next, or none
		'state' => '(complete|open|star|unstar|subscribe|unsubscribe)' // any of the following
		);

	function __construct($payload) {

		$this->config = Spyc::YAMLLoad('config.yml');


		// if (!$res = json_decode(stripslashes($payload), true))
		// 	die('your json was ;(');




		// foreach ($res['commits'] as &$commit) {
		// 	$this->process_commit($commit);
		// }
		
		//print_r($this->get_people());

	}
	
	function _request($url, $data, $method = 1, $header = 'Accept: application/json') {
		$options = array(
			CURLOPT_RETURNTRANSFER 	=> true,
			CURLOPT_HEADER			=> false,
			CURLOPT_CONNECTTIMEOUT 	=> 120,
	        CURLOPT_TIMEOUT        	=> 120,
			CURLOPT_POST			=> $method,
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
		return json_decode(stripslashes($content),true);

	}

	function get_people() {
		$url = $this->config['submit_url'].'/people?token='.$this->config['token'];
		return $this->_request($url, '', 0);
	}
	
	function parse_commit_message($message) {

		// see if the string is properly formed with proper keywords
		$match = '/([^\[\]]+)\[\#([0-9]+)\s([^\[\]]+)\]/i'; // anything quoted, anything without a space, or anything a , without spaces
		$data_match = '/(?<key>'.implode('|',array_keys($this->keywords)).'):(?<data>(\".*\")|(([\w,]+)(\b)*))/i'; // anything quoted, anything without a space, or anything a , without spaces
		preg_match_all($match,$message,$matches);
		//print_r($matches);

		// loop over each potential id, ie [#18 ...] [#20 ...]
		while(list($key,$value) = each($matches[2])) {
			//print_r( $matches[3][$key]);
			preg_match_all($data_match,$matches[3][$key],$keywords);
			//print_r($matches2);

			// loop over each potential keyword, ie [#18 responsible:.. tagged:..]
			while(list($k,$v) = each($keywords['key'])) {
//				echo "key$k value$v\n";
				if (preg_match($this->keywords[$v],$keywords['data'][$k])) {
					eval('$this->set_'.$v.'($value,"'.str_replace('"','',$keywords['data'][$k]).'");');
					echo "\n".'messages: '.$matches[1][0].' id:'.$value.' keyword:'.$v.' data:'.$keywords['data'][$k]."\n";
				}
			}
		}

		//$matches[0] is the full match
		// [1] is the commit msg
		// [2] is the id
		// [3] is the keyword
		// [4] is the keyword's data
		return $matches[1][0];
	}
	
	function set_tagged($id,$tags) {
		$url  = $this->config['submit_url'].'/projects/'.$this->config['project'].'/'.$this->config['type'].'s/'.$id.'/edit?token='.$this->config['token'];
		$post = 'submitted=submitted&'.$this->config['type'].'[tags]='.$tags;
		echo $url." + $post\n";
		//$this->_request($url,$post);
	}
	
	function set_milestone($id,$milestone) {
		
	}
	
	function set_responsible($id,$person) {
		
	}

	function set_state($id,$state) {
		
	}

	function process_commit($commit) {
		// need to clean this up to it constructs automatically off of an array
		$type = $this->config['type'];
		$token = $this->config['token'];
		// this is the user who owns the token (first 2 digits of the token), hackish way to do it now but it works, read below to fix
		$user = substr($token, 0, 2);
		$project = $this->config['project'];

		// process this thing looking for a lighthouse like thing
		$message = parse_commit_message($commit['message']);
	
		
		$files = "<b>Removed</b>:\n\t".implode(", ",$commit['removed']) ."\n<b>Added</b>\n\t". implode(", ",$commit['added']) ."\n<b>Modified</b>\n\t". implode(", ",$commit['modified']);

	    $url  = $this->config['submit_url'].'/projects/'.$project.'/'.$type.'s/add?token='.$token;


		$post = 'submitted=submitted&'.
				$type.'[name]='.urlencode($message).'+|+'.$commit['id'].' by '.urlencode($commit['author']['name']).'&'.
				$type.( $this->config['type'] != 'discussion' ? '[body]' : '[message]').'='.urlencode($commit['url'])."\n".urlencode("<b>Files:</b>\n".$files).
				($this->config['category'] > 0 ? '&'.$type.'[parent_id]='.$this->config['category'] : '').
				($type == 'ticket' ? '&ticket[assignees][0][]='.$user.'&ticket[assignees][1]='.$user : '');
			// assign whoever owns the token to the ticket, change this to use the yml config, 
			//    or eventually set it up to pull the user if it exists and use that user
			//    ie, /people, then search through the list


		// need to implement a real pluralize function above to resolve any idiocy which might occur
		$response = $this->_request($url, $post);
		//print_r($response);
		if ($type == 'ticket' || $type == 'checklist') {
		 	$complete_url = $this->config['submit_url'].'/projects/'.$project.'/objects/'.$response['id'].'/complete?token='.$token;
		 	$this->_request($complete_url, 'submitted=submitted');
		}

	}
	
		
}


?>