<?php

class sa_session extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Session");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_ServerContent"];
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
