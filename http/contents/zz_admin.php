<?php

class zz_admin extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Admin");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["Admin_User_Management"];
    }

    public function getHtml() {

        // access global data
        global $acswuiUser;

        // initialize the html output
        $html  = "";

        return $html;
    }
}

?>
