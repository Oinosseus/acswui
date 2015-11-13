<?php

class Foo2Bar1 extends cContentPage {

    public function __construct() {
        $this->MenuName           = "Bar1";
    }

    public function getHtml() {
        return "Content page Foo1";
    }
}

?>
