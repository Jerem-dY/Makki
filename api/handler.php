<?php 

require("parser.php");
require("simple_html_dom/simple_html_dom.php");

class RequestHandler {

    function __construct(string $uri, string $request_config) {

        $parser = new RequestParser($request_config);

        $this->output       = "";
        $this->header       = array();
        $this->method       = $_SERVER['REQUEST_METHOD'];
        $this->mime         = $parser->parse_mime($_SERVER['HTTP_ACCEPT']);
        $this->lang         = $parser->parse_lang($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $this->request      = $parser->parse_uri($uri);
        $this->content_type = "text/html";

        if ($this->request['code'] != 200) {
            http_response_code($this->request['code']);
            exit;
        }

        if ($this->method == 'GET') {

            // Si aucune page exacte est précisée ('accueil.html' par exemple) cela signifie que c'est une ressource type image/css/etc. (voir url.json)
            if ($this->request['page'] == "") {
                
                if ($this->request['type'] == 'images') {
        
                    $img_path = "html/images/".$this->request['target'];
        
                    if (file_exists($img_path)) {
                        
                        $this->add_header("Content-Type", image_type_to_mime_type(exif_imagetype($img_path)));
                        $this->add_header("Content-Length", filesize($img_path));
        
                        $res = file_get_contents($img_path);
        
                        if ($res == false) {
                            echo "ERROR";
                        }
                        else {
                            $this->output .= $res;
                        }
                        
                    }
                    else {
                        http_response_code(404);
                    }
                    
                }
                else if ($this->request['type'] == 'styles') {
        
                    $css_path = "html/".$this->request['target'];
        
                    if (file_exists($css_path)) {
                        
                        $this->add_header("Content-Type", "text/css");
                        $this->add_header("Content-Length", filesize($css_path));
        
                        $res = file_get_contents($css_path);
        
                        if ($res == false) {
                            echo "ERROR";
                        }
                        else {
                            $this->output .= $res;
                        }
                        
                    }
                    else {
                        http_response_code(404);
                    }
                    
                }
            }
            else {
                if ($this->content_type == "text/html") {
                    if (file_exists("html/".$this->request['page'])) {
        
                        $html = file_get_html("html/".$this->request['page']);
        
                        // On adapte les attributs de la page afin de permettre au navigateur de formuler la bonne requête :
        
                        // On adapte la mise en page à la langue (sens de lecture et code de langue)
                        //TODO: penser à étudier le fait que même en français, certains mots restent en arabe (voir s'il est utile de changer le sens de lecture ponctuellement)
                        foreach($html->find('html') as $e)
                            $e->dir = $this->request['lang']['dir'];
                            $e->lang = $this->request['lang']['code'];
        
                        // Images
                        foreach($html->find('img') as $e)
                            $e->src = pathinfo($_SERVER['PHP_SELF'])['dirname'].'/'.$this->request['lang']['code'].'/'.$e->src;
                        
                        foreach($html->find('link') as $e) {
        
                            // Feuilles de style
                            if ($e->rel == "stylesheet") {
                                $e->href = pathinfo($_SERVER['PHP_SELF'])['dirname'].'/'.$this->request['lang']['code'].'/styles/'.$e->href;
                            }
                        }
        
                        
        
                        /* Test requête
                        foreach($html->find('p') as $e)
                        if ($e->class == "textetitre") {
                            $e->outertext = $request['target'];
                        }*/
        
                        $this->output .= $html;
                    }
                    else {
                        echo "NO SUCH PAGE BITCH";
                    }
                
                }
                else {
                    echo $this->content_type;
                }
            }
        }
        else if ($this->method == 'POST') {
            echo "THIS IS A POST REQUEST";
        }
        else if ($this->method == 'PUT') {
            echo "THIS IS A PUT REQUEST";
        }
        else if ($this->method == 'DELETE') {
            echo "THIS IS A DELETE REQUEST";
        }
        else {
            echo "WUT?!";
        }

    }

    function add_header(string $h, string $v): void {
        $this->header[$h] = $v;
    }

    function send_header(): void {

        foreach(array_keys($this->header) as $h) {
            header($h.': '.$this->header[$h], true);
        }
    }
}

?>