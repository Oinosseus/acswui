<?php

namespace Content\Html;

class A_Home extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Home"),  "");
    }

    public function getHtml() {
        return "Hello Home World!";
    }
}
