<?php
include("GithubActiveCollab.php");


if (count($_POST) <= 0)
	die ('Hi!');

$ob_file = fopen('development.log', 'a+');
ob_start();

$gh = new GithubActiveCollab($_POST['payload']);

fwrite($ob_file, ob_get_contents());
ob_end_clean();


?>
