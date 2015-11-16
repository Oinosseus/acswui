<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

// session control
session_start();

if (!function_exists("_")) {
    echo("gettext() is not installed!");
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

// functions
include("functions/getMenuArrayFromContentDir.php");
include("functions/getPreferredClientLanguage.php");
include("functions/getContentPageFromMenuArray.php");



// =========================
//  = Fundamental Objects =
// =========================

$acswuiConfig = new cConfig();
$acswuiLog    = new cLog();



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

include("templates/" . $acswuiConfig->DefaultTemplate . "/cTemplate" . $acswuiConfig->DefaultTemplate . ".php");
$template_class = "cTemplate" . $acswuiConfig->DefaultTemplate;
$acswuiTemplate = new $template_class;
$acswuiTemplate->Menus = getMenuArrayFromContentDir();



// ====================
//  = Create Content =
// ====================

// create content object from active menu entry
$acswuiConentPage = getContentPageFromMenuArray($acswuiTemplate->Menus);

// load content
if (!is_null($acswuiConentPage)) {
    // determine localization
    $lang = getPreferredClientLanguage();
    if ($lang == "") $acswuiLog->LogWarning("Preferred localization could not be determined!");
    // load translation
    setlocale(LC_ALL, $lang);
    bindtextdomain($acswuiConentPage->TextDomain, "locale");
    bind_textdomain_codeset($acswuiConentPage->TextDomain, 'UTF-8');
    textdomain($acswuiConentPage->TextDomain);
    // put html from content to template
    $acswuiTemplate->Content .= $acswuiConentPage->getHtml();
}


// =================
//  = HTML Output =
// =================

echo $acswuiTemplate->getHtml();

?>
