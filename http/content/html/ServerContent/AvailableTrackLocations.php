<?php

namespace Content\Html;

class AvailableTrackLocations extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Tracks"),  _("Available Track Locations"));
        $this->requirePermission("ServerContent_Tracks_View");
    }

    public function getHtml() {
        $html = "";

        $html .= "<h1>"  . _("Countries") . "</h1>";
        $html .= "<div id=\"ListOfCountries\">";
        $country_index = 0;
        foreach (\DbEntry\TrackLocation::listCountries() as $c) {
            $html .= "<a href=\"#CountryIndex$country_index\">$c</a>";
            ++$country_index;
        }
        $html .= "</div>";


        $country_index = 0;
        foreach (\DbEntry\TrackLocation::listCountries() as $c) {
            $html .= "<a id=\"CountryIndex$country_index\"></a>";
            ++$country_index;

            $html .= "<h1>$c</h1>";

            foreach (\DbEntry\TrackLocation::listLocations(FALSE, $c) as $tl) {
                $html .= $tl->html();
            }
        }

        return $html;
    }
}

?>
