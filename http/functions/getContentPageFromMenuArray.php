<?php

// This function scans an array of cMenu menu objects.
// From the first active entry that has no active submenu entries a cContentPage object is created and returned.
// If no active entry was found NULL is returned.
function getContentPageFromMenuArray($menu_array) {
    $content_page = NULL;

    // scan all menus to find an active one
    foreach ($menu_array as $menu) {

        // this menu is active
        if ($menu->Active === True) {

            // check if submenu has active content
            if (count($menu->Menus)) {
                $submenu_content_page = getContentPageFromMenuArray($menu->Menus);
                if ($submenu_content_page !== NULL) {
                    return $submenu_content_page;
                }
            }

            // if submenu does not have active content this is the active content
            $content_page = new $menu->ClassName;
            return $content_page;
        }
    }

    // no active menu found
    return NULL;
}

?>
