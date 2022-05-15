<?php

/**
 * This returns an iCalender
 *
 * GET-Parameters:
 *  UserId - The ID of the requesting user (to allow custom settings)
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

// find User
if (array_key_exists("UserId", $_GET)) {
    $user = \DbEntry\User::fromId($_GET['UserId']);
} else {
    $user = \Core\USerManager::currentUser();
}

// l10n
$lang = $user->locale();
$lang .= ".UTF-8";
putenv("LANG=$lang");
putenv("LANGUAGE=$lang");
putenv("LC_ALL=$lang");
setlocale(LC_ALL, $lang);
bindtextdomain("acswui", "./locale");
textdomain("acswui");

// load events
header("Content-type:text/calendar");
$vcal = new \Core\VCalendar();
foreach (\DbEntry\SessionSchedule::listSchedules() as $ss) {
    $vev = \Core\VEvent::fromSessionSchedule($ss, $user);
    $vcal->addEvent($vev);
}
echo $vcal->ics();


// deinitialization of global singletons
\Core\Database::shutdown();

// finish log
$duration_end = microtime(TRUE);
$duration_ms = 1e3 * ($duration_end - $duration_start);
$msg = sprintf("Execution duration: %0.1f ms", $duration_ms);
\Core\Log::debug($msg);
\Core\Log::shutdown();

?>
