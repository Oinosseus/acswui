<?php

namespace Content\Html;

class ServerContent extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Content"),  _("Server Content"));
        $this->requirePermission("ViewServerContent");
    }

    public function getHtml() {
        $html  = "";
        return $html;
    }
}

?>
