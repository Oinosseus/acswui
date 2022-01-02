<?php

namespace Content\Html;

class T_Settings extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Settings"),  "");
        $this->requirePermission("Settings_View");
    }


    public function getHtml() {
        $html = "";

        return $html;
    }
}
