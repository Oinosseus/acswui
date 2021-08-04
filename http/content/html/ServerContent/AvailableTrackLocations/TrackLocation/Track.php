<?php

namespace Content\Html;

class Track extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Track"),  _("Track"));
    }

    public function getHtml() {
        $html  = '';

        // retrieve requests
        if (array_key_exists("Id", $_REQUEST) && $_REQUEST['Id'] != "") {
            $track = \DbEntry\Track::fromId($_REQUEST['Id']);
            $track_id = $track->id();
            $loc_id = $track->location()->id();

            $html .= "<h1>" . _("Track Info") . "</h1>";

            // Track Info
            $html .= _("Location Name") . ": <a href=\"?HtmlContent=TrackLocation&Id=$loc_id\">" . $track->location()->name() . "</a><br>";
            $html .= _("Track Name") . ": " . $track->name() . "<br>";
            $html .= _("Length") . ": " . \Core\HumanValue::format($track->length(), "m") . "<br>";
            $html .= _("Pits") . ": " . $track->pitboxes() . "<br>";
            $html .= _("Driven Laps") . ": " . $track->drivenLaps() . "<br>";
            $html .= _("Database Id") . ": $track_id<br>";

            // AC info
            $html .= "AC-Directory: content/tracks/" . $track->location()->track();
            if ($track->config() != "") $html .= "/" . $track->config();
            $html .= "<br>";

            $html .= "<img src=\"" . $track->previewPath() . "\">";
            $html .= "<img src=\"" . $track->outlinePath() . "\">";

            $html .= "<h1>" . _("Lap Records") . "</h1>";
            $html .= "<h2>GT3</h2>";
            $html .= "<h2>GT4</h2>";


        } else {
            \Core\Log::warning("No Id parameter given!");
        }

        return $html;
    }
}
