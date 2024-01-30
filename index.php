<?php 

require("api/handler.php");

$request_config = "config/url.json";
$uri = $_SERVER['REQUEST_URI'];
$headers = "config/headers.json";

$handler = new RequestHandler($uri, $request_config, $headers);
$handler->send_header();

if ($handler->output != '') {
	echo $handler->output;
}



?>