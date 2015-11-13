<?php

class Foo1 extends cContentPage {

    public function __construct() {
        $this->MenuName           = "Foo1";
        $this->ParentContentClass = "";
    }

    public function getHtml() {
        return "Content page Foo1";
    }
}

?>
