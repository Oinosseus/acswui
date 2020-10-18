<?php

class yy_race_series extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Roster");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_ServerContent"];
    }

    public function getHtml() {

        return "";
    }
}

?>
