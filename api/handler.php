<?php 

require_once("parser.php");
require_once("db.php");
require_once("serializers/html.php");
require_once("upload.php");
require_once("vendor/autoload.php");

use PhpOffice\PhpSpreadsheet\IOFactory;

// TODO:
// - faire un fichier excel des traductions avec les 'class' HTML pour les transmettre à Léa
// - mettre au point le système de connexion (table dans la base de données, authentification) (note : penser à remplacer le bouton de connexion par un de déconnexion !)
// - voir avec Léa pour la page d'administration
// - faire un système d'upload de fichiers rdf pour alimenter la DB
// - voir avec Inès pour la mise au point d'une conversion xlsx (traductions) => rdf

class RequestHandler {

    function __construct(string $uri, string $request_config, string $server, string $headers) {

        $parser = new RequestParser($request_config);

        $this->output       = "";
        $this->header       = array();
        $this->method       = $_SERVER['REQUEST_METHOD'];
        $this->mime         = $parser->parse_mime($_SERVER['HTTP_ACCEPT']);
        $this->acc_lang     = $parser->parse_lang($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $this->lang         = array();
        $this->there        = $server;
        $this->request      = $parser->parse_uri($uri, $this->there);
        $this->content_type = "text/html";
        $this->db           = new DB("config/db.json");

        if (sizeof($this->request) == 0) {
            echo $uri;
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

        /** 
         * NEGOCIATION DE LANGUE 
         */

        // On commence par récupérer les langues disponibles
        // Il s'agit d'un premier tri ; pour chaque élément il faudra faire attention à effectuer les vérifications nécessaires
        #TODO: ne récupérer que les littérales qui appartiennent à l'interface
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

        /**
         * GESTION DES TYPES DE REQUÊTE
         */
        if ($this->method == 'GET') {

                
            if (isset($this->request['collection']) && $this->request['collection'] == 'images') {
    
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
                    echo $uri;
                    http_response_code(404);
                }
            }  
            else if (isset($this->request['collection']) &&  $this->request['collection'] == 'styles') {
    
                $css_path = "html/".$this->request['target'];
    
                if (file_exists($css_path)) {
                    
                    $ext = pathinfo($css_path, PATHINFO_EXTENSION);

                    if ($ext == "css") {
                        $mime = "text/css";
                    }
                    else if ($ext == "ttf") {
                        $mime = "font/ttf";
                    }

                    $this->add_header("Content-Type", $mime);
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
                    echo $uri;
                    http_response_code(404);
                }
                
            }
            else if (isset($this->request['collection']) &&  $this->request['collection'] == 'scripts') {
    
                $scr_path = "html/scripts/".$this->request['target'];
    
                if (file_exists($scr_path)) {
                    
                    $this->add_header("Content-Type", "application/javascript");
                    $this->add_header("Content-Length", filesize($scr_path));
    
                    $res = file_get_contents($scr_path);
    
                    if ($res == false) {
                        echo "ERROR";
                    }
                    else {
                        $this->output .= $res;
                    }
                    
                }
                else {
                    echo $uri;
                    http_response_code(404);
                }
                
            }
            else if (in_array(array('text', 'html'), $this->mime) || in_array(array('*', '*'), $this->mime)) {
                # Remplacer l'accès à une page pré-faite par un générateur de page qui correspond
                if (isset($this->request['collection']) && $this->request['collection'] == "lexique") {
                    if (isset($this->request['target'])) {
                        $page = "mot";
                    }
                    else if (isset($this->request['query'])) {
                        $page = "recherche";
                    }
                    else {
                        $page = "accueil";
                    }
                }
                else if (isset($this->request['collection']) && $this->request['collection'] == "administration") {
                    $page = "administration";
                }
                else {
                    $page = "accueil";
                }
    
                $html = new HTMLSerializer("config/pages.json", $this->db);
                $this->output .= $html->make_html($page, $this->there);
            }
            else {
                echo "OH NO!";
            }
        }
        else if ($this->method == 'POST') {
            
            if (isset($this->request['collection']) && $this->request['collection'] == "administration") {

                if (isset($_FILES["uploadtrad"])) {
                    $uploader = new FileUploader(array("xlsx"));

                    $uploader->upload($_FILES["uploadtrad"]);

                    $ttl = "@prefix dcterms: <http://purl.org/dc/terms/> .\r\n@prefix rdf:   <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\r\n@prefix rdfs:  <http://www.w3.org/2000/01/rdf-schema#> .\r\n";

                    foreach($uploader->get_filenames() as $file){

                        $spreadsheet = IOFactory::load($file);
                        $fname = explode('/', $file);
                        $fname = end($fname);
                        $f_no_ext = explode('.', $fname);
                        $f_no_ext = current($f_no_ext);
                        $translations = array();

                        $nb_sheets = $spreadsheet->getSheetCount();

                        for ($i = 0 ; $i < $nb_sheets ; $i++) {
                            $sheet = $spreadsheet->getSheet($i);
                            $lang = $sheet->getTitle();
                            
                            foreach($sheet->getRowIterator() as $row) {

                                $id = null;
                                $val = null;

                                foreach ($row->getCellIterator() as $cell) {

                                    if ($row->getRowIndex() > 1){

                                        $content = $cell->getValue();
                                        $col = $cell->getColumn();

                                        if ($col == "A") {
                                            $id = $content;
                                        }
                                        else if ($col == "B") {
                                            $val = addslashes($content);
                                        }

                                    }
                                }

                                if ($id != null && $val != null) {
                                    $full_id = "<traductions/".strtr($f_no_ext, "_ ", "--")."/".strtr($id, "_ ", "--").">";
                                    array_push($translations, array($full_id, $fname, $val, $id,$lang));
                                }
                            }

                        }

                        unset($spreadsheet);

                        foreach($translations as $tr) {
                            $full_id = $tr[0];
                            $f = $tr[1];
                            $val = $tr[2];
                            $html_id = $tr[3];
                            $lang = $tr[4];
                            $ttl .=  "$full_id\r\n\trdf:type <traductions> ;\r\n\tdcterms:date \"".date('d/m/Y H:i', time())."\" ;\r\n\tdcterms:language \"$lang\" ;\r\n\tdcterms:source \"$f\" ;\r\n\tdcterms:identifier \"$html_id\" ;\r\n\tdcterms:alternative \"\"\"$val\"\"\"@$lang .\r\n\r\n";
                        }

                        unset($translations);
                    }

                    $ttl_parser = ARC2::getTurtleParser();
                    $ttl_parser->parse($ttl);
                    $protocol = strtolower(current(explode('/',$_SERVER['SERVER_PROTOCOL']))) . "://";

                    # ==> https://github.com/semsol/arc2/issues/122
                    $this->db->store->insert(mb_convert_encoding($ttl, "UTF-8"), $protocol.$this->there, 0);

                    $uploader->delete();
                }
                else {
                    echo "bleuargh";
                }
            }
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