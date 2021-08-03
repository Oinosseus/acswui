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
            $html .= "<img src=\"" . $track->previewPath() . "\">";
            $html .= "<img src=\"" . $track->outlinePath() . "\">";

        } else {
            \Core\Log::warning("No Id parameter given!");
        }

        return $html;
    }
}
