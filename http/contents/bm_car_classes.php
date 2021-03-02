<?php

class bm_car_classes extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Car Classes");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_ServerContent"];
    }

    public function getHtml() {
        return "";
    }
}

?>
