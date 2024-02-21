<?php 

require_once("serializer.php");

class JSONSerializer extends Serializer {

    public function make(string $page, string $base_url, array $langs, array $request, string $protocol, bool $connected, string $token="", array $word_data = [], array $themes = [], array $mimes = []): string {

        return json_encode($word_data,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}

?>