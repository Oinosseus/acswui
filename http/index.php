<?php
error_reporting(-1);
if (ini_set('display_errors', '1') === false) {
    echo "ini_set for error display failed!";
    exit(1);
}

// execution performance
$acswui_execution_start_date  = date("Y-m-d H:i:s");
$acswui_execution_start_mtime = microtime();

// session control
session_set_cookie_params(0);
if (   ini_set('session.cookie_lifetime',  '0')  === false
    || ini_set('session.use_cookies',      'On') === false
    || ini_set('session.use_strict_mode',  'On') === false ) {
    echo "ini_set for session failed!";
    exit(1);
}
session_start();



// =============================
//  = Check php Compatibility =
// =============================

// translation
if (!function_exists("_")) {
    echo("gettext() is not installed!");
    exit(1);
}

// password
if (!function_exists("password_verify")) {
    echo("password_verify() is not available (php version may be too low)!");
    exit(1);
}



// =====================
//  = Include Library =
// =====================

// classes
include("classes/cConfig.php");
include("classes/cLog.php");
include("classes/cDatabase.php");
include("classes/cMenu.php");
include("classes/cTemplate.php");
include("classes/cContentPage.php");
include("classes/cNonContentPage.php");
include("classes/cUser.php");

// functions
include("functions/getMenuArrayFromContentDir.php");
include("functions/getPreferredClientLanguage.php");
include("functions/getActiveMenuFromMenuArray.php");
include("functions/getImg.php");



// =========================
//  = Fundamental Objects =
// =========================

$acswuiConfig   = new cConfig();
$acswuiLog      = new cLog();
$acswuiLog->LogNotice("Execution start at " . $acswui_execution_start_date);
$acswuiDatabase = new cDatabase();
$acswuiUser     = new cUser();



// ===========================
//  = Get Content Requested =
// ===========================

// check if new page is requested
if (isset($_GET['CONTENT'])) {
    $_SESSION['CONTENT'] = $_GET['CONTENT'];
}

// reset session content
if (!isset($_SESSION['CONTENT'])) {
    $_SESSION['CONTENT'] = "";
}



// ============================
//  = Create Template Object =
// ============================

// non-content is requested
if (isset($_REQUEST['NONCONTENT'])) {

    // unset global template object
    $acswuiTemplate = Null;

// content is requested
} else {

    // load default template
    if (! file_exists("templates/" . $acswuiConfig->DefaultTemplate . "/cTemplate" . $acswuiConfig->DefaultTemplate . ".php")) {
        $acswuiLog->LogError("template '" . $acswuiConfig->DefaultTemplate . "' not found!");
    }
    $acswuiLog->LogNotice("include template '" . $acswuiConfig->DefaultTemplate ."'");
    include("templates/" . $acswuiConfig->DefaultTemplate . "/cTemplate" . $acswuiConfig->DefaultTemplate . ".php");
    $template_class = "cTemplate" . $acswuiConfig->DefaultTemplate;
    $acswuiTemplate = new $template_class;
    $acswuiTemplate->Menus = getMenuArrayFromContentDir();
}



// ====================
//  = Create Content =
// ====================

// non-content is requested
if (isset($_REQUEST['NONCONTENT'])) {

    // unset content
    $acswuiNonConentPage = Null;

    // scan non-content directory
    foreach (scandir("non-content/") as $file) {

        // ignore non *.php files
        if (substr($file, strlen($file) - 4, 4) != ".php") continue;

        // find requested non-contents
        if ($_REQUEST['NONCONTENT'] == substr($file, 0, strlen($file) - 4)) {

            // instantiate non-content class
            $acswuiLog->LogNotice("Non-Content request '" . $_REQUEST['NONCONTENT'] ."'");
            include("non-content/$file");
            $ncc = substr($file, 0, strlen($file) - 4);
            $acswuiNonConentPage = new $ncc;
            break;
        }
    }

    if ($acswuiNonConentPage == Null) {
        $acswuiLog->logWarning("Non-Content request not found: '" . $_REQUEST['NONCONTENT'] . "'");
        header("HTTP/1.0 404 Not Found");
        exit;
    }

// content is requested
} else {

    $menu = NULL;
    $acswuiContentPage = NULL;

    // create content object from active menu entry
     $menu = getActiveMenuFromMenuArray($acswuiTemplate->Menus);
     if (!is_null($menu)) {
        $acswuiContentPage = new $menu->ClassName;
        $acswuiContentPage->setMenu($menu);
        $acswuiLog->LogNotice("Content request '" . $_SESSION['CONTENT'] . "'");
     }

     // check content requirements
     if (!is_null($acswuiContentPage)) {

        // check root requirement
        if ($acswuiContentPage->RequireRoot && !$acswuiUser->IsRoot) {
            $path = $acswuiContentPage->getRelPath();
            $menu = $acswuiContentPage->MenuName;
            $acswuiLog->logError("Non-root user requested $path - $menu");
            header("HTTP/1.0 403 Forbidden");
            exit;
        }

        // check permissions
        $all_permissioms_available = true;
        foreach ($acswuiContentPage->RequirePermissions as $p) {
            if (!$acswuiUser->hasPermission($p)) {
                $all_permissioms_available = false;
            }
        }
        if (!$all_permissioms_available) {
            $path = $acswuiContentPage->getRelPath();
            $menu = $acswuiContentPage->MenuName;
            $acswuiLog->logError("User denied for $path - $menu");
            header("HTTP/1.0 403 Forbidden");
            exit;
        }
     }

    // load content
    if (!is_null($acswuiContentPage)) {
        // determine localization
        $lang = getPreferredClientLanguage();
        if ($lang == "") $acswuiLog->LogWarning("Preferred localization could not be determined!");
        $acswuiLog->LogNotice("Localization '$lang' selected");
        // load translation
        setlocale(LC_ALL, $lang);
        bindtextdomain($acswuiContentPage->TextDomain, "locale");
        bind_textdomain_codeset($acswuiContentPage->TextDomain, 'UTF-8');
        textdomain($acswuiContentPage->TextDomain);
        // put html from content to template
        $acswuiTemplate->Content .= $acswuiContentPage->getHtml();
        $acswuiTemplate->Title = $acswuiContentPage->PageTitle;
    }
}



// ====================
//  = Content Output =
// ====================

// non-content is requested
if (isset($_REQUEST['NONCONTENT'])) {

    // output content
    if ($acswuiNonConentPage != Null) {
        echo $acswuiNonConentPage->getContent();
    }

// content is requested
} else {
    echo $acswuiTemplate->getHtml();
}



// ======================
//  = Finish Execution =
// ======================

$acswuiLog->LogNotice("Execution finished at " . date("Y-m-d H:i:s") . " in " . (microtime() - $acswui_execution_start_mtime) . " seconds");


?>
