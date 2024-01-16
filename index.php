<?php 

require("api/handler.php");

header("Access-Control-Allow-Origin: *");

$request_config = "config/url.json";
$uri = $_SERVER['REQUEST_URI'];

$handler = new RequestHandler($uri, $request_config);
$handler->send_header();

if ($handler->output != '') {
	echo $handler->output;
}



?>