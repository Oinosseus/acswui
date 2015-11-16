<?php

class test_sub2 extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Subtest 2");
        $this->TextDomain = "test";
    }

    public function getHtml() {
        global $acswuiTemplate;

        $acswuiTemplate->Title = _("Title of Subtest 2");
        $acswuiTemplate->SubTitle = _("Subtitle of Subtest 2");
        return _("This is the content of this page");
    }
}

?>
