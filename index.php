<?php 

require("api/url.php");
require("api/simple_html_dom/simple_html_dom.php");


header("Access-Control-Allow-Origin: *");

// get request method
$method = $_SERVER['REQUEST_METHOD'];

if (isset($_SERVER["HTTP_CONTENT_TYPE"]) && $_SERVER["HTTP_CONTENT_TYPE"] != '') {
	$content_type = $_SERVER["HTTP_CONTENT_TYPE"];
}
else {
	$content_type = "text/html";
}

header('Content-Type: '.$content_type);

//echo $content_type . "<br/>";

$url_parser = new URLParser("config/url.json");
$uri = $_SERVER['REQUEST_URI'];

$request = $url_parser->parse_uri($uri);

if ($request['code'] != 200) {
	http_response_code(404);
	exit;
}

if ($method == 'GET') {

	// Si aucune page exacte est précisée ('accueil.html' par exemple) cela signifie que c'est une ressource type image/css/etc. (voir url.json)
	if ($request['page'] == "") {
		
		if ($request['type'] == 'images') {

			$img_path = "html/images/".$request['target'];

			if (file_exists($img_path)) {
				
				header("Content-Type: ".image_type_to_mime_type(exif_imagetype($img_path)));
				header("Content-Length: " . filesize($img_path));

				$res = readfile($img_path);

				if ($res == false) {
					echo "ERROR";
				}
				exit;
				
			}
			else {
				http_response_code(404);
			}
			
		}
		else if ($request['type'] == 'styles') {

			$css_path = "html/".$request['target'];

			if (file_exists($css_path)) {
				
				header("Content-Type: text/css");
				header("Content-Length: " . filesize($css_path));

				$res = readfile($css_path);

				if ($res == false) {
					echo "ERROR";
				}

				exit;
				
			}
			else {
				http_response_code(404);
			}
			
		}
	}
	else {
		if ($content_type == "text/html") {
			if (file_exists("html/".$request['page'])) {

				$html = file_get_html("html/".$request['page']);

				// On adapte les attributs de la page afin de permettre au navigateur de formuler la bonne requête :

				// On adapte la mise en page à la langue (sens de lecture et code de langue)
				foreach($html->find('html') as $e)
					$e->dir = $request['lang']['dir'];
					$e->lang = $request['lang']['code'];

				// Images
				foreach($html->find('img') as $e)
    				$e->src = pathinfo($_SERVER['PHP_SELF'])['dirname'].'/'.$request['lang']['code'].'/'.$e->src;
				
				foreach($html->find('link') as $e) {

					// Feuilles de style
					if ($e->rel == "stylesheet") {
						$e->href = pathinfo($_SERVER['PHP_SELF'])['dirname'].'/'.$request['lang']['code'].'/styles/'.$e->href;
					}
				}

				

				// Test requête
				foreach($html->find('p') as $e)
				if ($e->class == "textetitre") {
					$e->outertext = $request['target'];
				}

				echo $html;
			}
			else {
				echo "NO SUCH PAGE BITCH";
			}
		
		}
		else {
			echo $content_type;
		}
	}
}
else if ($method == 'POST') {
	echo "THIS IS A POST REQUEST";
}
else if ($method == 'PUT') {
	echo "THIS IS A PUT REQUEST";
}
else if ($method == 'DELETE') {
	echo "THIS IS A DELETE REQUEST";
}
else {
	echo "WUT?!";
}

?>