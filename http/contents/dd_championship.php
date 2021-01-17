<?php

class dd_championship extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Championship");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["Championship_View"];
    }

    public function getHtml() {
        return "";
    }
}

?>
