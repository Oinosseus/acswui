<?php

// This functions scans the contents directory for content pages
// and returns an array of cMenu objects that represent
// the menu for the content.

function getMenuArrayFromContentDir($dir = "") {

    $menu_array = array();

    // scan all files in directory
    foreach (scandir("contents/$dir",SCANDIR_SORT_ASCENDING) as $entry) {

        // skip hidden files, directories and non-php files
        if (substr($entry, 0, 1) === ".") continue;
        if (is_dir("contents/$dir/$entry")) continue;
        if (substr($entry, strlen($entry)-4, 4) != ".php") continue;

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

?>
