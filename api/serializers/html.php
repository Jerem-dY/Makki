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

    public function make_html(string $page, string $base_url, array $langs, array $request, string $protocol, bool $connected, string $token="", array $word_data = []): string {

        if (!array_key_exists($page, $this->pages)) {
            die("OH NO"); #TODO
        }

        $header = file_get_html($this->pages["header"]);
        $footer = file_get_html($this->pages["footer"]);
        $output = file_get_html($this->pages[$page]);

        if ($page == "mot") {
            $this->make_data($word_data, $output, $langs, $base_url, $protocol);
        }

        $html = $output->find('html', 0);
        $body = $html->find('body', 0);

        $bar = $header->find('.barre', 0);
        $lang_list = $bar->find('ul#lang_liste', 0);

        $accueil_link = $header->find('#accueil', 0);
        $accueil_link->href = $protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "");

        $contact_link = $header->find('#contact', 0);
        $contact_link->href = $protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "")."contact";

        $licence_link = $footer->find('#licence', 0);
        $licence_link->href = $protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "")."licences";

        $lang_list->innertext = "";
        $sorted_langs = $langs;
        asort($sorted_langs);
        $last = end($sorted_langs);
        foreach($sorted_langs as $l) {
            $lang_list->innertext .= "<li><a ". ($last == $l[0] ? "class=\"arrondie\"" : "") ." href=\"".$protocol.$base_url.$l[0]."/". (isset($request['collection']) ? $request['collection'] . "/" : ""). (isset($request['target']) ? $request['target'] . "/" : "") ."\">".$l[0]."</a></li>";
        }

        if ($connected) {
            $header->find('.login-wrapper', 0)->outertext = "";
            $header->find(".login-wrapper_connecte", 0)->removeAttribute("hidden");
            $header->find("#form_admin", 0)->action = $protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "")."administration";
        }
        else {
            $header->find(".login-wrapper_connecte", 0)->outertext = "";
        }
        
        $body->innertext = $header->find('header', 0)->outertext . $bar->outertext . $body->innertext . $footer->find('footer', 0)->outertext;

        $output = $output->save();
        $output = str_get_html($output);

        if ($token != "") {
            $nonce = "<input type=\"hidden\" id=\"nonce\" name=\"nonce\" value=\"".urlencode($token)."\">";

            foreach($output->find("form") as $form) {
                $form->innertext = $nonce . $form->innertext;
            }
        }

        if ($page == "administration") {
            $output->find("html", 0)->setAttribute("data-nonce", urlencode($token));
            $output = $this->make_table_trad($output, $protocol, $base_url);
            $output = $this->make_table_data($output, $protocol, $base_url);
        }
        $output = $output->save();
        $output = str_get_html($output);

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

            $ts = $this->fetch_translation($id, $langs, $base_url, $protocol);
            $e->lang = $ts[0];
            $e->dir = $ts[2];
            $e->innertext = $ts[1];
            
        }
        foreach($output->find('.trad_placeholder') as $e) {
            $id = $e->id;

            $ts = $this->fetch_translation($id, $langs, $base_url, $protocol);
            $e->lang = $ts[0];
            $e->dir = $ts[2];
            $e->placeholder = $ts[1];
        }
        foreach($output->find('.trad_value') as $e) {
            $id = $e->id;

            $ts = $this->fetch_translation($id, $langs, $base_url, $protocol);
            $e->lang = $ts[0];
            $e->dir = $ts[2];
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
                                    <td><button class=\"trad delete_trad boutons_fichiers\" data-url=\"".$protocol.$base_url."traductions\" data-lang=\"".$lang."\" data-file=\"".$file."\" data-date=\"".$date."\" id=\"btn_suppr\">Delete</button></td>
                                </tr>";
            }
        }

        return $html;
    }

    function make_table_data($html, $protocol, $base_url) {

        foreach($html->find('table#table_fichier_data>tbody') as $t) {

            $rows = $this->db->query("@prefix dcterms: <http://purl.org/dc/terms/> . @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> . @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> . 

            SELECT ?file WHERE { ?in dcterms:source ?file . ?in dcterms:title ?title } GROUP BY ?file");

            foreach($rows['result']['rows'] as $r) {
                $file = $r['file'];
                $t->innertext .= "<tr>
                                    <td>$file</td>
                                    <td><button class=\"delete_data trad boutons_fichiers\" data-url=\"".$protocol.$base_url."lexique\" data-file=\"".$file."\" id=\"btn_suppr\">Delete</button></td>
                                </tr>";
            }
        }

        return $html;
    }

    function make_data(array $word_data, $template, array $langs, string $base_url, string $protocol) {

        $ex = $template->find(".mots_donnees", 0);
        $ex->innertext = "";

        if (sizeof($word_data) <= 0) {
            return;
        }

        foreach(array_keys($word_data) as $word) {
            $ex->innertext .= "<ol class=\"resultats_donnees\"><h3 lang=\"ar\" dir=\"rtl\">$word</h3>";

            foreach(array_keys($word_data[$word]) as $def_id) {

                $ex->innertext .= "<li class=\"definition\" id=\"$def_id\">";

                if (array_key_exists("subject", $word_data[$word][$def_id])) {
                    $ex->innertext .= "<ul>";

                    $ex->innertext .= "<h5 class=\"trad\" id=\"theme\"> Thématiques : </h5>";

                    foreach($word_data[$word][$def_id]["subject"] as $subject) {
                        if ($subject[1] == $langs[0][0]) {
                            $ex->innertext .= "<li lang=\"".$subject[1]."\" dir=\"".$langs[0][1]."\"><a href=\"".$protocol.$base_url."lexique?subject=".urlencode($subject[0])."\">".$subject[0]."</a></li>";
                        }
                    }

                    $ex->innertext .= "</ul>";
                }

                if (array_key_exists("syn", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["syn"]) > 1) {

                    $ex->innertext .= " <p lang=\"ar\" dir=\"rtl\">( ";

                    $map = function($syn) use ($word, $protocol, $base_url) {
                            return "<i><a href=\"".$protocol.$base_url."lexique/".urlencode($syn[0])."\">".$syn[0]."</a></i>";
                        };

                    
                    $ex->innertext .= implode(" ، ", array_map($map, array_filter($word_data[$word][$def_id]["syn"], function($el) use ($word) {
                                if ($el[0] != $word)
                                    return true;

                                return false;
                            })
                        )
                    );

                    $ex->innertext .= " ) <span class=\"trad\" id=\"synonyme\"> Synonymes : </span></p>";
                }

                if (array_key_exists("coverage", $word_data[$word][$def_id])) {
                    $ex->innertext .= "<ul>";

                    $ex->innertext .= "<h5 class=\"trad\" id=\"origine\"> Origines : </h5>";

                    foreach($word_data[$word][$def_id]["coverage"] as $coverage) {
                        if ($coverage[1] == $langs[0][0]) {
                            $ex->innertext .= "<li lang=\"".$coverage[1]."\" dir=\"".$langs[0][1]."\"><a href=\"".$protocol.$base_url."lexique?coverage=".urlencode($coverage[0])."\">".$coverage[0]."</a></li>";
                        }
                    }

                    $ex->innertext .= "</ul>";
                }

                if (array_key_exists("pron", $word_data[$word][$def_id])) {
                    $ex->innertext .= "<ul>";

                    $ex->innertext .= "<h5 class=\"trad\" id=\"pron\"> Prononciations : </h5>";

                    foreach($word_data[$word][$def_id]["pron"] as $pron) {
                        if ($pron[1] == $langs[0][0]) {
                            $ex->innertext .= "<li lang=\"".$pron[1]."\" dir=\"".$langs[0][1]."\"><a href=\"".$protocol.$base_url."lexique?pron=".urlencode($pron[0])."\">".$pron[0]."</a></li>";
                        }
                    }

                    $ex->innertext .= "</ul>";
                }

                if (array_key_exists("etymo", $word_data[$word][$def_id])) {
                    $ex->innertext .= "<ul>";

                    $ex->innertext .= "<h5 class=\"trad\" id=\"etymo\"> Etymologie : </h5>";

                    foreach($word_data[$word][$def_id]["etymo"] as $etymo) {
                        if ($etymo[1] == $langs[0][0]) {
                            $ex->innertext .= "<li lang=\"".$etymo[1]."\" dir=\"".$langs[0][1]."\"><a href=\"".$protocol.$base_url."lexique?etymo=".urlencode($etymo[0])."\">".$etymo[0]."</a></li>";
                        }
                    }

                    $ex->innertext .= "</ul>";
                }

                if (array_key_exists("abstract", $word_data[$word][$def_id])) {
                    foreach($word_data[$word][$def_id]["abstract"] as $abstract) {
                        if ($abstract[1] == $langs[0][0]) {
                            $ex->innertext .= "<p lang=\"".$abstract[1]."\" dir=\"".$langs[0][1]."\">".htmlentities($abstract[0])."</p>";
                        }
                    }
                }

                if (array_key_exists("example", $word_data[$word][$def_id])) {
                    $ex->innertext .= "<ul>";

                    $ex->innertext .= "<h5 class=\"trad\" id=\"exemple\"> Exemples : </h5>";

                    foreach($word_data[$word][$def_id]["example"] as $example) {
                        if ($example[1] == "ar") {
                            $ex->innertext .= "<li lang=\"".$example[1]."\" dir=\"rtl\"><i>".$example[0]."</i></li>";
                        }
                    }

                    $ex->innertext .= "</ul>";
                }

                $ex->innertext .= "</li>";
            }

            $ex->innertext .= "</ol>";
        }

    }

    function fetch_translation(string $id, array $langs, string $base_url, string $protocol): array {

        if (sizeof($langs) > 0) {
            $prepare_trad = function ($value, $base_url, $protocol) {
                return "{ ?in dcterms:alternative ?txt . ?in <$protocol".$base_url."traductions/dir> ?dir FILTER( lang(?txt) = \"".$value[0]."\" ) }";
            };
    
            $query = "@prefix dcterms: <http://purl.org/dc/terms/> . @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> . @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> . SELECT ?txt ?dir WHERE { ?in dcterms:identifier \"$id\" . ";
            $mini_q = array_map($prepare_trad, $langs, array_fill(0, sizeof($langs), $base_url), array_fill(0, sizeof($langs), $protocol));
            $query .= implode(" UNION ", $mini_q) . "}";
    
            $rows = $this->db->query($query)['result']['rows'];
    
            if (sizeof($rows) > 0) {
                return array($rows[0]['txt lang'], $rows[0]['txt'], isset($rows[0]['dir']) ? $rows[0]['dir'] : "ltr");
            }
        }

        return array("", "NOT_FOUND:".strtoupper($id), "ltr");
    }
}

?>