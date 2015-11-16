<?php

class test extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Test");
        $this->TextDomain = "test";
    }

    public function getHtml() {
        return _("This is the content of the test page.");
    }
}

?>
