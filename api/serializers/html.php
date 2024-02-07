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

    public function make_html(string $page, string $base_url, array $langs, array $request, string $protocol, bool $connected): string {

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
        $accueil_link->href = $protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "");

        $licence_link = $footer->find('#licence', 0);
        $licence_link->href = $protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "")."licences";

        $lang_list->innertext = "";
        $sorted_langs = $langs;
        asort($sorted_langs);
        $last = end($sorted_langs);
        foreach($sorted_langs as $l) {
            $lang_list->innertext .= "<li><a ". ($last == $l ? "class=\"arrondie\"" : "") ." href=\"".$protocol.$base_url.$l."/". (isset($request['collection']) ? $request['collection'] . "/" : ""). (isset($request['target']) ? $request['target'] . "/" : "") ."\">$l</a></li>";
        }

        if ($connected) {
            $header->find('.login-wrapper', 0)->outertext = "";
        }
        
        $body->innertext = $header->find('header', 0)->outertext . $bar->outertext . $body->innertext . $footer->find('footer', 0)->outertext;


        $output = $output->save();
        $output = str_get_html($output);

        if ($page == "administration") {
            $output = $this->make_table_trad($output, $protocol, $base_url);
        }
        $output = $output->save();
        $output = str_get_html($output);
        //print_r($langs);

        // Images
        foreach($output->find('img') as $e)
            $e->src = $protocol.$base_url.$e->src;
        
        foreach($output->find('link') as $e) {

            // Feuilles de style
            $e->href = $protocol.$base_url.$e->href;
        }
        foreach($header->find('link') as $e) {
            $e->href = $protocol.$base_url.$e->href;
            $output->find('head', 0)->appendChild($e);
        }
        foreach($footer->find('link') as $e) {
            $e->href = $protocol.$base_url.$e->href;
            $output->find('head', 0)->appendChild($e);
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

        return $output->innertext;

    }

    function make_table_trad($html, $protocol, $base_url) {

        foreach($html->find('table#table_fichier_trad>tbody') as $t) {

            $rows = $this->db->query("@prefix dcterms: <http://purl.org/dc/terms/> . @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> . @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> . SELECT ?language ?file ?date WHERE { ?in dcterms:source ?file . ?in dcterms:language ?language . ?in dcterms:date ?date } GROUP BY ?file ?language ?date");

            foreach($rows['result']['rows'] as $r) {
                $lang = $r['language'];
                $file = $r['file'];
                $date = $r['date'];
                $t->innertext .= "<tr>
                                    <td>$lang</td>
                                    <td>$file</td>
                                    <td>$date</td>
                                    <td><button class=\"trad delete_trad\" data-url=\"".$protocol.$base_url."traductions\" data-lang=\"".$lang."\" data-file=\"".$file."\" data-date=\"".$date."\" id=\"btn_suppr\">Delete</button></td>
                                </tr>";
            }
        }

        return $html;
    }

    function make_table_data($html, $protocol, $base_url) {

        foreach($html->find('table#table_fichier_data>tbody') as $t) {

            $rows = $this->db->query("@prefix dcterms: <http://purl.org/dc/terms/> . @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> . @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> . SELECT ?file WHERE { ?in dcterms:source ?file . FILTER(REGEX(?in, \"lexique/.*/n([0-9a-f]+)-([0-9a-f]+)-([0-9a-f]+)-([0-9a-f]+)-([0-9a-f]+)\")) } GROUP BY ?file");

            foreach($rows['result']['rows'] as $r) {
                $file = $r['file'];
                $t->innertext .= "<tr>
                                    <td>$file</td>
                                    <td><button class=\"delete_data trad\" data-url=\"".$protocol.$base_url."lexique\" data-file=\"".$file."\" id=\"btn_suppr\">Delete</button></td>
                                </tr>";
            }
        }

        return $html;
    }

    function fecth_translation(string $id, array $langs): array {

        if (sizeof($langs) > 0) {
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
        }

        return array("", "NOT_FOUND:".strtoupper($id));
    }
}

?>