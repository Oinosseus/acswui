<?php

namespace Content\Html;

class ServerContent extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Content"),  _("Server Content"));
        $this->requirePermission("ServerContent_View");
        $this->addScript("server_content.js");
    }

    public function getHtml() {
        $html  = "";

        $html .= "<h1>" . _("Popular Tracks") . "</h1>";
        $html .= "<button type=\"button\" onclick=\"LoadPopularTracks(this)\">" . _("Load Popular Tracks") . "</button>";
        $html .= "<div id=\"PopularTracks\"></div>";

        $html .= "<h1>" . _("Popular CarClasses") . "</h1>";
        $html .= "<button type=\"button\" onclick=\"LoadPopularCarClasses(this)\">" . _("Load Popular Car Classes") . "</button>";
        $html .= "<div id=\"PopularCarClasses\"></div>";

        return $html;
    }
}

?>
