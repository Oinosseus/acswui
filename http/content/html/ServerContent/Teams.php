<?php

namespace Content\Html;

class Teams extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Teams"),  _("Teams"));
        $this->requirePermission("ServerContent_View");
    }

    public function getHtml() {
        $html = "";

        return $html;
    }
}

?>
