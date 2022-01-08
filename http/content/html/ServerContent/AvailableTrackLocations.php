<?php

namespace Content\Html;

class AvailableTrackLocations extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Tracks"),  _("Available Track Locations"));
        $this->requirePermission("ServerContent_Tracks_View");
    }

    public function getHtml() {
        $html = "";

        foreach (\DbEntry\TrackLocation::listLocations() as $tl) {
            $html .= $tl->html();
        }

        return $html;
    }
}

?>
