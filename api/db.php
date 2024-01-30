<?php

include_once("vendor/autoload.php");


class DB {

    function __construct(string $config_path) {

        $config = json_decode(file_get_contents($config_path), true);
        $this->store = ARC2::getStore($config);
        $this->store->createDBCon();

        if (!$this->store->isSetUp()) {
            $this->store->setUp();
        }
    }

    public function query(string $q): array {

        $out = $this->store->query($q);
        return $out;
    }
}

?>