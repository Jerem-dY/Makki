<?php 

require_once("serializer.php");

class TEISerializer extends Serializer {

    public function make(string $page, string $base_url, array $langs, array $request, string $protocol, bool $connected, string $token="", array $word_data = [], array $themes = []): string {


        $title = $this->fetch_translation("title", $langs, $base_url, $protocol)[self::TRANSLATION_TEXT];
        $xml_lang = $langs[0][0];
        $authors = implode("", array_map(function($item){
            return "<persName>
                        <name>".$item[0]["value"]."</name>
                    </persName>";
        }, $this->get_values_of($word_data, "creator")));

        $output = new DOMDocument;
        $output->preserveWhiteSpace = FALSE;
        $output->loadXML(
            "<TEI xml:lang=\"$xml_lang\">
                <teiHeader>
                    <fileDesc>
                        <titleStmt>
                            <title>$title</title>
                            <author>
                                $authors
                            </author>
                        </titleStmt>
                        <publicationStmt>
                            <p></p>
                        </publicationStmt>
                        <sourceDesc>
                            <p></p>
                        </sourceDesc>
                    </fileDesc>
                </teiHeader>
                <text>
                    <body>
                        <div>
                            <head>$title</head>
                        </div>
                    </body>
                </text>
            </TEI>");




        $output->formatOutput = TRUE;
        return $output->saveXML();
    }

    function get_values_of(array $ar, string $key): array {

        $output = array();

        foreach(array_keys($ar) as $w) {

            foreach(array_keys($ar[$w]) as $def) {
                if (array_key_exists($key, $ar[$w][$def])) {
                    if (!in_array($ar[$w][$def][$key], $output)) {
                        array_push($output, $ar[$w][$def][$key]); 
                    }
                }
            }
        }

        return $output;
    }
}

?>