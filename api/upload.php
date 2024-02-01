<?php 

const NORMALIZE_CHARS = array(
    'Š'=>'S', 'š'=>'s', 'Ð'=>'Dj','Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A',
    'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I',
    'Ï'=>'I', 'Ñ'=>'N', 'Ń'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U',
    'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss','à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a',
    'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i',
    'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ń'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u',
    'ú'=>'u', 'û'=>'u', 'ü'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y', 'ƒ'=>'f',
    'ă'=>'a', 'î'=>'i', 'â'=>'a', 'ș'=>'s', 'ț'=>'t', 'Ă'=>'A', 'Î'=>'I', 'Â'=>'A', 'Ș'=>'S', 'Ț'=>'T', 
    ' '=>'_', '\t'=>'__',
);

class FileUploader {

    function __construct(array $valid_ext) {

        $this->valid_ext = $valid_ext;

        /**
         * Etape 1 : on crée le dossier qui va accueillir les fichiers à traiter, s'il n'existe pas déjà.
         *  
         * */
        $this->uploads_dir = getcwd().DIRECTORY_SEPARATOR."uploads";
        $this->uploads_path = $this->uploads_dir.DIRECTORY_SEPARATOR."up_0".DIRECTORY_SEPARATOR;//.$_SESSION["user_id"].DIRECTORY_SEPARATOR;

        /*if(!is_dir($this->uploads_dir)){
            if(!mkdir($this->uploads_dir, 0777)){
                print "<br/>Couldn't make the directory ".$this->uploads_dir." for upload : '".$this->uploads_path."'. Aborting.<br/>";
                http_response_code(500);
            }
            else {
                chmod($ttl_file, 0777);
            }
        }*/

        if(is_dir($this->uploads_path)){

            if(!is_writable($this->uploads_path)){
                #print "<br/>Upload dir #".$_SESSION["user_id"]." is not writeable. Aborting.<br/>";
                print "<br/>Upload dir is not writeable. Aborting.<br/>";
                http_response_code(500);
            }
        }else{
            if(!mkdir($this->uploads_path, 0777)){
                print "<br/>Couldn't make the directory for upload : '".$this->uploads_path."'. Aborting.<br/>";
                http_response_code(500);
            }
            else {
                chmod($this->uploads_path, 0777);
            }
        }
    }

    public function upload(array $filelist) {

        /**
         * Etape 2 : on vérifie les fichiers à uploader.
         *  
         * */
        if(!isset($filelist)){
            print "<br/>No files to upload. Aborting.<br/>";
            http_response_code(400);
        }

        $size = 0;
        for ($i = 0 ; $i < sizeof($filelist["size"]) ; $i++) {
            $size += $filelist['size'][$i];

            $fname = explode('.', $filelist['name'][$i]);
            $fname = end($fname);
            if (!in_array($fname, $this->valid_ext)) {
                echo "<br/>Wrong filetype. Aborting.<br/>";
                http_response_code(400);
            }
        }

        if($size > 500000){
            print "<br/>Upload size is too big : ".$size.". Aborting.<br/>";
            http_response_code(400);
        }


        /**
         * Etape 3 : on upload les fichiers à traiter.
         *  
         * */

        $this->files = array();

        for ($i = 0 ; $i < sizeof($filelist["size"]) ; $i++) {

            $target_path = $this->uploads_path.basename(strtr($filelist['name'][$i], NORMALIZE_CHARS));

            if (move_uploaded_file($filelist['tmp_name'][$i], $target_path)) {
                array_push($this->files, $target_path);
                if (!chmod($target_path, 0666)) {
                    echo "<br/>Something went wrong with permissions...<br/>";
                }
                echo "<br/>File '".$filelist['name'][$i]."' is valid, and was successfully uploaded at '".$target_path."'.<br/>";
            } else {
                echo "<br/>Possible file upload attack! File '".$filelist['name'][$i]."' not uploaded.<br/>";
            }
        }
    }

    public function get_filenames(): array {
        return $this->files;
    }


    public function delete() {
        /**
         * Etape 5 : on supprime les fichiers temporaires du serveur.
         * 
         * */

        foreach($this->files as $del_file){

            echo "<br/>Deleting '". $del_file . "'.<br/>";

            if(!is_file($del_file)){
                echo "<br/>Something went wrong...<br/>";
            }

            if(!unlink($del_file)){
                echo "<br/>Couldn't delete file '".$del_file."'!<br/>";
            }
        }
    }
}

?>