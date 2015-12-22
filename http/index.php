<?php
error_reporting(-1);
if (ini_set('display_errors', '1') === false) {
    echo "ini_set for error display failed!";
    exit(1);
}

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
include("classes/cMenu.php");
include("classes/cTemplate.php");
include("classes/cContentPage.php");
include("classes/cNonContentPage.php");
include("classes/cUser.php");

// functions
include("functions/getMenuArrayFromContentDir.php");
include("functions/getPreferredClientLanguage.php");
include("functions/getContentPageFromMenuArray.php");



// =========================
//  = Fundamental Objects =
// =========================

$acswuiConfig = new cConfig();
$acswuiLog    = new cLog();
$acswuiUser   = new cUser();



// ===========================
//  = Get Requested Content =
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

    // create content object from active menu entry
    $acswuiConentPage = getContentPageFromMenuArray($acswuiTemplate->Menus);

    // load content
    if (!is_null($acswuiConentPage)) {
        // determine localization
        $lang = getPreferredClientLanguage();
        if ($lang == "") $acswuiLog->LogWarning("Preferred localization could not be determined!");
        $acswuiLog->LogNotice("Localization '$lang' selected");
        // load translation
        setlocale(LC_ALL, $lang);
        bindtextdomain($acswuiConentPage->TextDomain, "locale");
        bind_textdomain_codeset($acswuiConentPage->TextDomain, 'UTF-8');
        textdomain($acswuiConentPage->TextDomain);
        // put html from content to template
        $acswuiTemplate->Content .= $acswuiConentPage->getHtml();
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

?>
