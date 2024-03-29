<?php

$duration_start = microtime(TRUE);

// error reporting
error_reporting(E_ALL | E_STRICT);
if (ini_set('display_errors', '1') === false) {
    echo "ini_set for error display failed!";
    exit(1);
}

// autoload of class library
spl_autoload_register(function($className) {
    $className = str_replace("\\", DIRECTORY_SEPARATOR, $className);
    $file_path = 'classes/' . $className . '.php';
    if (file_exists($file_path)) include_once $file_path;
});

// session control
session_set_cookie_params(60*60*24*2);
if (   ini_set('session.cookie_lifetime',  60*60*24*2)  === false
    || ini_set('session.use_cookies',      'On') === false
    || ini_set('session.use_strict_mode',  'On') === false ) {
    echo "ini_set for session failed!";
    exit(1);
}
session_start();

// initialize global singletons
\Core\Log::initialize(\Core\Config::AbsPathData . "/logs_http");
\Core\Database::initialize(\Core\Config::DbHost,
                           \Core\Config::DbUser,
                           \Core\Config::DbPasswd,
                           \Core\Config::DbDatabase);
\Core\UserManager::initialize();

// l10n
$lang = \Core\USerManager::currentUser()->locale();
$lang .= ".UTF-8";
putenv("LANG=$lang");
putenv("LANGUAGE=$lang");
putenv("LC_ALL=$lang");
setlocale(LC_ALL, $lang);
bindtextdomain("acswui", "./locale");
textdomain("acswui");

// check for requested page before login
if (\Core\UserManager::loggedUser() === NULL) {
    $url = ($_SERVER['HTTPS'] == "on") ? "https://" : "http://";
    $url .= $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $_SESSION['ACswuiUnloggedUrlRequest'] = $url;
} else if (array_key_exists("ACswuiUnloggedUrlRequest", $_SESSION)) {
    // forward to original requested URL before login
    $url = $_SESSION['ACswuiUnloggedUrlRequest'];
    unset($_SESSION['ACswuiUnloggedUrlRequest']);
    header("Location: $url");
}

// Setup template
if (array_key_exists("JsonContent", $_GET)) {
    echo \Core\JsonContent::getContent();
} else {
    $requested_template = \Core\Config::DefaultTemplate;
    $template = \Core\HtmlTemplate::getTemplate($requested_template);
    if ($template === NULL) {
        \Core\Log::error("Could not load template '$requested_template'!");
    } else {
        echo $template->getHtml();
    }
}

// deinitialization of global singletons
\Core\Database::shutdown();

// finish log
$duration_end = microtime(TRUE);
$duration_ms = 1e3 * ($duration_end - $duration_start);
$msg = sprintf("Execution duration: %0.1f ms", $duration_ms);
\Core\Log::debug($msg);
\Core\Log::shutdown();

?>
