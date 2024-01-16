<?php 

require("tools.php");

class RequestParser {

    function __construct(string $config_file) {

        //TODO: ajouter au constructeur un paramètre 'db'

        $str = file_get_contents($config_file);

        if ($str == false) {
            echo "No config file";
            $this->config = array();
        }
        else {

            $config = json_decode($str, true); 

            if(isset($config["pages"])) {
                $this->pages = $config["pages"];
            }
            else {
                echo "No 'pages' configuration.";
            }
        }

        $this->langs = array("fr" => array("dir" => "ltr", "code" => "fr"), "ar" => array("dir" => "rtl", "code" => "ar")); //TODO: A remplacer par une requête à la DB
    }

    /**
     * Cette méthode traite une uri et renvoie un tableau associatif des données qu'elle contient.
     * 
     * @param string $uri L'URI à traiter
     * 
     * @return array Un tableau associatif donnée => valeur dans l'URI 
     */
    public function parse_uri(string $uri): array {

        $output = array();

        // On récupère la différence entre le chemin complet (serveur compris) et le chemin d'accès du fichier php ; 
        // cela permet de faire la différence entre les deux et de récupérer le chemin d'accès à la ressource seul,
        // Permettant ainsi de ne pas nécessiter de connaissance a priori du nom du serveur (pratique pour migrer le site)
        // Ex : 
        // uri = 		"i3l.univ-grenoble-alpes.fr/~makki/fr/lexique"
        // PHP_SELF = 	"i3l.univ-grenoble-alpes.fr/~makki/index.php"
        // On récupère `dirname` de PHP_SELF, soit "i3l.univ-grenoble-alpes.fr/~makki"
        // En faisant la différence, on obtient : "/fr/lexique"
        $php_loc = pathinfo($_SERVER['PHP_SELF'])['dirname'];

        $r = substr($uri, strlen($php_loc));

        // On récupère les champs de requête (recherche, etc.) et le chemin vers la ressource
        $query_s = parse_url($r, PHP_URL_QUERY);
        $path = parse_url($r, PHP_URL_PATH);

        // Si une query a été fournie, on la récupère sous la forme d'un tableau associatif
        if ($query_s != null) {
            $output['query'] = array();
            parse_str($query_s, $output['query']);
        }

        // On récupère les éléments séparés par un slash '/', et on se débarasse du premier élément
        $elements = explode("/", $path);
        array_shift($elements);
        $size = sizeof($elements);

        if ($size == 0) {
            $output['code'] = 404;
        }

        //TODO: remplacer par une requête à la DB pour récupérer les codes de toutes les langues gérées
        if (!isset($this->langs[$elements[0]])) {
            echo "BAD LANG: " . $elements[0];
            $output['code'] = 404;
        }
        else {
            $output['lang'] = $this->langs[$elements[0]];
        }

        // Exemples d'urls valides :
        // [lang]/[page]/[target]?[query]
        // fr/lexique/أباجُور/
        // fr/theme/commun
        // fr/images/bebe.png
        // fr/scripts/jquery.js
        // fr/styles/style.css

        if ($size > 1) {

            if (isset($this->pages[$elements[1]])) {
                $output['page'] = $this->pages[$elements[1]];
                $output['type'] = $elements[1];
            }
            else {
                echo "BAD PAGE: " . $elements[1];
                $output['code'] = 404;
            }

            if ($size > 2) {
        
                $output['target'] = urldecode($elements[2]);

                // On accept un seul '/' traînant et pas d'autres éléments
                // fr/lexique/أباجُور/		✅
                // fr/lexique/أباجُور		✅
                // fr/lexique/أباجُور//		❌
                // fr/lexique/أباجُور/a/	❌
                // fr/lexique/أباجُور/a/b	❌
                if ($size == 4 && $elements[3] != '' || $size > 4) {
                    echo "BAD TARGET: " . $elements[3];
                    $output['code'] = 404;
                }
            }
        }

        if(!isset($output['code'])) {
            $output['code'] = 200;
        }

        return $output;
    }

    /**
     * Méthode permettant de récupérer les types MIME d'un champ `Accept`.
     * 
     * @param string $str La chaîne de caractères à traiter
     * 
     * @return array Les types acceptés par le client dans l'ordre de préférence
     */
    function parse_mime(string $str): array {

        $output = array();
        $inter = array();

        // On découpe la chaîne d'entrée :
        // text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8
        // devient array("text/html", "application/xhtml+xml", "application/xml;q=0.9", "image/avif", "image/webp", "*/*;q=0.8")
        $mimes = explode(',', $str);
    
        // Ensuite, on redécoupe chaque type pour extraire son coefficient q=x, permettant de définir un ordre de préférence (si pas présent=1.0)
        foreach($mimes as $m) {
            $sep = explode(";q=", $m);
    
            if (sizeof($sep) < 2) {
                array_push($sep, 1.0);
            }
    
            array_push($inter, $sep);
        }
    
        // On trie les types par rapport à leur coefficient (ordre décroissant)
        usort($inter, "cmp_sec");
    
        // On ne récupère que les éléments textes, mais dans le bon ordre
        foreach($inter as $item) {
            array_push($output, $item[0]);
        }
    
        return $output;
    }

    /**
     * Méthode permettant de récupérer les langues d'un champ `Accept-Language` (alias de `parse_mime()` !).
     * 
     * @param string $str La chaîne de caractères à traiter
     * 
     * @return array Les langues acceptés par le client dans l'ordre de préférence
     */
    function parse_lang(string $str): array {

        return $this->parse_mime($str);
    }
}

?>