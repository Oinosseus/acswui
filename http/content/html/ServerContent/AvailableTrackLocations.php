<?php

namespace Content\Html;

class AvailableTrackLocations extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Tracks"),  _("Available Track Locations"));
    }

    public function getHtml() {
        $html = "";

        foreach (\DbEntry\TrackLocation::listLocations() as $tl) {
            $html .= $tl->htmlImg();
        }

        return $html;
    }
}

?>
