<?php

namespace Content\Html;

class TrackLocation extends \core\HtmlContent {

    private bool $CanEditGeoLocation = False;

    public function __construct() {
        parent::__construct(_("Track Location"),  _("Track Location"));
        $this->requirePermission("ServerContent_Tracks_View");
    }

    public function getHtml() {
        $this->CanEditGeoLocation = \Core\UserManager::currentUser()->permitted("ServerContent_Tracks_UpdateGeoLocation");
        $html = "";


        # get requested track location
        $track_location = NULL;
        if (array_key_exists("Id", $_REQUEST) && $_REQUEST['Id'] != "") {
            $track_location = \DbEntry\TrackLocation::fromId($_REQUEST['Id']);
        } else {
            \Core\Log::warning("No Id parameter given!");
        }
        if ($track_location === NULL) return "";


        # save new geo location
        if ($this->CanEditGeoLocation) {
            if (array_key_exists("Action", $_POST) && $_POST['Action'] == "SaveGeoLocation") {
                $loc = \Core\GeoLocation::fromGeoUrl($_POST['GeoUrl']);
                $track_location->setGeoLocation($loc);
//                 $track_location->save();
            }
        }

        // retrieve requests
        $track_location = \DbEntry\TrackLocation::fromId($_REQUEST['Id']);

        $html .= "<h1>" . $track_location->name() . "</h1>";

        $html .= "<table id=\"TrackInfoGeneral\">";
        $html .= "<caption>" . _("Track Location Info") . "</caption>";
        $html .= "<tr><th>" . _("Location Name") . "</th><td>" . $track_location->name() . "</td></tr>";
        $html .= "<tr><th>AC-Directory</th><td>content/tracks/" . $track_location->track() . "</td></tr>";
        $html .= "<tr><th>" . _("Country") . "</th><td>". $track_location->country() . "</td></tr>";
        $html .= "<tr><th>" . _("Deprecated") . "</th><td>". (($track_location->deprecated()) ? _("yes") : ("no")) . "</td></tr>";
        $html .= "</table>";

        $html .= $this->newHtmlForm("post", "TrackInfoGeoLocation");
        $html .= "<table>";
        $html .= "<caption>" . _("Geographic Location") . "</caption>";
        $html .= "<tr><th>" . _("HTML URL") . "</th><td>". $track_location->geoLocation()->htmlLink() . "</td></tr>";
        $html .= "<tr><td colspan=\"2\">". $track_location->geoLocation()->htmlOsmEmbed() . "</td></tr>";

        $geourl = sprintf("geo:%0.F,%0.F", $track_location->geoLocation()->latitude(), $track_location->geoLocation()->longitude());
        if ($this->CanEditGeoLocation) {
            $html .= "<tr><th>" . _("Geo URL") . "</th><td><input type=\"text\" name=\"GeoUrl\" value=\"$geourl\"></td></tr>";
        } else {
            $html .= "<tr><th>" . _("Geo URL") . "</th><td><a href=\"$geourl\">$geourl</a></td></tr>";
        }

        $html .= "</table>";
        if ($this->CanEditGeoLocation) {
            $html .= "<button type=\"submit\" name=\"Action\" value=\"SaveGeoLocation\">" . _("Save") . "</button>";
        }
        $html .= "</form>";

        $html .= "<h2>" . _("Available Track Variants") . "</h2>";

        $html .= "<div id=\"AvailableTracks\">";
        foreach ($track_location->listTracks() as $t) {
            $html .= $t->html();
        }
        $html .= "</div>";


        // real weather
        $html .= "<h2>" . _("Real Weather") . "</h2>";
        $rwche = \Core\RealWeatherCache::fromTrackLocation($track_location);

        $html .= "<table id=\"RealWeaterData\">";

        $html .= "<tr>";
        $html .= "<th>" . _("Time") . "</th>";
        $html .= "<th>" . _("Weather Forecast") . "</th>";
        $html .= "<th>" . _("AC Weather") . "</th>";
        $html .= "</tr>";

        foreach ($rwche->conditions() as $rwc) {
            $html .= "<tr>";

            $html .= "<td>";
            $html .= \Core\UserManager::currentUser()->formatDateTimeNoSeconds($rwc->timestamp());
            $html .= "<br>" . _("Geo Time") . ": ";
            $html .= $rwc->timestamp()->format("H:i");
            $html .= "</td>";

            $html .= "<td>";
            $html .= $rwc->htmlImg();
            $html .= sprintf("%0.1f", $rwc->temperature()) . "&deg;C | ";
            $html .= sprintf("%0.1f", $rwc->precipitation()) . "mm/h<br>";

            $html .= round(100 * $rwc->humidity()) . "&percnt;H | ";
            $html .= round(100 * $rwc->cloudiness()) . "&percnt;C<br>";

            $html .= sprintf("%0.1f", $rwc->windSpeed()) . "m/s | ";
            $html .= $rwc->windDirection() . "&deg;<br>";
            $html .= "</td>";

            $html .= "<td>";
            $ac_weather = $rwc->weather();
            $html .= $ac_weather->parameterCollection()->child("Graphic")->valueLabel() . "<br>";
            $t_amb = $ac_weather->parameterCollection()->child("AmbientBase")->value();
            $html .= _("Ambient") . ": $t_amb&deg;C<br>";
            $road = $t_amb + $ac_weather->parameterCollection()->child("RoadBase")->value();
            $html .= _("Road") . ": $road&deg;C<br>";
            $html .= _("Wind") . ": " . $ac_weather->parameterCollection()->child("WindBaseMin")->valueLabel() . "m/s";
            $html .= "; " . $ac_weather->parameterCollection()->child("WindDirection")->valueLabel() . "&deg;<br>";
            $html .= "</td>";

            $html .= "</tr>";
        }

        $html .= "</table>";

        return $html;
    }
}

?>
