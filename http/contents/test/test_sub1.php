<?php

class test_sub1 extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Subtest 1");
        $this->TextDomain = "test";
    }

    public function getHtml() {
        global $acswuiTemplate;

        $acswuiTemplate->Title = _("Title of Subtest 1");
        $acswuiTemplate->SubTitle = _("Subtitle of Subtest 1");
        return $this->getRelPath();
        return _("This is the content of this page");
    }
}

?>
