<?php 

abstract class Serializer {

    protected const TRANSLATION_LANG = 0;
    protected const TRANSLATION_TEXT = 1;
    protected const TRANSLATION_DIR  = 2;

    function __construct($db) {
        $this->db = $db;
    }

    abstract public function make(
        string $page, 
        string $base_url, 
        array  $langs, 
        array  $request, 
        string $protocol, 
        bool   $connected, 
        string $token       = "", 
        array  $word_data   = [], 
        array  $themes      = [],
        array $mimes        = []
            ): string;
    
    protected function fetch_translation(string $id, array $langs, string $base_url, string $protocol): array {

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