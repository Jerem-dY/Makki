<?php 

require_once(__DIR__."/../simple_html_dom/simple_html_dom.php");
require_once("serializer.php");

class HTMLSerializer extends Serializer {

    function __construct($db) {

        $paths = file_get_contents("config/pages.json");
        $this->pages = array("header" => "html/header.html", "footer" => "html/footer.html");
        parent::__construct($db);

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

    public function make(string $page, string $base_url, array $langs, array $request, string $protocol, bool $connected, string $token="", array $word_data = [], array $themes = []): string {

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
        $themes_list = $bar->find('ul#thematiques_liste', 0);

        $accueil_link = $header->find('#accueil', 0);
        $accueil_link->href = $protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "");

        $contact_link = $header->find('#contact', 0);
        $contact_link->href = $protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "")."contact";

        $licence_link = $footer->find('#licence', 0);
        $licence_link->href = $protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "")."licences";

        $lang_list->innertext = "";
        $sorted_langs = $langs;
        asort($sorted_langs);
        $nb = count($sorted_langs);
        $c = 0;
        foreach($sorted_langs as $l) {
            $c += 1;
            $lang_list->innertext .= "<li><a ". ($c == $nb ? "class=\"arrondie\"" : "") ." href=\"".$protocol.$base_url.$l[0]."/". (isset($request['collection']) ? $request['collection'] . "/" : ""). (isset($request['target']) ? $request['target'] . "/" : "") ."\">".$l[0]."</a></li>";
        }

        $themes_list->innertext = "";
        $sorted_themes = $themes;
        asort($sorted_themes);
        $nb = count($sorted_themes);
        $c = 0;
        foreach($themes as $t) {
            $c += 1;
            $themes_list->innertext .= "<li><a ". ($c == $nb ? "class=\"arrondie\"" : "") ." href=\"".$protocol.$base_url.(isset($request['lang']) ? $request['lang']."/" : "")."lexique?subject=".$t."\">".$t."</a></li>";
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
            $e->lang = $ts[self::TRANSLATION_LANG];
            $e->dir = $ts[self::TRANSLATION_DIR];
            $e->innertext = $ts[self::TRANSLATION_TEXT];
            
        }
        foreach($output->find('.trad_placeholder') as $e) {
            $id = $e->id;

            $ts = $this->fetch_translation($id, $langs, $base_url, $protocol);
            $e->lang = $ts[self::TRANSLATION_LANG];
            $e->dir = $ts[self::TRANSLATION_DIR];
            $e->placeholder = $ts[self::TRANSLATION_TEXT];
        }
        foreach($output->find('.trad_value') as $e) {
            $id = $e->id;

            $ts = $this->fetch_translation($id, $langs, $base_url, $protocol);
            $e->lang = $ts[self::TRANSLATION_LANG];
            $e->dir = $ts[self::TRANSLATION_DIR];
            $e->value = $ts[self::TRANSLATION_TEXT];
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
        $page_sys = $template->find(".pagination");
        $ex->innertext = "";

        if (sizeof($word_data) <= 0) {
            return;
        }

        $pagination = $word_data['pagination'];
        $word_data  = $word_data['data'];

        if ($pagination["nb_results"] > 1) {
            $r = $template->find("div.resultats", 0);
            $r->innertext .= $pagination["nb_results"];
        }
        else {
            $template->find("div.thematiqueseule", 0)->outertext = "";
            foreach($template->find(".pagination") as $pagin) {
                $pagin->outertext = "";
            }
        }

        $current_page = isset($pagination['query']['page']) ? (int)$pagination['query']['page'][0] : 1;

        $make_query_string = function(array $q, int $p = 1, int $p_s = 1) {
            $q['page'] = $p;
            $q['page_size'] = $p_s;
            return urldecode(preg_replace('/%5B(?:[0-9]|[1-9][0-9]+)%5D=/', '=', http_build_query($q, null, '&')));
        };

        $expand_numbers = function(int $n, int $k, int $min=1, int $max=10) {

            $n = min(max($n, $min), $max);

            $output = array(
                $n
            );
            
            $last_up = $n;
            $last_down = $n;

            $limit = min($k, $max-$min+1);

            while(sizeof($output) < $limit) {
                if ($last_down-1 >= $min) {
                    $last_down -= 1;
                    array_unshift($output, $last_down);
                }

                if (sizeof($output) < $limit) {
                    if ($last_up+1 <= $max) {
                        $last_up += 1;
                        array_push($output, $last_up);
                    }
                }
            }

            return $output;
        };

        foreach($page_sys as $el) {


            $el->innertext .= "<a href=\"".$protocol.$base_url."lexique?".$make_query_string($pagination['query'], 1, $pagination['page_size'])."\">&laquo;</a>";

            foreach($expand_numbers($current_page, 7, 1, $pagination['nb_pages']) as $p) {
                $el->innertext .= "<a ".($p == $current_page ? "class=\"active\"" : "")." href=\"".$protocol.$base_url."lexique?".$make_query_string($pagination['query'], $p, $pagination['page_size'])."\">$p</a>";
            }

            $el->innertext .= "<a href=\"".$protocol.$base_url."lexique?".$make_query_string($pagination['query'], $pagination['nb_pages'], $pagination['page_size'])."\">&raquo;</a>";
        }

        foreach(array_keys($word_data) as $word) {
            $ex->innertext .= "<ol id=\"$word\" class=\"thematiqueseule\"><h3 lang=\"ar\" dir=\"rtl\"><a class=\"mot_titre\" href=\"".$protocol.$base_url."lexique/".$word."\">$word<a></h3>";

            foreach(array_keys($word_data[$word]) as $def_id) {

                $ex->innertext .= "<li class=\"definition\" id=\"$def_id\">";

                if (array_key_exists("abstract", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["abstract"]) > 0) {
                    foreach($word_data[$word][$def_id]["abstract"] as $abstract) {
                        if ($abstract["lang"] == $langs[0][0]) {
                            $ex->innertext .= "<p class=\"defmot\" lang=\"".$abstract["lang"]."\" dir=\"".$langs[0][1]."\">".htmlentities($abstract["value"])."</p>";
                        }
                    }
                }

                if (array_key_exists("subject", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["subject"]) > 0) {

                    $txt = "<p class=\"trad\" id=\"theme\"> Thématiques : </p><ul>";

                    $one = false;
                    foreach($word_data[$word][$def_id]["subject"] as $subject) {
                        if ($subject["lang"] == $langs[0][0]) {
                            $one = true;
                            $txt .= "<li lang=\"".$subject["lang"]."\" dir=\"".$langs[0][1]."\"><a href=\"".$protocol.$base_url."lexique?subject=".urlencode($subject["value"])."\">".$subject["value"]."</a></li>";
                        }
                    }

                    if ($one) {
                        $ex->innertext .= $txt . "</ul>";
                    }
                }

                if (array_key_exists("syn", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["syn"]) > 1) {

                    // On met sizeof($word_data[$word][$def_id]["syn"]) > 1 car le mot lui-même est présent dans les synonymes

                    $txt = "<p class=\"trad\" id=\"synonyme\">Synonymes : </p><p lang=\"ar\" dir=\"rtl\"> ";

                    $map = function($syn) use ($word, $protocol, $base_url, $word_data) {

                            if (array_key_exists($syn["value"], $word_data)) {
                                $link = "#".$syn["value"];
                            } else {
                                $link = ($protocol.$base_url."lexique/".urlencode($syn["value"]));
                            }
                            return "<i><a href=\"".$link."\">".$syn["value"]."</a></i>";
                        };

                    
                    $txt .= implode(" ، ", array_map($map, array_filter($word_data[$word][$def_id]["syn"], function($el) use ($word) {
                                if ($el["value"] != $word)
                                    return true;

                                return false;
                            })
                        )
                    );

                    $ex->innertext .= $txt . " </p>";

                }

                if (array_key_exists("coverage", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["coverage"]) > 0) {
                    $txt = "<p class=\"trad\" id=\"origine\"> Origines : </p><ul>";

                    $one = false;
                    foreach($word_data[$word][$def_id]["coverage"] as $coverage) {
                        if ($coverage["lang"] == $langs[0][0]) {
                            $one = true;
                            $txt .= "<li lang=\"".$coverage["lang"]."\" dir=\"".$langs[0][1]."\"><a href=\"".$protocol.$base_url."lexique?coverage=".urlencode($coverage["value"])."\">".$coverage["value"]."</a></li>";
                        }
                    }

                    if ($one) {
                        $ex->innertext .= $txt . " </ul>";
                    }
                }

                if (array_key_exists("pron", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["pron"]) > 0) {

                    $txt = "<p class=\"trad\" id=\"pron\"> Prononciations : </p><ul>";

                    $one = false;
                    foreach($word_data[$word][$def_id]["pron"] as $pron) {
                        if ($pron["lang"] == $langs[0][0]) {
                            $one = true;
                            $txt .= "<li lang=\"".$pron["lang"]."\" dir=\"".$langs[0][1]."\"><a href=\"".$protocol.$base_url."lexique?pron=".urlencode($pron["value"])."\">".$pron["value"]."</a></li>";
                        }
                    }

                    if ($one) {
                        $ex->innertext .= $txt . " </ul>";
                    }
                }

                if (array_key_exists("etymo", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["etymo"]) > 0) {

                    $txt = "<p class=\"trad\" id=\"etymo\"> Etymologie : </p><ul>";

                    $one = false;
                    foreach($word_data[$word][$def_id]["etymo"] as $etymo) {
                        if ($etymo["lang"] == $langs[0][0]) {
                            $txt .= "<li lang=\"".$etymo["lang"]."\" dir=\"".$langs[0][1]."\"><a href=\"".$protocol.$base_url."lexique?etymo=".urlencode($etymo["value"])."\">".$etymo["value"]."</a></li>";
                        }
                    }

                    if ($one) {
                        $ex->innertext .= $txt . " </ul>";
                    }
                }

                if (array_key_exists("example", $word_data[$word][$def_id]) && sizeof($word_data[$word][$def_id]["example"]) > 0) {

                    $txt = "<p class=\"trad\" id=\"exemple\"> Exemples : </p>";
                    $txt .= "<ul>";

                    $one = false;
                    foreach($word_data[$word][$def_id]["example"] as $example) {
                        if ($example["lang"] == "ar") {
                            $one = true;
                            $txt .= "<li lang=\"".$example["lang"]."\" dir=\"rtl\"><i>".$example["value"]."</i></li>";
                        }
                    }

                    if ($one) {
                        $ex->innertext .= $txt . " </ul>";
                    }
                }

                $ex->innertext .= "<br/></li>";
            }

            $ex->innertext .= "</ol>";
        }

    }
}

?>