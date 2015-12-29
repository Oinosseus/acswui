<?php

// This function scans an array of cMenu menu objects.
// The first active entry that has no active submenu entries is returned.
// If no active entry was found NULL is returned.
function getActiveMenuFromMenuArray($menu_array) {

    // scan all menus to find an active one
    foreach ($menu_array as $menu) {

        // this menu is active
        if ($menu->Active === True) {

            // check if submenu has active content
            if (count($menu->Menus)) {
                $submenu = getActiveMenuFromMenuArray($menu->Menus);
                if ($submenu !== NULL) {
                    return $submenu;
                }
            }

            // if submenu does not have active content this is the active content
            return $menu;
        }
    }

    // no active menu found
    return NULL;
}

?>
