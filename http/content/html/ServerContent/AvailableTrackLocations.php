<?php

namespace Content\Html;

class AvailableTrackLocations extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Tracks"),  _("Available Track Locations"));
        $this->requirePermission("ServerContent_Tracks_View");
    }

    public function getHtml() {
        $html = "";

        foreach (\DbEntry\TrackLocation::listCountries() as $c) {
            $html .= "<h1>$c</h1>";

            foreach (\DbEntry\TrackLocation::listLocations(FALSE, $c) as $tl) {
                $html .= $tl->html();
            }
        }

        return $html;
    }
}

?>
