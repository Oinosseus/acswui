<?php

namespace Content\Html;

class AvailableCarClasses extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Car Classes"), _("Available Car Classes"), 'ServerContent');
        $this->requirePermission("ViewServerContent_CarClasses");
    }

    public function getHtml() {
        $html = "";

        foreach (\DbEntry\CarClass::listClasses() as $cc) {
            $html .= $cc->htmlImg();
        }

        return $html;
    }
}
