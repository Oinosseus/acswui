<?php

namespace Content\Html;

class TrackLocation extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Track Location"),  _("Available Tracks At Location"));
    }

    public function getHtml() {
        $html = "";

        // retrieve requests
        if (array_key_exists("Id", $_REQUEST) && $_REQUEST['Id'] != "") {
            $tl = \DbEntry\TrackLocation::fromId($_REQUEST['Id']);

            $html .= "<div id=\"AvailableTracks\">";
            foreach ($tl->listTracks() as $t) {
                $html .= $t->htmlImg();
            }
            $html .= "</div>";

        } else {
            \Core\Log::warning("No Id parameter given!");
        }

        return $html;
    }
}

?>
