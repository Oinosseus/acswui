<?php

/**
 * Thios script needs to be executed multiple times a minute (eg. every ten seconds)
 * It is recommended to call this from commandline.
 * In any case it is mandatory to run this as http-user.
 *
 * following example can be used for cron:
 * * * * * *           cd /path/to/htdocs/; sudo -u wwwuser nice -n 19 /usr/bin/php cron.php &>/dev/null
 * * * * * * sleep 10; cd /path/to/htdocs/; sudo -u wwwuser nice -n 19 /usr/bin/php cron.php &>/dev/null
 * * * * * * sleep 20; cd /path/to/htdocs/; sudo -u wwwuser nice -n 19 /usr/bin/php cron.php &>/dev/null
 * * * * * * sleep 30; cd /path/to/htdocs/; sudo -u wwwuser nice -n 19 /usr/bin/php cron.php &>/dev/null
 * * * * * * sleep 40; cd /path/to/htdocs/; sudo -u wwwuser nice -n 19 /usr/bin/php cron.php &>/dev/null
 * * * * * * sleep 50; cd /path/to/htdocs/; sudo -u wwwuser nice -n 19 /usr/bin/php cron.php &>/dev/null
 */

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
session_set_cookie_params(0);
if (   ini_set('session.cookie_lifetime',  '0')  === false
    || ini_set('session.use_cookies',      'On') === false
    || ini_set('session.use_strict_mode',  'On') === false ) {
    echo "ini_set for session failed!";
    exit(1);
}
session_start();

// initialize global singletons
\Core\Log::initialize(\Core\Config::AbsPathData . "/logs_cron");
\Core\Database::initialize(\Core\Config::DbHost,
                           \Core\Config::DbUser,
                           \Core\Config::DbPasswd,
                           \Core\Config::DbDatabase);
\Core\UserManager::initialize();

// check if executed from command line
$CLI = stripos(php_sapi_name(), "cli") !== FALSE;

// execute cronjobs
// check if cronjobs are executed from commandline
// or if user is permitted to execute Cronjobs
if ($CLI || \Core\UserManager::currentUser()->permitted("Cronjobs_View")) {
    \Core\Cronjob::checkExecute();
} else {
    $id = \Core\UserManager::currentUser()->id();
    $interface = ($CLI) ? "CLI" : "HTTP";
    \Core\Log::warning("User '$id' is not permitted to execute cronjobs from $interface!");
}

// deinitialization of global singletons
\Core\Database::shutdown();

// finish log
$duration_end = microtime(TRUE);
$duration_ms = 1e3 * ($duration_end - $duration_start);
$msg = sprintf("Execution duration: %0.1f ms", $duration_ms);
\Core\Log::debug($msg);
\Core\Log::shutdown();
