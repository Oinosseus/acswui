<?php

class yy_race_classes extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Race Classes");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_ServerContent"];
    }

    public function getHtml() {

        return "";
    }
}

?>
