<?php 

function cmp_sec($a, $b) {
    
    if ($a[1] == $b[1]) {

        return 0;
    }

    return ($a[1] > $b[1]) ? -1 : 1;
}

?>