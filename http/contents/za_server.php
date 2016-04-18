<?php

class za_server extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Server");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_ServerContent"];
    }

    public function getHtml() {

        return "";
    }
}

?>
