<?php
// To increase verbosity call cron.php?VERBOSE

error_reporting(-1);
if (ini_set('display_errors', '1') === false) {
    echo "ini_set for error display failed!";
    exit(1);
}

// execution performance
$acswui_execution_start_date  = date("Y-m-d H:i:s");
$acswui_execution_start_mtime = microtime(true);

//session_start();



// =====================
//  = Include Library =
// =====================

include("includes.php");



// =========================
//  = Fundamental Objects =
// =========================

$acswuiConfig   = new cConfig();
$acswuiLog      = new cLog("cron");
$acswuiLog->LogNotice("Execution start at " . $acswui_execution_start_date);
$acswuiDatabase = new cDatabase();
// $acswuiUser     = new cUser();



// =======================
//  = Cronjob Execution =
// =======================

// user information
$executed_jobs = array();
$not_executed_jobs = array();
if (isset($_GET["VERBOSE"])) {
    echo "<h1>Executed Jobs</h1>";
}

// scan cronjobs
foreach (scandir("cronjobs",SCANDIR_SORT_ASCENDING) as $entry) {

    // only care for php files
    if (substr($entry, -4, 4) != ".php") continue;

    // import cronjob class
    include("cronjobs/$entry");
    $job_class_name = substr($entry, 0, strlen($entry) - 4);

    // execute cronjob
    $job_object = new $job_class_name();
    $job_executed = $job_object->check_execute();

    // user information
    if (isset($_GET["VERBOSE"])) {

        if ($job_executed) {
            echo "<h2>$job_class_name</h2>";
            echo $job_object->getLog();

        } else {
            $not_executed_jobs[] = $job_class_name;
        }
    }
}

// user information
if (isset($_GET["VERBOSE"]) && count($not_executed_jobs) > 0) {
    echo "<h1>Not Executed Jobs</h1>";
    foreach ($not_executed_jobs as $job) {
        echo "$job<br>";
    }
}


// ======================
//  = Finish Execution =
// ======================

$acswuiLog->LogNotice("Execution finished at " . date("Y-m-d H:i:s") . " in " . (microtime(true) - $acswui_execution_start_mtime) . " seconds");

?>
