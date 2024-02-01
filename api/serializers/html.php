<?php 

require_once(__DIR__."/../simple_html_dom/simple_html_dom.php");

class HTMLSerializer {

    function __construct(string $pages_path, $db) {

        $paths = file_get_contents($pages_path);
        $this->pages = array("header" => "html/header.html", "footer" => "html/footer.html");
        $this->db = $db;

        if ($paths != false) {
            $data = json_decode($paths, true);

            foreach(array_keys($data) as $h) {
                $this->pages[$h] = $data[$h];
            }
        }
        else {
            die("OUPSIE"); #TODO
        }
    }

    public function make_html(string $page, string $base_url): string {

        if (!array_key_exists($page, $this->pages)) {
            die("OH NO"); #TODO
        }

        $header = file_get_html($this->pages["header"]);
        $footer = file_get_html($this->pages["footer"]);
        $output = file_get_html($this->pages[$page]);

        $html = $output->find('html', 0);
        $body = $html->find('body', 0);
        #$html->removeChild($body);

        #$html->appendChild($header->find('header', 0));
        $body->innertext = $header->find('header', 0)->outertext . $body->innertext . $footer->find('footer', 0)->outertext;
        #$html->appendChild($body);


        // On adapte la mise en page à la langue (sens de lecture et code de langue)
        //TODO: penser à étudier le fait que même en français, certains mots restent en arabe (voir s'il est utile de changer le sens de lecture ponctuellement)
        /*foreach($output->find('html') as $e) {
            #$e->dir = $this->request['lang']['dir'];
            $e->lang = $this->lang[0];
        }*/

        $protocol = strtolower(current(explode('/',$_SERVER['SERVER_PROTOCOL']))) . "://";

        $output = $output->save();
        $output = str_get_html($output);

        // Images
        foreach($output->find('img') as $e)
            $e->src = $protocol.$base_url.$e->src;
        
        foreach($output->find('link') as $e) {

            // Feuilles de style
            if ($e->rel == "stylesheet") {
                $e->href = $protocol.$base_url.'styles/'.$e->href;
            }
        }
        foreach($output->find('script') as $e) {
            // Scripts
            $e->src = $protocol.$base_url.$e->src;
        }

        if ($page == "administration") {
            $output = $this->make_table_trad($output);
        }

        return $output->innertext;

    }

    function make_table_trad($html) {

        foreach($html->find('table#table_fichier_trad>tbody') as $t) {

            $rows = $this->db->query("@prefix dcterms: <http://purl.org/dc/terms/> . @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> . @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> . SELECT ?language ?file ?date WHERE { ?in dcterms:source ?file . ?in dcterms:language ?language . ?in dcterms:date ?date } GROUP BY ?file ?language ?date");

            foreach($rows['result']['rows'] as $r) {
                $lang = $r['language'];
                $file = $r['file'];
                $date = $r['date'];
                $t->innertext .= "<tr><td>$lang</td><td>$file</td><td>$date</td><td><button>blutton</button></td></tr>";
            }
        }

        return $html;
    }
}

?>