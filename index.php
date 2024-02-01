<?php 

require("api/handler.php");

$request_config = "config/url.json";
$uri = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$headers = "config/headers.json";
$server = "config/server.txt";

http_response_code(200);
$handler = new RequestHandler($uri, $request_config, file_get_contents($server), $headers);
$handler->send_header();

if ($handler->output != '') {
	echo $handler->output;
}



?>