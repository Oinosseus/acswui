<?php

namespace Content\Html;

class Y_Settings extends \core\HtmlContent {

    private $CurrentPreset = NULL;


    public function __construct() {
        parent::__construct(_("Settings"),  "");
        $this->requirePermission("Settings_View");
    }


    public function getHtml() {
        $html = "";

        return $html;
    }
}
