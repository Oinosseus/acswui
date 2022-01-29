<?php

namespace Content\Html;

class Track extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Track"),  _("Track"));
        $this->requirePermission("ServerContent_Tracks_View");
        $this->addScript("track.js");
    }

    public function getHtml() {
        $html  = '';

        // retrieve requests
        if (array_key_exists("Id", $_REQUEST) && $_REQUEST['Id'] != "") {
            $track = \DbEntry\Track::fromId($_REQUEST['Id']);
            $track_id = $track->id();
            $loc_id = $track->location()->id();

            $html .= "<h1>" . $track->name() . "</h1>";

            $html .= "<table id=\"TrackInfoGeneral\">";
            $html .= "<caption>" . _("General Info") . "</caption>";
            $html .= "<tr><th>" . _("Location Name") . "</th><td><a href=\"?HtmlContent=TrackLocation&Id=$loc_id\">" . $track->location()->name() . "</a></td></tr>";
            $html .= "<tr><th>" . _("Track Name") . "</th><td>" . $track->name() . "</td></tr>";
            $html .= "<tr><th>" . _("Length") . "</th><td>" . \Core\HumanValue::format($track->length(), "m") . "</td></tr>";
            $html .= "<tr><th>" . _("Pits") . "</th><td>" . $track->pitboxes() . "</td></tr>";
            $html .= "<tr><th>" . _("Driven Laps") . "</th><td>" . $track->drivenLaps() . "</td></tr>";
            $html .= "</table>";

            $html .= "<table id=\"TrackInfoRevision\">";
            $html .= "<caption>" . _("Revision Info") . "</caption>";
            $html .= "<tr><th>" . _("Version") . "</th><td>" . $track->version() . "</td></tr>";
            $html .= "<tr><th>" . _("Author") . "</th><td>" . $track->author() . "</td></tr>";
            $html .= "<tr><th>" . _("Database Id") . "</th><td>$track_id</td></tr>";
            $html .= "<tr><th>AC-Directory</th><td>content/tracks/" . $track->location()->track() .(($track->config() != "") ? "/" . $track->config() : "") . "</td></tr>";
            $html .= "<tr><th>" . _("Deprecated") . "</th><td>". (($track->deprecated()) ? _("yes") : ("no")) . "</td></tr>";
            $html .= "</table>";

            $html .= "<div id=\"TrackDescription\">";
            $html .= nl2br(htmlentities($track->description()));
            $html .= "</div>";

            $html .= "<a id=\"TrackImgPreview\" href=\"" . $track->previewPath() . "\">";
            $html .= "<img src=\"" . $track->previewPath() . "\">";
            $html .= "</a>";

            $html .= "<a id=\"TrackImgOutline\" href=\"" . $track->outlinePath() . "\">";
            $html .= "<img src=\"" . $track->outlinePath() . "\">";
            $html .= "</a>";

            // track records
            $html .= "<h1>" . _("Track Records") . "</h1>";
            $html .= "<button type=\"button\" trackId=\"" . $track->id() . "\" onclick=\"TrackLoadRecords(this)\">" . _("Load Track Records") . "</button>";
            $html .= "<span id=\"TrackRecordsList\"></span>";


        } else {
            \Core\Log::warning("No Id parameter given!");
        }

        return $html;
    }
}
