<?php 

require_once(__DIR__."/../vendor/autoload.php");
require_once("serializer.php");

class RDFSerializer extends Serializer {

    public function make(string $page, string $base_url, array $langs, array $request, string $protocol, bool $connected, string $token="", array $word_data = [], array $themes = []): string {

        $ns = array(
            'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'xsd' => 'http://www.w3.org/2001/XMLSchema#',
            'dcterms' => 'http://purl.org/dc/terms/',
            'lex' => $protocol.$base_url.'lexique/'
        );
        $conf = array('ns' => $ns);
        $ser = ARC2::getRDFXMLSerializer($conf);
        return $ser->getSerializedIndex($word_data);
    }
}

class TTLSerializer extends Serializer {

    public function make(string $page, string $base_url, array $langs, array $request, string $protocol, bool $connected, string $token="", array $word_data = [], array $themes = []): string {

        $ns = array(
            'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'xsd' => 'http://www.w3.org/2001/XMLSchema#',
            'dcterms' => 'http://purl.org/dc/terms/',
            'lex' => $protocol.$base_url.'lexique/'
        );
        $conf = array('ns' => $ns);
        $ser = ARC2::getTurtleSerializer($conf);
        return $ser->getSerializedIndex($word_data);
    }
}

?>