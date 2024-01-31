<?php 

require("tools.php");
require_once("vendor/autoload.php");

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
        // uri = 		"i3l.univ-grenoble-alpes.fr/~makki/lexique"
        // PHP_SELF = 	"i3l.univ-grenoble-alpes.fr/~makki/index.php"
        // On récupère `dirname` de PHP_SELF, soit "i3l.univ-grenoble-alpes.fr/~makki"
        // En faisant la différence, on obtient : "/lexique"
        $php_loc = pathinfo($_SERVER['PHP_SELF'])['dirname'];

        $r = substr($uri, strlen($php_loc));

        // On récupère les champs de requête (recherche, etc.) et le chemin vers la ressource
        $query_s = parse_url($r, PHP_URL_QUERY);
        $path = parse_url($r, PHP_URL_PATH);

        // On récupère les champs 'lang', 'collection' et 'target' depuis 
        preg_match("{^(?>\/(?>(?P<lang>[\w]{2})\/|)(?P<collection>[\w]+)(?>(?>\/(?P<target>[\w. +_-]+))|[\w]+|)$)|\/{0,1}$}", $path, $output);

        // Si une query a été fournie, on la récupère sous la forme d'un tableau associatif
        if ($query_s != null && strlen($query_s) > 0) {
            $output['query'] = $this->parse_query($query_s);
        }

        foreach(array_keys($output) as $key) {
            if ($output[$key] == '') {
                unset($output[$key]);
            }
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
    public function parse_mime(string $str): array {

        $output = array();
        $inter = array();

        // On découpe la chaîne d'entrée :
        // text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8
        // devient array("text/html", "application/xhtml+xml", "application/xml;q=0.9", "image/avif", "image/webp", "*/*;q=0.8")
        $mimes = explode(',', $str);
    
        // Ensuite, on redécoupe chaque type pour extraire son coefficient q=x, permettant de définir un ordre de préférence (si pas présent=1.0)
        foreach($mimes as $m) {
            $sep = explode(";q=", $m);
            $sep[0] = explode("/", $sep[0]);
    
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
    public function parse_lang(string $str): array {

        return $this->parse_mime($str);
    }

    /**
     * Méthode permettant de récupérer les variables d'une requête de la façon la plus répandue (càd stocker dans un tableau quand une variable est présente plusieurs fois).
     * Honteusement volé sur https://www.php.net/manual/en/function.parse-str.php#76792 pour gagner du temps.
     * 
     * @param string $str La chaîne de caractères à traiter
     * 
     * @return array Un tableau associatif liant un nom de variable à sa ou ses valeurs
     */
    public function parse_query(string $str): array {

        $output = array();
      
        // On découpe la chaîne pour récupérer chaque déclaration
        $pairs = explode('&', $str);
      
        foreach ($pairs as $i) {

          // On récupère le nom et la valeur
          list($name,$value) = explode('=', $i, 2);
          
          // Si la variable existe déjà, on la transforme en tableau pour accueillir la nouvelle
          if( isset($output[$name]) ) {

            if( is_array($output[$name]) ) {
              $output[$name][] = $value;
            }
            else {
              $output[$name] = array($output[$name], $value);
            }
          }
          
          // Si elle n'existe pas, on ajoute simplement le couple clé => valeur
          else {
            $output[$name] = $value;
          }
        }
      
        return $output;
      }

}

?>