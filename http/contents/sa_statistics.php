<?php

class sa_statistics extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Statistics");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_ServerContent", "View_Statistics"];
    }

    public function getHtml() {
        return "";
    }
}

?>
