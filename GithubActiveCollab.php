<?php

define("DEBUG", 0);

include ('spyc/spyc.php');


class GithubActiveCollab {


	protected $config;
	protected $keywords = array(
		'responsible' => '((\".*\")|(^[^\s]+$))', // anything quoted or, then a single word
		'tagged' => '((^[^\s]+$)((\,)(^[^\s]+$))*)', // ^-- followed by , and ^--
		'milestone' => '((\".*\")|next|none)', // anything quote, or next, or none
		'state' => '(complete|open|star|unstar|subscribe|unsubscribe)' // any of the following
		);
	// milestones cache
	protected $milestones = array();

	// objects cache
	protected $objects = array();

	function __construct($payload) {

		$this->config = Spyc::YAMLLoad('config.yml');


		if (DEBUG == 1)
			return;

		if (!$res = json_decode(stripslashes($payload), true))
			die('your json was ;(');
		
		
		
		
		foreach ($res['commits'] as &$commit) {
			$this->process_commit($commit);
		}
		

	}
	
	function _request($url, $data = '', $method = 1, $header = 'Accept: application/json') {
		$options = array(
			CURLOPT_RETURNTRANSFER 	=> true,
			CURLOPT_HEADER			=> false,
			CURLOPT_CONNECTTIMEOUT 	=> 120,
	        CURLOPT_TIMEOUT        	=> 120,
			CURLOPT_POST			=> $method,
			CURLOPT_POSTFIELDS		=> 'submitted=submitted&'.$data,
			CURLOPT_SSL_VERIFYHOST 	=> 0,
	        CURLOPT_SSL_VERIFYPEER	=> false,
			CURLOPT_HTTPHEADER 		=> array($header),
	        CURLOPT_VERBOSE       	=> (DEBUG == 1 ? 0 : 0)		
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


	
	
	// this function returns only the msg without the actions to modify objects (through milestones, states, etc)
	//   it modifies the objects as it finds them
	function parse_commit_message($message) {

		// see if the string is properly formed with proper keywords
		$match = '/([^\[\]]+)(\[\#([0-9]+)\s([^\[\]]+)\])*/i'; // anything quoted, anything without a space, or anything a , without spaces
		$data_match = '/(?<key>'.implode('|',array_keys($this->keywords)).'):(?<data>(\".*\")|(([\w,]+)(\b)*))/i'; // anything quoted, anything without a space, or anything a , without spaces
		preg_match_all($match,$message,$matches);
		//print_r($matches)."hi\n";

		// loop over each potential id, ie [#18 ...] [#20 ...]
		while(list($key,$value) = each($matches[3])) {
			//print_r( $matches[3][$key]);
			preg_match_all($data_match,$matches[4][$key],$keywords);
			//print_r($matches2);

			// loop over each potential keyword, ie [#18 responsible:.. tagged:..]
			while(list($k,$v) = each($keywords['key'])) {
				if (preg_match($this->keywords[$v],$keywords['data'][$k])) {
					eval('$this->set_'.$v.'($value,"'.addslashes($keywords['data'][$k]).'");');
					//echo "\n".'messages: '.$matches[2][0].' id:'.$value.' keyword:'.$v.' data:'.$keywords['data'][$k]."\n";
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
	
	function get_people() {
		$url = $this->config['submit_url'].'/people?token='.$this->config['token'];
		return $this->_request($url, '', 0);
	}
	
	function get_milestones() {
		// if it isn't cached
		if (count($this->milestones) > 0)
			return;
		$url  = $this->config['submit_url'].'/projects/'.$this->config['project'].'/milestones?token='.$this->config['token'];
		// cached
		$this->milestones = $this->_request($url, '', 0);
		// sort it by ids so we can figure out which id is next
		foreach ($this->milestones as $k => $v) {
			$id[$k] = $v['id'];
		}
		array_multisort($id, SORT_DESC, $this->milestones);
		//print_r($this->milestones);	
	}
	
	// fetch an object using an object actual id
	function get_object($id) {
		if (count($this->objects[$id]) > 0)
			return;
		$url  = $this->config['submit_url'].'/projects/'.$this->config['project'].'/'.$this->config['type'].'s/'.$id.'?token='.$this->config['token'];
		echo "object id original id: $id, fetch url: $url\n";
		$this->objects[$id] = $this->_request($url, '', 0);
		return $this->_request($url, '', 0);
	}
	
	function set_tagged($id,$tags) {
		$url  = $this->config['submit_url'].'/projects/'.$this->config['project'].'/'.$this->config['type'].'s/'.$id.'/edit?token='.$this->config['token'];
		$post = $this->config['type'].'[tags]='.$tags;
		echo 'setting tags'.$url." + $post\n";
		$this->_request($url,$post);
	}
	
	function set_milestone($id,$milestone) {
		// if milestone is text, find it
		// if milestone is next, find the next one
		// if milestone is none, remove it 
		$this->get_milestones();
		$this->get_object($id);
		$milestone_id = 0;
		switch ($milestone) {
			case "next":
				// find the current id, then go to the next array id
				while (list($k,$v) = each($this->milestones)) {
					if (!is_array($this->objects[$id])) // if we received an error from the object
						break;
					if ($k != 0)
						if ($v['id'] == $this->objects[$id]['milestone_id'])
							$milestone_id = $this->milestones[$k-1]['id']; // k-1 because it's setup in desc. order
				}
				break;
			
			case "none":
				$milestone_id = 0;
				break;
				
			default: // where we have to find the milestone by it's name
				foreach ($this->milestones as $v) {
					if (strstr($milestone, $v['name'])) $milestone_id = $v['id'];
				}
			
		}
		//print_r($this->milestones);
		$url  = $this->config['submit_url'].'/projects/'.$this->config['project'].'/'.$this->config['type'].'s/'.$id.'/edit?token='.$this->config['token'];
		$post = $this->config['type'].'[milestone_id]='.$milestone_id;
		echo 'setting milestone: '.$milestone.' '.$url." + $post\n";
		//$this->_request($url,$post);
	}
	
	function set_responsible($id,$person) {
		// implement me
	}

	function set_state($id,$state) {
		// need to fetch the item's object id since that's how it works!
		$this->get_object($id);
		$url  = $this->config['submit_url'].'/projects/'.$this->config['project'].'/objects/'.$this->objects[$id]['id'].'/'.$state.'?token='.$this->config['token'];
		echo 'setting state: '.$url."\n";
		//$this->_request($url,'');
	}
	

	function process_commit($commit) {
		// need to clean this up to it constructs automatically off of an array
		$type = $this->config['type'];
		$token = $this->config['token'];
		// this is the user who owns the token (first 2 digits of the token), hackish way to do it now but it works, read below to fix
		$user = substr($token, 0, 2);
		$project = $this->config['project'];

		// process this thing looking for a lighthouse like thing
		$message = $this->parse_commit_message($commit['message']);
	
		
		$files = "<b>Removed</b>:\n\t".implode(", ",$commit['removed']) ."\n<b>Added</b>\n\t". implode(", ",$commit['added']) ."\n<b>Modified</b>\n\t". implode(", ",$commit['modified']);

	    $url  = $this->config['submit_url'].'/projects/'.$project.'/'.$type.'s/add?token='.$token;


		$post = $type.'[name]='.urlencode($message).'+|+'.$commit['id'].' by '.urlencode($commit['author']['name']).'&'.
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
		 	$this->_request($complete_url);
		}

	}
	
		
}


?>