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

// helper
function get_user_name($user_id) : String {
    $res = \Core\Database::fetchRaw("SELECT Name FROM Users WHERE Id=$user_id;");
    return $res[0]['Name'];
}
function count_starts_driver($rser_season, $rser_class, $user_id) : int {
    $starts = 0;
    foreach ($rser_season->listEvents() as $event) {
        foreach ($event->listResultsDriver($rser_class) as $result) {
            if ($result->user()->id() == $user_id) {
                $starts++;
                break;
            }
        }
    }
    return $starts;
}
function count_wins_driver($rser_season, $rser_class, $user_id) : int {
    $starts = 0;
    foreach ($rser_season->listEvents() as $event) {
        foreach ($event->listResultsDriver($rser_class) as $result) {
            if ($result->position() == 1 && $result->user()->id() == $user_id) {
                $starts++;
                break;
            }
        }
    }
    return $starts;
}
function count_podiums_driver($rser_season, $rser_class, $user_id) : int {
    $starts = 0;
    foreach ($rser_season->listEvents() as $event) {
        foreach ($event->listResultsDriver($rser_class) as $result) {
            if ($result->position() <= 3 && $result->user()->id() == $user_id) {
                $starts++;
                break;
            }
        }
    }
    return $starts;
}
function verdict_performance_driver($rser_season, $rser_class, $user_id) : String {
    $verdict = "";

    $starts= count_starts_driver($rser_season, $rser_class, $user_id);
    if ($starts == 1) $verdict .= "1 Start";
    else $verdict .= "$starts Starts";

    $wins = count_wins_driver($rser_season, $rser_class, $user_id);
    if ($wins > 1) $verdict .= ", $wins Siege";
    else if ($wins == 1) $verdict .= ", 1 Sieg";

    $podiums = count_podiums_driver($rser_season, $rser_class, $user_id);
    if ($podiums > 1) $verdict .= ", $podiums Podien";
    else if ($podiums == 1) $verdict .= ", 1 Podium";

    return $verdict;
}
function name_first($name) : String {
    $firstnames = [];
    $explode = explode(" ", $name);
    for ($i=1; $i < count($explode); $i++) {
        $idx = $i-1;
        $firstnames[] = $explode[$idx];
    }
    return implode(" ", $firstnames);
}
function name_last($name) : String {
    $explode = explode(" ", $name);
    return $explode[count($explode)-1];
}


echo "<html>";
echo "<body>";
$season = \DbEntry\RSerSeason::fromId((int) $_REQUEST['season']);

echo "<h1>Classement</h1>";
echo "<table><tr>";
echo "<th>[0]Pos</th>";
echo "<th>[1]Points</th>";
echo "<th>[2]Team</th>";
echo "<th>[3]Drivers</th>";
echo "<th>[4]Verdict</th>";
echo "</tr>";
$position = 1;
$position_last = 1;
$points_last = 0;
foreach (\Compound\RSerTeamStanding::listStadnings($season) as $rs_ts) {
    if ($rs_ts->points() == $points_last) {
        $local_position = $position_last;
    } else {
        $local_position = $position;
        $position_last = $position;
    }
    echo "<tr>";
    echo "<td>{$local_position}</td>";
    echo "<td>{$rs_ts->points()}</td>";
    if (is_a($rs_ts->entry(), "\\DbEntry\\User")) {
        echo "<td>-</td>";
        echo "<td>" . get_user_name($rs_ts->entry()->id()) . "</td>";

    } else if (is_a($rs_ts->entry(), "\\DbEntry\\Team")) {
        echo "<td>{$rs_ts->entry()->name()}</td>";
        $driver_ids = [];
        foreach ($season->listRegistrations(NULL, TRUE) as $reg) {
            if (!$reg->teamCar() || $reg->teamCar()->team() != $rs_ts->entry()) continue;
            foreach ($reg->teamCar()->drivers() as $d) {
                if (!in_array($d->user()->id(), $driver_ids)) $driver_ids[] = $d->user()->id();
            }
        }
        $driver_names = [];
        foreach ($driver_ids as $uid) $driver_names[] = get_user_name($uid);
        $drivers = implode(", ", $driver_names);
        echo "<td>$drivers</td>";
    }
    echo "</tr>";
    ++$position;
    $points_last = $rs_ts->points();
}
echo "</table>";

foreach ($season->series()->listClasses(active_only:FALSE) as $rs_class) {
    echo "<h1>" . _("Driver Ranking") . " - {$rs_class->name()}</h1>";
    echo "<table><tr>";
    echo "<th>[0]Class</th>";
    echo "<th>[1]Pos</th>";
    echo "<th>[2]Pts</th>";
    echo "<th>[3]DriverFull</th>";
    echo "<th>[4]DriverFirst</th>";
    echo "<th>[5]DriverLast</th>";
    echo "<th>[6]Verdict</th>";
    echo "</tr>";
    foreach ($season->listStandingsDriver($rs_class) as $stdg) {
        $name = get_user_name($stdg->user()->id());
        $name_first = name_first($name);
        $name_last = name_last($name);
        $class_verdict = verdict_performance_driver($season, $rs_class, $stdg->user()->id());
        echo "<tr>";
        echo "<td>{$rs_class->name()}</td>";
        echo "<td>{$stdg->position()}</td>";
        echo "<td>{$stdg->points()}</td>";
        echo "<td>{$name}</td>";
        echo "<td>{$name_first}</td>";
        echo "<td>{$name_last}</td>";
        echo "<td>{$class_verdict}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "</body></html>";

// deinitialization of global singletons
\Core\Database::shutdown();

// finish log
$duration_end = microtime(TRUE);
$duration_ms = 1e3 * ($duration_end - $duration_start);
$msg = sprintf("Execution duration: %0.1f ms", $duration_ms);
\Core\Log::debug($msg);
\Core\Log::shutdown();

?>
