<?php

// This function returns the language that is declared as preferred by the http client
// and is also available as localization

function getPreferredClientLanguage() {
    global $acswuiLog;
    global $acswuiUser;
    global $acswuiConfig;

    // check for manual selected locale
    if (in_array($acswuiUser->Locale, $acswuiConfig->Locales)) return $acswuiUser->Locale;

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

        // check against each available locales
        foreach ($acswuiConfig->Locales as $locale) {

            $locale_short = explode("_", $locale)[0];

            // if match found return language directory
            if (strpos($pref, $locale_short) !== False) {
                $acswuiLog->logNotice("preferred client language: ". $locale);
                return $locale;
            }
        }
    }

    // inform no language match found
    $msg = "Could not find translation!\n";
    $msg .= "Preferred translations: '" . implode("', '", $preferred) . "'\n";
    $acswuiLog->logWarning($msg);

    return "";
}

?>
