<?php 

require_once("parser.php");
require_once("db.php");
require_once("upload.php");
require_once("jwt.php");
require_once("vendor/autoload.php");

// On importe tous les sérialiseurs du dossier
$serz = dirname(__FILE__).DIRECTORY_SEPARATOR."serializers";
foreach (scandir($serz) as $filename) {
    $path = $serz . DIRECTORY_SEPARATOR . $filename;
    if (is_file($path)) {
        require_once $path;
    }
}

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Classe coordonnant tout le traitement des requêtes ainsi que les réponses
 */
class RequestHandler {

    const RESOURCES_COLL = array("images", "scripts", "styles", "fonts", "clefs");
    const PAGE_SIZE_MAX      = 30;
    const PAGE_SIZE_MIN      = 1;

    /**
     * @param string $url l'url complète demandée par l'utilisateur, sans le protocole
     * @param string $server l'adresse de base du site (équivalent à l'adresse de la page d'accueil)
     * @param string $headers Chemin vers le fichier référençant les en-têtes à mettre à chaque réponse
     */
    function __construct(string $url, string $server, string $headers) {

        // On prépare le parser (URI, query, etc.) et on défini la variable globale qui indique le serveur (utilisée notamment par les JWT)
        $parser = new RequestParser;
        $GLOBALS['iss'] = $_SERVER['HTTP_HOST'];

        // On prépare toutes les variables nécessaires au traitement de la requête
        $this->output           = "";
        $this->header           = array();
        $this->method           = $_SERVER['REQUEST_METHOD'];
        $this->mime             = $parser->parse_mime($_SERVER['HTTP_ACCEPT']);
        $this->acc_lang         = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $parser->parse_lang($_SERVER['HTTP_ACCEPT_LANGUAGE']) : array();
        $this->lang             = array();
        $this->there            = $server;
        $this->protocol         = strtolower(current(explode('/',$_SERVER['SERVER_PROTOCOL']))) . "://";
        $this->request          = $parser->parse_uri($url, $this->there);
        $this->uri              = $url;
        $this->content_type     = "text/html";
        $this->db               = new DB("config/db.json", "config/db.sql", "config/prefixes.ttl");

        // On récupère les informations sur la session de l'utilisateur (ou son absence)
        $session = $this->retrieve_session();
        $this->auth      = $session[0];
        $this->id        = $session[1]['usr'];
        $this->session   = $session[1]['sid'];

        // On s'occupe de charger les headers par défaut
        $headers = file_get_contents($headers);

        // On désactive l'affichage des erreurs de type "Notice" (mineures) car elles gênent l'uplooad (des erreurs SQL sans conséquence)
        error_reporting(E_ALL & ~E_NOTICE);

        if ($headers != false) {
            $data = json_decode($headers, true);

            foreach(array_keys($data) as $h) {
                $this->add_header($h, $data[$h]);
            }
        }

        // Si le tableau contenant les éléments décortiqués de la requête est vide, on redirige l'utilisateur vers la page d'accueil
        if (sizeof($this->request) == 0) {
            $this->redirect("", "badreq_err", 400);
            return;
        }

        /** 
         * NEGOCIATION DE LANGUE 
         */

        // On commence par récupérer les langues disponibles
        // Il s'agit d'un premier tri ; pour chaque élément il faudra faire attention à effectuer les vérifications nécessaires
        $rows = $this->db->query("SELECT ?language WHERE { ?in dcterms:source ?file . ?in dcterms:language ?language . ?in dcterms:date ?date } GROUP BY ?language");
        $lang_available = array();

        foreach($rows['result']['rows'] as $l) {
            if (isset($l['language'])) {
                array_push($lang_available, $l['language']);
            }
        }

        // Ordre de priorité : langue dans l'URI puis langues dans Accept-Language puis langue par défaut (le français)
        if(isset($this->request['lang']) and in_array($this->request['lang'], $lang_available)) {
            array_push($this->lang, $this->request['lang']);
        }
        foreach($this->acc_lang as $l) { # Langues dans l'en-tête HTTP 'Accept-Language'
            $selected = current(explode("-", $l[0]));
            if (in_array($selected, $lang_available) && !in_array($selected, $this->lang)) {
                array_push($this->lang, $selected);
            }
        }

        $this->lang = array_merge($this->lang, array_diff($lang_available, $this->lang));
        
        // On récupère le sens de lecture `dir` associé à chaque langue
        $this->lang = array_map(
            function ($value) {
                $res = $this->db->query("SELECT ?dir WHERE { ?in dcterms:alternative ?txt . ?in <".$this->protocol.$this->there."traductions/dir> ?dir FILTER( lang(?txt) = \"$value\" ) } GROUP BY ?dir");
                return [$value, $res['result']['rows'][0]['dir']];
            }, 
            $this->lang
        );

        /**
         * GESTION DES TYPES DE REQUÊTE
         */

        // Les requêtes GET sont vouées à fournir une représentation (HTML, XML, JSON, etc.) d'une ressource (page web, résultats de recherche, ...)
        // Elles ne modifient pas l'ETAT du site, i.e. elles ne changent pas les données de la DB ou les fichiers sur le serveur
        if ($this->method == 'GET') {
            

            if (isset($this->request['collection']) && in_array($this->request['collection'], self::RESOURCES_COLL)) {

                $collection = $this->request['collection'];

                if ($collection == "clefs") {

                    $rs_path = "secure/public_keys.json";
                    $mime = "application/json";

                    if (!is_file($rs_path)) {
                        make_keyset();
                    }

                    if (isset($this->request['target'])) {

                        $kid = $this->request['target'];
                        $keys = json_decode(file_get_contents($rs_path), true);

                        foreach($keys['keys'] as $k) {
                            if ($k['kid'] == $kid) {
                                $this->output = json_encode($k);
                                $this->add_header("Content-Type", $mime);
                                $this->add_header("Content-Length", strlen($this->output));
                                return;
                            }
                        }
                        
                        http_response_code(404);
                        return;

                    }
                    
                }
                else {
                    $rs_path = "html/".$collection."/".$this->request['target'];

                    if($collection == 'images') {
                        $mime = image_type_to_mime_type(exif_imagetype($rs_path));
                    }
                    else if($collection == 'fonts') {
                        $mime = "font/ttf";
                    }
                    else if($collection == 'styles') {
                        $mime = "text/css";
                    }
                    else if($collection == 'scripts') {
                        $mime = "application/javascript";
                    }
                }

                http_response_code($this->cache($rs_path, $mime));
                return;
            }  
            else {

                $available = json_decode(file_get_contents("config/mimes.json"), true);
                $raw = false;

                if (isset($this->request['query']['mime'])) {
                    array_unshift($this->mime, $this->request['query']['mime']);
                }

                foreach($this->mime as $m) {
                    $m = implode("/", $m);

                    if (array_key_exists($m, $available)) {
                        $selected = $available[$m];
                        $builder = new $selected["classe"]($this->db);
                        $this->add_header("Content-Type", $m);
                        $raw = $selected["raw"];

                        if ($m != "text/html" && $m != "*/*")
                            $this->add_header("Content-Disposition", "attachment");
                        break;
                    }
                }

                if(!isset($builder)) {
                    http_response_code(400);
                    return;
                }

                if (isset($this->lang[0]) && isset($this->lang[0][0])) {
                    $themes = $this->db->query("SELECT DISTINCT ?subject WHERE {
                        
                        ?in dcterms:subject ?subject .
                        FILTER (lang(?subject) = \"". $this->lang[0][0] ."\")
                        
                        }");

                    if (isset($themes['result'])) {
                        $themes = array_map(function($item) {
                            return $item['subject'];
                        }, $themes['result']['rows']);
                    }
                    else {
                        $themes = array();
                    }
                }
                else {
                    $themes = array();
                }
                


                if (isset($this->request['collection']) && $this->request['collection'] == "lexique") {
                    if (isset($this->request['target'])) {
                        $page = "mot";
                        $word_data = $this->get_data(array("title" => [$this->request['target']]), $raw);

                    }
                    else if (isset($this->request['query'])) {
                        $page = "mot";
                        $word_data = $this->get_data($this->request['query'], $raw);
                    }
                    else {
                        //TODO: Afficher une liste de tous les mots dans l'ordre alphabétique
                        $page = "mot";
                        $word_data = $this->get_data(array(), $raw);
                    }
                }
                else if (isset($this->request['collection']) && $this->request['collection'] == "recherche") {
                    $page = "recherche";
                }
                else if (isset($this->request['collection']) && $this->request['collection'] == "contact") {
                    $page = "contact";
                }
                else if (isset($this->request['collection']) && $this->request['collection'] == "administration") {

                    if (!$this->auth) {
                        $this->unauthorized();
                        return;
                    }

                    $page = "administration";
                }
                else if (isset($this->request['collection']) && $this->request['collection'] == "licences") {
                    $page = "licences";
                }
                else if (isset($this->request['collection'])) {
                    $this->redirect("", "badreq_err", 400);
                    return;
                }
                else {
                    $page = "accueil";
                }
                
                $token = $this->make_nonce();

                $this->output .= $builder->make($page, $this->there, $this->lang, $this->request, $this->protocol, $this->auth, $token, isset($word_data) ? $word_data : array(), $themes, array_keys($available));
            }
        }
        else if ($this->method == 'POST') {

            if (!isset($_POST['nonce']) || !isset($_COOKIE['makki_nonce'])) {
                $this->unauthorized();
                return;
            }

            $cookie = $this->retrieve_nonce($_COOKIE['makki_nonce']);
            $frm = $this->retrieve_nonce(urldecode($_POST['nonce']));

            // On vérifie que :
            // si connecté, le champ `usr` du cookie nonce doit correspondre au champ `sid` du cookie d'authentification
            // le nonce doit être présent dans les donneés du formulaire
            // le champ `sid` du nonce du formulaire doit correspondre à celui du cookie 
            if(($this->auth && $cookie['usr'] != $this->session) || $frm['sid'] != $cookie['sid']) {
                $this->unauthorized();
                return;
            }

            if (isset($_POST['bouton_co'])) {

                $mdp = base64_decode($_POST['mdp']);
                $login = base64_decode($_POST['utilisateur']);

                $id = $this->db->check_credentials($login, $mdp);

                if ($this->db->check_admin_id($id)) {
                    $this->make_session($id);
                }

                $this->redirect($this->protocol.$this->uri, "co_msg");
                return;
            }
            else if (isset($_POST['bouton_deco'])) {
                $this->disconnect();
                return;
            }
            else if (isset($this->request['collection']) && $this->request['collection'] == "contact" && isset($_POST["contact"])){

                /** Validation */
                if(!(
                isset($_POST["name"]) && $_POST["name"] != "" && 
                isset($_POST["objet"]) && $_POST["objet"] != "" && 
                isset($_POST["email"]) && $_POST["email"] != "" && 
                isset($_POST["msg"]) && $_POST["msg"] != ""
                )) {
                    //TODO: communiquer le message d'erreur dans la query
                    $this->redirect("", "contact_err", 400);
                }

                $name = $_POST["name"];
                $obj = $_POST["objet"];
                $uni = isset($_POST["uni"]) ? " (".$_POST["uni"].")" : "";
                $mail = $_POST["email"];
                $msg = $_POST["msg"];

                $sub = $obj . $uni;

                $stt = $this->db->pdo->prepare("INSERT INTO `contact` (name, email, subject, message) VALUES (:name, :email, :subject, :message);");
                $stt->bindParam(":name", $name);
                $stt->bindParam(":email", $mail);
                $stt->bindParam(":subject", $sub);
                $stt->bindParam(":message", $msg);
                $stt->bindParam(":name", $name);

                $stt->execute();

                $this->redirect("", "contact_msg");
            }
            else if (isset($this->request['collection']) && $this->request['collection'] == "administration") {

                if(!$this->validate_tokens()) {
                    $this->unauthorized();
                    return;
                }    

                if (isset($_FILES["uploadtrad"])) {
                    $uploader = new FileUploader(array("xlsx"), $this->id, 2500000);


                    if (!$uploader->upload($_FILES["uploadtrad"])) {
                        $this->redirect($this->protocol.$this->uri, "upload_err", 400);
                        return;
                    }

                    $ttl = "";

                    foreach($uploader->get_filenames() as $file){

                        $spreadsheet = IOFactory::load($file);
                        $fname = explode('/', $file);
                        $fname = end($fname);
                        $f_no_ext = explode('.', $fname);
                        $f_no_ext = current($f_no_ext);
                        $translations = array();

                        $pre_q = "SELECT ?out WHERE {?in dcterms:source ?out . FILTER(REGEX(?out, \"$f_no_ext( \([0-9]+\)|)\"))} GROUP BY ?out";
                        $rows = $this->db->query($pre_q);

                        $occs = count($rows['result']['rows']);
                        if ($occs > 0) {
                            $f_no_ext .= " ($occs)";
                        }

                        $nb_sheets = $spreadsheet->getSheetCount();

                        for ($i = 0 ; $i < $nb_sheets ; $i++) {
                            $sheet = $spreadsheet->getSheet($i);
                            $lang = $sheet->getTitle();
                            $orientation = $sheet->getCell("E1")->getValue();
                            $orientation = in_array($orientation, ['ltr', 'rtl']) ? $orientation : 'ltr';
                            
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
                                    $full_id = "<traductions/".strtr($f_no_ext, "_ ", "--")."/".strtr($id, "_ ", "--")."/$lang>";
                                    array_push($translations, array($full_id, $f_no_ext, $val, $id, $lang, $orientation));
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
                            $orientation = $tr[5];

                            $ttl .=  "$full_id 
                                dcterms:date \"".date('d/m/Y H:i', time())."\" ; 
                                dcterms:language \"$lang\" ; 
                                dcterms:source \"$f\" ; 
                                dcterms:identifier \"$html_id\" ; 
                                <traductions/dir> \"$orientation\" ;
                                dcterms:alternative \"\"\"$val\"\"\"@$lang . ";
                        }

                        unset($translations);
                    }

                    # ==> https://github.com/semsol/arc2/issues/122
                    $this->db->insert($ttl, $this->protocol.$this->there);

                    $uploader->delete();
                    $this->redirect($this->protocol.$this->uri, "upload_msg");
                }
                else if (isset($_FILES["uploaddata"])) {
                    $uploader = new FileUploader(array("ttl", ".rdf", ".xml"),$this->id, 2500000);

                    if (!$uploader->upload($_FILES["uploaddata"])) {
                        $this->redirect($this->protocol.$this->uri, "upload_err", 400);
                        return;
                    }

                    foreach($uploader->get_filenames() as $file){

                        $f_name = $f_no_ext = explode(".", basename($file))[0];

                        $pre_q = "SELECT ?out WHERE {?in dcterms:source ?out . FILTER(REGEX(?out, \"$f_no_ext( \([0-9]+\)|)\"))} GROUP BY ?out";
                        $rows = $this->db->query($pre_q);

                        $occs = count($rows['result']['rows']);
                        if ($occs > 0) {
                            $f_no_ext .= " ($occs)";
                        }

                        $contents = mb_convert_encoding(file_get_contents($file), "UTF-8");

                        if ($f_no_ext != $f_name) {
                            $contents = str_replace($f_name, $f_no_ext, $contents);
                        }

                        $this->db->insert($contents, $this->protocol.$this->there);

                    }

                    $uploader->delete();
                    $this->redirect($this->protocol.$this->uri, "upload_msg");
                }
            }
        }
        else if ($this->method == 'DELETE') {

            if(!$this->validate_tokens() || !$this->auth) {
                $this->unauthorized();
                return;
            }

            parse_str(file_get_contents('php://input'), $_DELETE);

            if (isset($_DELETE['type']) ){

                if ($_DELETE['type'] == "delete_trad" && isset($_DELETE['date']) && isset($_DELETE['file']) && isset($_DELETE['lang'])) {

                    $date = $_DELETE['date'];
                    $file = $_DELETE['file'];
                    $lang = $_DELETE['lang'];

                    $this->db->query("DELETE {
                        ?truc dcterms:date \"$date\" .
                        ?truc dcterms:language \"$lang\" .
                        ?truc dcterms:source \"$file\" .
                        ?truc <traductions/dir> ?dir .
                        ?truc dcterms:alternative ?txt .
                    }
                    
                    WHERE {
                    
                      ?truc dcterms:date \"$date\" .
                      ?truc dcterms:language \"$lang\" .
                      ?truc dcterms:source \"$file\" .
                      ?truc dcterms:alternative ?txt .
                      ?truc <traductions/dir> ?dir .
                      FILTER( lang(?txt) = \"$lang\")
                    
                    }");
                }
                else if ($_DELETE['type'] == "delete_data" && isset($_DELETE['file'])) {
                    
                    $file = $_DELETE['file'];

                    /** On commence par compter combien de sources a chaque définition issue de ce fichier.
                     * Cela permet d'éviter de tout supprimer si une définition est aussi issue d'un autre fichier.
                     * Ainsi, on supprime le lien entre le fichier et ces définitions, et l'on a alors les mains libre pour supprimer entièrement ce qui est
                     * uniquement issu de ce fichier.
                     */
                    $pre_q = "SELECT DISTINCT ?el (COUNT(?src2) AS ?nb) WHERE { 
                    
                      ?el dcterms:source ?src, ?src2 .
                      FILTER (?src = \"$file\")
                    
                    } GROUP BY ?el";
                    $res = $this->db->query($pre_q);
                    
                    $unlink = array();
                    foreach($res['result']['rows'] as $row) {
                        if ($row["nb"] > 1) {
                            array_push($unlink, $row["el"]);
                        }
                    }

                    $del_assoc = "DELETE {
                        <".implode("> dcterms:source \"$file\" .<", $unlink)."> dcterms:source \"$file\" .
                    }";

                    $this->db->query($del_assoc);

                    $del_all = "DELETE {
                        ?in ?pred ?out .
                    }
                    
                    WHERE {
                        ?in dcterms:source \"$file\" .
                        ?in ?pred ?out .
                    }";

                    $this->db->query($del_all);
                }
                else if ($_DELETE['type'] == "delete_contact" && isset($_DELETE['file'])) {

                    $id = $_DELETE['file'];

                    $stt = $this->db->pdo->prepare("DELETE FROM `contact` WHERE contact_id=$id");
                    if (!$stt->execute()) {
                        http_response_code(400);
                        return;
                    }
                }
            }
        }
        else {
            echo "WUT?!";
        }

    }

    /**
     * Ajoute un en-tête HTTP
     * 
     * @param string $h Le nom de l'en-tête (sans `:`)
     * @param string $v La valeur de l'en-tête
     */
    function add_header(string $h, string $v): void {
        $this->header[$h] = $v;
    }

    /**
     * Ajoute un cookie dans l'en-tête de la réponse
     * 
     * @param string $name Le nom du cookie (sans `:`)
     * @param string $value La valeur du cookie
     * @param int $t La date d'expiration du cookie
     */
    function add_cookie(string $name, string $value, int $t) {
        $this->add_header("Set-Cookie", urlencode($name)."=".urlencode($value)."; Expires=".date("D, d M Y H:i:s", $t)."GMT"."; path=/; domain=".$GLOBALS['iss']."; HttpOnly; SameSite=Strict");
    }

    /** 
     * Méthode envoyant les en-têtes définies durant le traitement de la requête.
     */
    function send_header(): void {

        foreach(array_keys($this->header) as $h) {
            header($h.': '.$this->header[$h], true);
        }
    }

    /**
     * Méthode permettant de récupérer l'URL complète de la page d'accueil
     * 
     * @return string L'URL de la page d'accueil
     */
    function get_homepage(): string {
        return $this->protocol . $this->there . (isset($this->request['lang']) ? $this->request['lang'].'/' : "" );
    }

    /**
     * Cette méthode permet de définir les headers nécessaires à la redirection de l'usager vers une autre page, avec possibilité de définir un message
     * 
     * @param string $location La page vers laquelle rediriger
     * @param string $msg      L'id du message à afficher (correspondant à l'entrée dans la traduction)
     * @param int    $code     Le code de réponse HTTP (par défaut, le code de redirection 303)
     */
    function redirect(string $location = "", string $msg = "", int $code = 303) {

        $location = ($location == "" ? $this->get_homepage() : $location);

        if ($msg != "") {

            if (isset($this->request['query']) && sizeof($this->request['query']) > 0 && !str_ends_with($location, "/")) {
                $location = trim($location, "/") . '&';
            }
            else {
                $location = trim($location, "/") . "?";
            }

            $location .= 'msg='.$msg;
        }

        $this->add_header('Location', $location);
        http_response_code($code);
    }

    /**
     * Déconnecte l'utilisateur en définissant une redirection, un message spécifique ainsi qu'en supprimant le cookie de session.
     */
    function disconnect() {
        $this->redirect($this->protocol.$this->uri, "deco_msg");
        $this->add_cookie("makki_user", "", 1);
    }

    /**
     * Défini une redirection et un message spécifiques à un problème d'authentification
     */
    function unauthorized() {
        $this->redirect("", "unauth_err", 401);
        return;
    }

    /**
     * Génère un eTag, ou timestamp (horodatage) permettant de définir quand la ressource a été modifiée pour la dernière fois, à partir de la date de modification du fichier.
     * 
     * @param string $filepath Le chemin vers le fichier concerné
     * @return array|bool Un tuple (timestamp ; eTag) ou false si le fichier n'existe pas. Le timestamp est souvent utilisé pour l'en-tête `Expires`
     */
    function make_etag(string $filepath) {

        if (is_file($filepath)) {

            $filename = basename($filepath);
            $time = filemtime($filepath); # false
            $time = date('d/m/Y H:i:s', $time);

            return array($time, base64_encode(md5($time)));
        }
        else {
            return false;
        }
    }

    /**
     * Permet de vérifier si une ressource a été modifiée et, le cas échéant, de fournir la nouvelle version de la ressource.
     * 
     * @param string $filepath Le chemin vers la ressource
     * @param string $content_type Le type MIME de la ressource
     * @return int Le code de réponse HTTP requis
     */
    function cache(string $filepath, string $content_type) {

        $etag = $this->make_etag($filepath);
    
        if ($etag != false) {

            $if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false;
            $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] : false;

            $mod = $etag[0];
            $etag = $etag[1];

            $t = new DateTimeImmutable();
            $duration = 60*60*24*7;

            $this->add_header("Expires", $t->modify("$duration seconds")->getTimestamp());

            if (
                (
                    ($if_none_match && $if_none_match == "\"".$etag."\"") || (!$if_none_match)
                    ) &&
                ($if_modified_since && $if_modified_since == $mod)
                )
            {
                return 304;
            }

            $this->add_header("Content-Type", $content_type);
            $this->add_header("Content-Length", filesize($filepath));
            $this->add_header("Cache-Control", "no-cache");
            $this->add_header("Last-Modified", $mod);
            $this->add_header("ETag", "\"".$etag."\"");

            $res = file_get_contents($filepath);

            if ($res == false) {
                return 500;
            }
            else {
                $this->output .= $res;
                return 200;
            }
            
        }
        else {
            return 404;
        }
    }

    /**
     * Génère un token de session pour l'utilisateur, sous la forme d'un JSONWebToken envoyé en cookie.
     * 
     * @param int $id L'id de l'utilisateur dans la base de données
     */
    function make_session(int $id) {

        if (!file_exists("secure/clefs.json")) {
            make_keyset();
        }

        $clefs = json_decode(file_get_contents("secure/clefs.json"), true);

        if ($clefs["exp"] < time()) {
            make_keyset();
            $clefs = json_decode(file_get_contents("secure/clefs.json"), true);
        }

        $jwt = JWT::encode($id, '+60 minutes');
        $jws = JWS::encode($jwt, base64_decode($clefs['keys']['sig-session']['key']));
        $jwe = JWE::encode($jws, get_public_key(base64_decode($clefs['keys']['enc-session']['key'])));

        $dec = json_decode($jwt, true);
        $this->add_cookie("makki_user", $jwe, $dec['exp']);
    }

    /**
     * Méthode validant ou non la session de l'utilisateur (sous la forme du cookie `makki_user` reçu)
     * 
     * @return array Un tuple authentifié? (bool) et id utilisateur (int, -1 si pas authentifié)
     */
    function retrieve_session(): array {

        if (isset($_COOKIE["makki_user"])) {

            if (!is_file("secure/clefs.json")) {
                make_keyset();
                return [false, -1];
            }

            $clefs = json_decode(file_get_contents("secure/clefs.json"), true);

            if (!$clefs || $clefs["exp"] < time()) {
                make_keyset();
                return [false, -1];
            }

            $jws = JWE::decode($_COOKIE["makki_user"], base64_decode($clefs['keys']['enc-session']['key']));
            $jwt = JWS::decode($jws, base64_decode($clefs['keys']['sig-session']['key']));

            if (check_errors($jwt)) {
                return [false, -1];
            }

            $decoded = JWT::decode($jwt, '');

            if (check_errors($decoded)) {
                return [false, -1];
            }

            return $this->db->check_admin_id($decoded['usr']) ? [true, $decoded] : [false, -1];
        }
        else {
            return [false, -1];
        }
    }

    /**
     * Méthode générant un jeton anti-CSRF
     * 
     * @return string Le jeton encodé (à destination des formulaires ; un cookie est aussi ajouté pour le double-send)
     */
    function make_nonce(): string {

        if (!file_exists("secure/clefs.json")) {
            make_keyset();
        }

        $clefs = json_decode(file_get_contents("secure/clefs.json"), true);

        if ($clefs["exp"] < time()) {
            make_keyset();
            $clefs = json_decode(file_get_contents("secure/clefs.json"), true);
        }

        $jwt = JWT::encode($this->auth ? $this->session : "", '+60 minutes');
        $jws = JWS::encode($jwt, base64_decode($clefs['keys']['sig-csrf']['key']));

        $dec = json_decode($jwt, true);

        $this->add_cookie("makki_nonce", $jws, $dec['exp']);
        return $jws;
    }

    /**
     * Permet de récupérer un jeton anti-CSRF
     * 
     * @param string $nonce Le jeton à décoder
     * @return string|false Le jeton décodé ou false si rejeté
     */
    function retrieve_nonce(string $nonce) {

        if (!isset($nonce)) {
            return false;
        }

        $clefs = json_decode(file_get_contents("secure/clefs.json"), true);

        if (!$clefs || $clefs["exp"] < time()) {
            make_keyset();
            return false;
        }

        $jwt = JWS::decode($nonce, base64_decode($clefs['keys']['sig-csrf']['key']));

        if (check_errors($jwt)) {
            return false;
        }

        $decoded = JWT::decode($jwt, '');

        if (check_errors($decoded)) {
            return false;
        }

        return $decoded;
    }

    /**
     * Méthode validant les jetons anti-CSRF
     * 
     * @return bool Si les jetons sont identiques ou non
     */
    function validate_tokens(): bool {

        if ($this->method == "DELETE") {
            parse_str(file_get_contents('php://input'), $_DELETE);
            $form_nonce = isset($_DELETE["nonce"]) ? $_DELETE["nonce"] : null;
        }
        else if ($this->method == "POST") {
            $form_nonce = isset($_POST["nonce"]) ? $_POST["nonce"] : null;
        }
        else {
            $form_nonce = null;
        }

        if ($form_nonce == null || !isset($_COOKIE['makki_nonce'])) {
            return false;
        }

        $cookie = $this->retrieve_nonce($_COOKIE['makki_nonce']);
        $frm = $this->retrieve_nonce(urldecode($form_nonce));

        // On vérifie que :
        // si connecté, le champ `usr` du cookie nonce doit correspondre au champ `sid` du cookie d'authentification
        // le nonce doit être présent dans les donneés du formulaire
        // le champ `sid` du nonce du formulaire doit correspondre à celui du cookie 
        if(($this->auth && $cookie['usr'] != $this->session) || $frm['sid'] != $cookie['sid']) {
            return false;
        }

        return true;
    }

    /**
     * Génère les résultats de recherche de l'utilsateur
     * 
     * @param array $query La query string traitée par le `RequestParser`
     * @return array Les données brutes récupérées
     */
    function search_query(array $query): array {

        $corres = array(
            "subject" => "dcterms:subject",
            "title" => "dcterms:title",
            "coverage" => "dcterms:coverage",
            "pron" => "lex:pron",
            "etymo" => "lex:etymo",
        );

        $body = "WHERE { ?in dcterms:title ?title . ";

        foreach(array_keys($query) as $criterion) {
            if(!array_key_exists($criterion, $corres) || sizeof($query[$criterion]) <= 0) {
                continue;
            }

            $body .= implode(" UNION ", array_map(function($val) use ($corres, $criterion) {
                return ($val == "none") ? "{ OPTIONAL {?in ".$corres[$criterion]." ?out} FILTER ( !BOUND(?out) ) }" : "{?in ".$corres[$criterion]." ?$criterion . FILTER (REGEX(?$criterion, \"".urldecode($val)."\")) .}";
            }, $query[$criterion]));

        }

        $body .= "}";

        $nb_results = (int)array_sum(array_map(function($item){return $item['nb'];}, $this->db->query("SELECT DISTINCT COUNT(?in) AS ?nb $body")['result']['rows']));

        $page_size = isset($query['page_size']) ? min(self::PAGE_SIZE_MAX, max(self::PAGE_SIZE_MIN, $query['page_size'][0])) : self::PAGE_SIZE_MAX;
        $offset    = (isset($query['page']) ? max((int)$query['page'][0]-1, 0) : 0)*$page_size;
        $nb_pages  = (int)ceil($nb_results / $page_size);

        
        
        $q = "DESCRIBE ?in $body ORDER BY (?title) LIMIT $page_size OFFSET ".$offset;

        return array("pagination" => array(
            "nb_results" => (int)$nb_results,
            "page_size"  => (int)$page_size,
            "offset"     => (int)$offset,
            "nb_pages"   => (int)$nb_pages,
            "query"      => $query
        ), "data" => $this->db->query($q));
    }

    /**
     * Met en forme les données de search_query() si besoin
     * 
     * @param array $query La query string traitée par le `RequestParser`
     * @param bool $raw Permet de bypass la mise en forme (utilisé pour les représentations comme RDF)
     * @return array Les données formatées ou non
     */
    function get_data(array $query, bool $raw = false): array {

        // On prépare puis effectue la requête pour récupérer les infos :

        $retrieved = $this->search_query($query);
        $res = $retrieved["data"];

        if ($raw) {
            return $res['result'];
        }

        // On arrange les résultats sous une forme exploitable :

        $output = array();

        foreach(array_keys($res['result']) as $def_id) {

            $def = array();
            $words = array();
            $syns = array();


            foreach(array_keys($res['result'][$def_id]) as $pred) {

                $item = substr($pred, strrpos($pred, "/")+1);

                if ($item == 'title') {
                    foreach($res['result'][$def_id][$pred] as $element) {
                        array_push($words, $element['value']);
                    }
                }
                else if ($item == 'identifier') {

                    $q_s = "SELECT ?title ?in WHERE {
                            ?in dcterms:identifier \"".$res['result'][$def_id][$pred][0]["value"]."\" .
                            ?in dcterms:title ?title .
                        }
                        ";
                    
                    $res_syn = $this->db->query($q_s);

                    if (sizeof($res_syn['result']['rows']) > 0) {

                        if (!array_key_exists("syn", $def)) {
                            $def["syn"] = array();
                        }
    
                        foreach($res_syn['result']['rows'] as $element) {
                            array_push($def["syn"], ["value" => $element['title'], "lang" => (isset($element['title lang']) ? $element['title lang'] : "ar"), "URI" => $element['in']]);
                        }
                    }

                }
                else {
                    if (!array_key_exists($item, $def)) {
                        $def[$item] = array();
                    }
                    
                    foreach($res['result'][$def_id][$pred] as $element) {
                        array_push($def[$item], ["value" => $element['value'], "lang" => isset($element['lang']) ? $element['lang'] : "fr"]);
                    }
                }
            }

            foreach($words as $w) {

                if (!array_key_exists($w, $output)) {
                    $output[$w] = array();
                }

                $output[$w][$def_id] = $def;
            }

        }

        return array("pagination" => $retrieved["pagination"], "data" => $output);
    }

}
?>