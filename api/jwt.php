<?php 

define('validationError', 'validationError');
define('expirationError', 'expirationError');
define('encryptionError', 'encryptionError');
define('decryptionError', 'decryptionError');

$iss = "your.domain.name";


function make_keyset() {

    /** Etape 1 : on définit la date d'expiration du trousseau (1 mois) */
    $exp = new DateTimeImmutable();
    // On définit la date d'expiration à minuit afin que cela affecte le moins possible les utilisateurs
    $exp = $exp->modify('+ 1 month')->setTime(0, 0)->getTimestamp(); 

    /** Etape 2 : On génère les clés */
    $keys = array("exp" => $exp, "keys" => array());

    

    // Token de session (signature)
    $key = generate_sym_key();
    $id  = "sig-session";
    $use = "sig";

    $keys['keys'][$id] = array("key" => base64_encode($key), "kid" => $id, "use" => $use);

    
    // Token de session (chiffrement)
    $key = generate_asym_key()[0];
    $id  = "enc-session";
    $use = "enc";

    $keys['keys'][$id] = array("key" => base64_encode($key), "kid" => $id, "use" => $use);

    // Token anti-CSRF (signature)
    $key = generate_sym_key();
    $id  = "sig-csrf";
    $use = "sig";

    $keys['keys'][$id] = array("key" => base64_encode($key), "kid" => $id, "use" => $use);
    

    // Chiffrement et déchiffrement d'identifiants (chiffrement)
    $k = generate_asym_key("enc-login");
    $key = $k[0];
    $public = $k[1];
    $id  = "enc-login";
    $use = "enc";

    $keys['keys'][$id] = array("key" => base64_encode($key), "use" => $use); 


    /** Etape 3 : on enregistre les clés */
    if (!is_dir("secure")) {
        mkdir("secure", 0770);
    }
    file_put_contents("secure/clefs.json", json_encode($keys));
    file_put_contents("secure/public_keys.json", json_encode(array("keys" => array($public))));

}


function generate_sym_key(int $length=512): string {
    return openssl_random_pseudo_bytes($length);
}


function generate_asym_key(string $id="", int $length=4096, string $key_use="enc"): array {

    $config = array(
        "private_key_bits" => $length,
        "private_key_type" => OPENSSL_KEYTYPE_RSA,
    );

    /** On génère la pair de clés */
    $key = openssl_pkey_new($config);
    
    if (!$key) {
        return array(false, false);
    }

    if (!openssl_pkey_export($key, $private_key, null, $config)) {
        return array(false, false);
    }

    $details = openssl_pkey_get_details($key);

    $public_key = array(
        "kty" => "RSA",
        "kid" => $id,
        "use" => $key_use,
        "n" => base64_encode($details['rsa']['n']),
        "e" => base64_encode($details['rsa']['e'])

    );

    return array($private_key, $public_key);

}


function get_public_key(string $private_key) {
    $pkey = openssl_pkey_get_private($private_key);
    return openssl_pkey_get_public(openssl_pkey_get_details($pkey)['key']);
}


function check_errors($value): bool {
    if ($value == validationError || $value == expirationError || $value == encryptionError || $value == decryptionError)
        return true;
    else
        return false;
}

/**
 * Classe regroupant les méthodes pour encoder ou décoder un message selon les standards définis par l'IETF.
 */
interface JsonWebProcess {

    /**
     * @param string $payload la charge utile / le message que l'on souhaite encoder
     * 
     * @return string 
     */
    static public function encode(string $payload, $key);


    /**
     * @param string $data le message encodé à déchiffrer ou valider
     * 
     * @return string
     */
    static public function decode(string $data, $key);

}


/**
 * Classe permettant l'encodage ou le décodage de Json Web Signatures. 
 * Cela signifie la validation mais aussi le décodage en tant que tel, permettant de récupérer les données si valides.
 */
class JWS implements JsonWebProcess {

    public static $header = '{"typ": "jwt", "alg": "HS512"}';
    public static $hashmethod = 'sha512';

    public static function encode(string $payload, $key) {


        // On encode l'en-tête et la charge utile en base64
        $header_64 = base64_encode(json_encode(self::$header));
        $payload_64 = base64_encode($payload);

        // On concatène le tout par un point
        $concat = $header_64 . "." . $payload_64;

        // On génère la signature des données (Message Authentication Code: MAC)
        $mac = hash_hmac(self::$hashmethod, $concat, $key);

        // On concatène la signature aux données par un point
        return $concat . "." . base64_encode($mac);

    }


    public static function decode(string $data, $key) {

        // On sépare les différents éléments de la chaîne : l'en-tête, la charge utile (le message) et la signature (un hash des deux premiers)
        $data = explode(".", $data);

        if (sizeof($data) != 3) {
            return decryptionError;
        }
        list($header_64, $payload_64, $mac_64) = $data;
        $concat = $header_64 . "." . $payload_64;

        // On récupère la version utf-8 de chaque élément en décodant la base64
        $header = json_decode(base64_decode($header_64), true);
        $payload = json_decode(base64_decode($payload_64), true);
        $mac = base64_decode($mac_64);

        // On valide le contenu en vérifiant que le hash des données est bien équivalent à celui transmis.
        $validation = hash_hmac(self::$hashmethod, $concat, $key);
        if (!hash_equals($validation, $mac)) {
            return validationError;
        }

        // On vérifie ensuite si le token est expiré ou non
        $t = new DateTimeImmutable();

        if (isset($payload["exp"]) && $t->getTimestamp() > $payload["exp"] ) {
            return expirationError;
        }

        return base64_decode($payload_64);
    }

}


/**
 * Classe permettant l'encodage et le décodage de Json Web Encryptions. 
 */
class JWE implements JsonWebProcess {

    public static $encryption = "aes-256-gcm";


    /**
     * @param string $payload les données à chiffer
     * @param string $key clé publique pour chiffrer les données ; doit faire au moins 512 octets (>= 4096 bits)
     * 
     * @return string
     */
    public static function encode(string $payload, $key) {

        
        $ivlen = openssl_cipher_iv_length(self::$encryption);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $header = array("alg" => "RSA-OAEP", "enc" => "A256GCM");
        $header_64 = base64_encode(json_encode($header));

        // On génère une clé publique pour chiffrer les données
        $encryption_key = openssl_random_pseudo_bytes(256);

        // On chiffre la clé avec la clé publique fournie à la fonction (elle pourra être déchiffrée par la clé privée associée)
        if (!openssl_public_encrypt($encryption_key, $encrypted_key, $key,  OPENSSL_PKCS1_OAEP_PADDING)) {
            return encryptionError;
        }

        // On chiffre les données avec la clé générée
        $sealed_data = openssl_encrypt($payload, self::$encryption, $encryption_key, $options=0, $iv, $tag, mb_convert_encoding($header_64, "ASCII", "BASE64"), 16);

        if (!$sealed_data) {
            return encryptionError;
        }
        
        // On met en forme la réponse 
        $encrypted_key_64 = base64_encode($encrypted_key);
        $iv_64 = base64_encode($iv);

        $tag_64 = base64_encode($tag);
        $sealed_data_64 = base64_encode($sealed_data);

        return $header_64 . "." . $encrypted_key_64 . "." . $iv_64 . "." . $sealed_data_64 . "." . $tag_64;
    }

    /**
     * @param string $data la chaîne à déchiffrer
     * @param mixed $key doit être la clé privée associée à la clé publique qui a été utilisée pour chiffrer la clé de contenu
     * 
     * @return array
     */
    public static function decode(string $data, $key) {

        $data = explode(".", $data);
        if (sizeof($data) != 5) {
            return decryptionError;
        }
        list($header_64, $encrypted_key_64, $iv_64, $sealed_data_64, $tag_64) = $data;

        $header = base64_decode($header_64);
        $encrypted_key = base64_decode($encrypted_key_64);
        $iv = base64_decode($iv_64);
        $sealed_data = base64_decode($sealed_data_64);
        $tag = base64_decode($tag_64);

        if (!openssl_private_decrypt($encrypted_key, $decryption_key, $key, OPENSSL_PKCS1_OAEP_PADDING)) {
            return decryptionError;
        }

        $opened_data = openssl_decrypt($sealed_data, self::$encryption, $decryption_key, $options=0, $iv, $tag, mb_convert_encoding($header_64, "ASCII", "BASE64"));

        if (!$opened_data) {
            return decryptionError;
        }

        return $opened_data;
    }
}


class JWT implements JsonWebProcess {

    public static function encode(string $payload, $key) {

        $iat = $nbf = new DateTimeImmutable();
        $exp = $iat->modify($key)->getTimestamp(); // '+6 minutes'

        return json_encode([
            'iat' => $iat->getTimestamp(),               // Issued at
            'iss' => $GLOBALS['iss'],                    // Issuer
            'nbf' => $nbf->getTimestamp(),               // Not before
            'exp' => $exp,                               // Expire
            'usr' => $payload,                           // User name
            'sid' => base64_encode(generate_sym_key(64)) // Session ID
        ]);
    }

    public static function decode(string $data, $key) {

        $decoded = json_decode($data, true);

        $t = new DateTimeImmutable();
        $now = $t->getTimestamp();

        if (!isset($decoded['iat']) || !isset($decoded['iss']) || !isset($decoded['nbf']) || !isset($decoded['exp']) || !isset($decoded['usr']) || $now < $decoded['nbf'] || $now > $decoded['exp']) {
            return validationError;
        }

        return ['usr' => $decoded['usr'], 'sid' => $decoded['sid']];
    }

}

///////////////////////////////////////////////////////////////////////////////////////////////////
/*
$test = array(
    "a" => "truc",
    "b" => "machin",
    "c" => "bidule",
);

$jwt = JWT::encode("test", '+6 minutes');

$jws = JWS::encode($jwt, "aaaaaaaaaaaaaaaaaaaaaaa");

//$jws = str_replace("wiOi", "iiii", $jws);



$key = openssl_pkey_new(array(
    "private_key_bits" => 4096,
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
));

if (!$key) {
    echo "Oh no.";
}

$public_key_pem = openssl_pkey_get_details($key)['key'];
$public_key = openssl_pkey_get_public($public_key_pem);

$jwe = JWE::encode($jws, $public_key);
//echo $jwe;
//$jwe = str_replace("i", "o", $jwe);

$decrypted = JWE::decode($jwe, $key);
$validated = JWS::decode($decrypted, "aaaaaaaaaaaaaaaaaaaaaaa");
echo JWT::decode($validated, '');
*/
?>