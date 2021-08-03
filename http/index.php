<?php

$duration_start = microtime(TRUE);

// error reporting
error_reporting(E_ALL);
if (ini_set('display_errors', '1') === false) {
    echo "ini_set for error display failed!";
    exit(1);
}

// autoload of class library
spl_autoload_register(function($className) {
    $className = str_replace("\\", DIRECTORY_SEPARATOR, $className);

//     if (class_exists("\\core\\Log", FALSE) && class_exists("\\core\\Config", FALSE)) {
//         \core\Log::debug("AUTO-INC $className");
//     }

    $file_path = 'classes/' . $className . '.php';

    include_once $file_path;
});

// session control
session_set_cookie_params(0);
if (   ini_set('session.cookie_lifetime',  '0')  === false
    || ini_set('session.use_cookies',      'On') === false
    || ini_set('session.use_strict_mode',  'On') === false ) {
    echo "ini_set for session failed!";
    exit(1);
}
session_start();

// import namespaces
use \Core\Config;
use \Core\Log;
use \Core\Database;
use \Core\HtmlTemplate;

// initialize global singletons
Log::initialize(Config::AbsPathData . "/logs_http");
Database::initialize(Config::DbHost, Config::DbUser, Config::DbPasswd, Config::DbDatabase);

// Setup template
$requested_template = Config::DefaultTemplate;
$template = HtmlTemplate::getTemplate($requested_template);
if ($template === NULL) {
    Log::error("Could not load template '$requested_template'!");
} else {
    echo $template->getHtml();
}

// deinitialization of global singletons
Database::shutdown();

// finish log
$duration_end = microtime(TRUE);
$duration_ms = 1e3 * ($duration_end - $duration_start);
$msg = sprintf("Execution duration: %0.1f ms", $duration_ms);
Log::debug($msg);
Log::shutdown();

?>
