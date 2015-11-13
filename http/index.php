<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

// session control
session_start();



// ===========================
//  = Include Class Library =
// ===========================

include("include/cConfig.php");
include("include/cLog.php");
include("include/cMenu.php");
include("include/cTemplate.php");
include("include/cContentPage.php");



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



// =================
//  = Create Menu =
// =================

function getMenuArrayFromContentDir($dir = "") {

    $menu_array = array();

    // scan all files in directory
    foreach (scandir("contents/$dir",SCANDIR_SORT_ASCENDING) as $entry) {

        // skip hidden files and directories
        if (substr($entry, 0, 1) === ".") continue;
        if (is_dir("contents/$dir/$entry")) continue;

        // include contant class
        include("contents/$dir/$entry");
        $class = substr($entry, 0, strlen($entry) - 4);
        $content = new $class;

        // create new menu for the content
        $menu = new cMenu();
        $menu->Name              = $content->MenuName;
        $menu->ContentDirectory  = "$dir";
        $menu->ClassName         = $class;
        $menu->Url               = "?CONTENT=$dir/$class";

        // check if active
        if (strlen($_SESSION['CONTENT']) >= strlen("$dir/$class") && substr_compare($_SESSION['CONTENT'], "$dir/$class", 0, strlen("$dir/$class")) === 0) {
            $menu->Active = True;
        }

        // get subcontents of content
        if (is_dir("contents/$dir/$class/"))
            $menu->Menus = getMenuArrayFromContentDir("$dir/$class/");

        // add menu structure
        $menu_array[] = $menu;
    }

//     // scan all files in subdirectries that do not have a *.php in the actual directory
//     foreach (scandir("contents/$dir",SCANDIR_SORT_ASCENDING) as $entry) {
//
//         // skip hidden files and non-directories
//         if (substr($entry, 0, 1) === ".") continue;
//         if (!is_dir("contents/$dir/$entry")) continue;
//
//         // skip if directory was already scanned
//         $already_scanned = False;
//         foreach ($menu_array as $menu) {
//             if ($menu->ClassName == $entry) {
//                 $already_scanned = True;
//                 break;
//             }
//         }
//         if ($already_scanned === True) continue;
//
//         // create new menu for the content
//         $menu = new cMenu();
//         $menu->Name  = $entry;
//         $menu->Url   = "?CONTENT=$dir/$entry";
//         $menu->Menus = getMenuArrayFromContentDir("$dir/$entry/");
//
//         // check if active
//         if (strlen($_SESSION['CONTENT']) >= strlen("$dir/$entry") && substr_compare($_SESSION['CONTENT'], "$dir/$entry", 0, strlen("$dir/$entry")) === 0) {
//             $menu->Active = True;
//         }
//
//         // add menu structure
//         if (count($menu->Menus) > 0) $menu_array[] = $menu;
//     }

    return $menu_array;
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

$acswuiConentPage = NULL;

function callContentFromMenuArray($menu_array) {
    global $acswuiTemplate;
    global $acswuiConentPage;

    // scan all menus to find an active one
    foreach ($menu_array as $menu) {

        // this menu is active
        if ($menu->Active === True) {

            // check if submenu has active content
            if (count($menu->Menus) && callContentFromMenuArray($menu->Menus))
                return True;

            // if submenu does not have active content this is the active content
            $acswuiConentPage = new $menu->ClassName;
            return True;
        }
    }

    // no active menu found
    return False;
}

// create content object
callContentFromMenuArray($acswuiTemplate->Menus);

// put html from content to template
if (!is_null($acswuiConentPage))
    $acswuiTemplate->Content .= $acswuiConentPage->getHtml();



// =================
//  = HTML Output =
// =================

echo $acswuiTemplate->getHtml();

?>
