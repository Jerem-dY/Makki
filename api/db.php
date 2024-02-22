<?php

include_once("vendor/autoload.php");


class DB {

    function __construct(string $config_path, string $db_init_path, string $prefixes_path) {

        $config = json_decode(file_get_contents($config_path), true);
        $this->store = ARC2::getStore($config);
        $this->store->createDBCon();

        $this->prefixes = file_get_contents($prefixes_path);

        if (!$this->store->isSetUp()) {
            $this->store->setUp();
        }


        $this->pdo = new PDO('mysql:host=localhost;dbname='.$config['db_name'], $config['db_user'], $config['db_pwd']);
        $this->pdo->query(file_get_contents($db_init_path));
    }

    public function query(string $q): array {

        $out = $this->store->query($this->prefixes . $q);

        if ($errs = $this->store->getErrors()) {
            echo "Query errors: ";
            return $errs;
        }

        return $out;
    }

    public function insert(string $q, string $g) {
        $this->store->insert(mb_convert_encoding($this->prefixes . $q, "UTF-8"), $g);
    }

    public function check_credentials(string $login, string $pwd): int {
        $q = $this->pdo->prepare("SELECT password, admin_id FROM `admin` WHERE login = ?");
        $q->execute([$login]);
        $res = $q->fetch();

        if (isset($res['password'])) {
            if (password_verify($pwd, $res['password'])) {
                return $res['admin_id'];
            }
        }
        return -1;
    }

    public function check_admin_id($id): bool {
        $q = $this->pdo->prepare("SELECT login FROM `admin` WHERE admin_id = ?");
        $q->execute([$id]);
        $res = $q->fetch();

        if (isset($res['login'])) {
            return true;
        }
        return false;
    }
}

?>