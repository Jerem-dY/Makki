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

    public function make_html(string $page, string $base_url, array $langs, array $request, string $protocol): string {

        if (!array_key_exists($page, $this->pages)) {
            die("OH NO"); #TODO
        }

        $header = file_get_html($this->pages["header"]);
        $footer = file_get_html($this->pages["footer"]);
        $output = file_get_html($this->pages[$page]);

        $html = $output->find('html', 0);
        $body = $html->find('body', 0);

        $bar = $header->find('.barre', 0);
        $lang_list = $bar->find('ul#lang_liste', 0);

        $accueil_link = $header->find('#accueil', 0);
        $accueil_link->href = $protocol.$base_url;

        $licence_link = $footer->find('#licence', 0);
        $licence_link->href = $protocol.$base_url."licences";

        $lang_list->innertext = "";
        $sorted_langs = $langs;
        asort($sorted_langs);
        $last = end($sorted_langs);
        foreach($sorted_langs as $l) {
            $lang_list->innertext .= "<li><a ". ($last == $l ? "class=\"arrondie\"" : "") ." href=\"".$protocol."/".$base_url.$l."/". (isset($request['collection']) ? $request['collection'] . "/" : ""). (isset($request['target']) ? $request['target'] . "/" : "") ."\">$l</a></li>";
        }


        $body->innertext = $header->find('header', 0)->outertext . $bar->outertext . $body->innertext . $footer->find('footer', 0)->outertext;

        $output = $output->save();
        $output = str_get_html($output);

        //print_r($langs);

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
        foreach($output->find('.trad') as $e) {
            $id = $e->id;

            $ts = $this->fecth_translation($id, $langs);
            $e->lang = $ts[0];
            $e->innertext = $ts[1];
            
        }
        foreach($output->find('.trad_placeholder') as $e) {
            $id = $e->id;

            $ts = $this->fecth_translation($id, $langs);
            $e->lang = $ts[0];
            $e->placeholder = $ts[1];
        }
        foreach($output->find('.trad_value') as $e) {
            $id = $e->id;

            $ts = $this->fecth_translation($id, $langs);
            $e->lang = $ts[0];
            $e->value = $ts[1];
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
                $t->innertext .= "<form method=\"DELETE\" action=\".\"><tr><td name=\"lang\">$lang</td><td name=\"file\">$file</td><td name=\"date\">$date</td><td><input type=\"submit\" value=\"Delete\" name=\"delete_trad\"/></td></tr></form>";
            }
        }

        return $html;
    }

    function fecth_translation(string $id, array $langs): array {

        $prepare_trad = function ($value) {
            return "{ ?in dcterms:alternative ?txt FILTER( lang(?txt) = \"$value\" ) }";
        };

        $query = "@prefix dcterms: <http://purl.org/dc/terms/> . @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> . @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> . SELECT ?txt WHERE { ?in dcterms:identifier \"$id\" . ";
        $mini_q = array_map($prepare_trad, $langs);
        $query .= implode(" UNION ", $mini_q) . "}";

        $rows = $this->db->query($query)['result']['rows'];

        if (sizeof($rows) > 0) {
            return array($rows[0]['txt lang'], $rows[0]['txt']);
        }

        return array("", "NOT_FOUND:".strtoupper($id));
    }
}

?>