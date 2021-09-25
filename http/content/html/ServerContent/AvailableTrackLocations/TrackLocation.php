<?php

namespace Content\Html;

class TrackLocation extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Track Location"),  _("Track Location"));
        $this->requirePermission("ViewServerContent_Tracks");
    }

    public function getHtml() {
        $html = "";

        // retrieve requests
        if (array_key_exists("Id", $_REQUEST) && $_REQUEST['Id'] != "") {
            $tl = \DbEntry\TrackLocation::fromId($_REQUEST['Id']);

            $html .= "<h1>" . $tl->name() . "</h1>";

            $html .= "<table id=\"TrackInfoGeneral\">";
            $html .= "<caption>" . _("Track Location Info") . "</caption>";
            $html .= "<tr><th>" . _("Location Name") . "</th><td>" . $tl->name() . "</td></tr>";
            $html .= "<tr><th>AC-Directory</th><td>content/tracks/" . $tl->track() . "</td></tr>";
            $html .= "<tr><th>" . _("Deprecated") . "</th><td>". (($tl->deprecated()) ? _("yes") : ("no")) . "</td></tr>";
            $html .= "</table>";

            $html .= "<div id=\"AvailableTracks\">";
            foreach ($tl->listTracks() as $t) {
                $html .= $t->html();
            }
            $html .= "</div>";

        } else {
            \Core\Log::warning("No Id parameter given!");
        }

        return $html;
    }
}

?>
