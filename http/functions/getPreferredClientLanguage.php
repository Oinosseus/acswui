<?php

// This function returns the language that is declared as preferred by the http client
// and is also available as localization

function getPreferredClientLanguage() {
    global $acswuiLog;

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
    if (function_exists("apache_request_headers") && array_key_exists("Accept-Language", apache_request_headers())) {
        $preferred = explode(",", apache_request_headers()["Accept-Language"]);
    } else if (array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER)){
        $preferred = explode(",", $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    } else {
        $acswuiLog->logError("Cannot detect client languages!");
    }

    // decide for translation
    foreach ($preferred as $pref) {

        // remove qualifier
        $pref_no_qual = strstr($pref, ";q=", True);
        if ($pref_no_qual !== False) {
            $pref = $pref_no_qual;
        }

        // check against each available language
        foreach ($available as $avbl) {

            // if match found return language directory
            if (strpos($pref, $avbl['short']) !== False) {
                $acswuiLog->logNotice("preferred client language: ". $avbl['full']);
                return $avbl['full'];
            }
        }
    }

    // inform no language match found
    $msg = "Could not find translation!\n";
    $msg .= "Preferred translations: '" . implode("', '", $preferred) . "'\n";
    $msg .= "Available translations: '" . implode("', '", $available) . "'\n";
    $acswuiLog->logWarning($msg);

    return "";
}

?>
