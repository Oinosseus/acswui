<?php

class zm_session extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Session");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["Session_View"];
    }

    public function getHtml() {

        return "";
    }
}

?>
