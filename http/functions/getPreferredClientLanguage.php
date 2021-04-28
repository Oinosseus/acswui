<?php

// This function returns the language that is declared as preferred by the http client
// and is also available as localization

function getPreferredClientLanguage() {

    // 2D array []['short', 'full']
    // for example short is 'en' and full is 'en_US'
    $available = array();
    foreach(scandir("locale/") as $dir) {

        // ignore hidden items and files
        if (substr($dir, 0, 1) == ".") continue;
        if (!is_dir("locale/$dir")) continue;

        $i = count($available);
        $available[$i]['full']  = $dir;
        $available[$i]['short'] = explode("_", $dir)[0];
    }

    // check preferred browser languages
    $preferred = array();
    if (function_exists("apache_request_headers")) {
        $preferred = explode(",", apache_request_headers()["Accept-Language"]);
    } else {
        $preferred = explode(",", $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    // decide for translation
    foreach ($preferred as $pref) {
        // check against each available language
        foreach ($available as $avbl) {
            // if match found return language directory
            if (strpos($pref, $avbl['short']) !== False)
                return $avbl['full'];
        }
    }

    return "";
}

?>
