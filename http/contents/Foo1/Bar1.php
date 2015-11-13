<?php

class Bar1 extends cContentPage {

    public function __construct() {
        $this->MenuName           = "Bar1";
    }

    public function getHtml() {
        global $acswuiTemplate;

        $acswuiTemplate->Title = "My Bar1 Page Title";
        $acswuiTemplate->SubTitle = "My bar1 Page Subtitle";
        return "Content page Bar1";
    }
}

?>
