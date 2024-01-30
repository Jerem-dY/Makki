<?php 

require("parser.php");
require("db.php");
require("simple_html_dom/simple_html_dom.php");

class RequestHandler {

    function __construct(string $uri, string $request_config, string $headers) {

        $parser = new RequestParser($request_config);

        $this->output       = "";
        $this->header       = array();
        $this->method       = $_SERVER['REQUEST_METHOD'];
        $this->mime         = $parser->parse_mime($_SERVER['HTTP_ACCEPT']);
        $this->acc_lang     = $parser->parse_lang($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $this->lang         = array();
        $this->request      = $parser->parse_uri($uri);
        $this->content_type = "text/html";
        $this->there        = pathinfo($_SERVER['PHP_SELF'])['dirname'];
        $this->db           = new DB("config/db.json");

        if (sizeof($this->request) == 0) {
            http_response_code(404);
            exit;
        }

        // On s'occupe de charger les headers par défaut
        $headers = file_get_contents($headers);

        if ($headers != false) {
            $data = json_decode($headers, true);

            foreach(array_keys($data) as $h) {
                $this->add_header($h, $data[$h]);
            }
        }

        /* Négociation de langue */
        $literals = $this->db->query("select distinct ?literal {?s ?p ?literal filter isLiteral(?literal)}"); # On sélectionne toutes les valeurs littérales
        $lang_available = array();

        foreach($literals['result']['rows'] as $l) {
            if (isset($l['literal lang'])) {
                $lang_available[$l['literal lang']] = 1;
            }
        }

        $lang_available = array_keys($lang_available);

        // Ordre de priorité : langue dans l'URI puis langues dans Accept-Language puis langue par défaut (le français)
        if(isset($this->request['lang']) and in_array($this->request['lang'], $lang_available)) {
            array_push($this->lang, $this->request['lang']);
        }
        foreach($this->acc_lang as $l) { # Langues dans l'en-tête HTTP 'Accept-Language'
            if (in_array($lang_available, $l)) {
                array_push($this->lang, $l);
            }
        }

        array_push($this->lang, "fr"); # Langue par défaut

        /*if ($this->method == 'GET') {

            // Si aucune page exacte n'est précisée ('accueil.html' par exemple) cela signifie que c'est une ressource type image/css/etc. (voir url.json)
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
        }*/

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