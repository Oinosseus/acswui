<?php

namespace Content\Html;

class AvailableTrackLocations extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Tracks"),  _("Available Track Locations"));
        $this->requirePermission("ServerContent_Tracks_View");
    }

    public function getHtml() {
        $html = "";

        $order = \Core\UserManager::currentUser()->getParam("UserTracksOrder");
        if ($order == "alphabet") {
            $html .= $this->listByAlphabet();
        } else if ($order == "country") {
            $html .= $this->listByCountry();
        } else {
            \Core\Log::error("Unexpected order '$order'!");
        }

        return $html;
    }


    private function listByCountry() {
        $html = "";

        $html .= "<h1>"  . _("Countries") . "</h1>";
        $html .= "<div id=\"TableOfContents\">";
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


    private function listByAlphabet() {

        # list track locations by name
        $letters = array();
        $last_letter = NULL;
        $html_trk = "";
        foreach (\DbEntry\TrackLocation::listLocations() as $tl) {

            $current_letter = strtoupper(substr($tl->name(), 0, 1));
            if ($last_letter === NULL || $last_letter != $current_letter) {
                $html_trk .= "<a id=\"Letter$current_letter\"></a>";
                $html_trk .= "<h1>$current_letter</h1>";
                $letters[] = $current_letter;
                $last_letter = $current_letter;
            }

            $html_trk .= $tl->html();
        }

        # create a TOC of first letters
        $html_toc = "";
        $html_toc .= "<h1>"  . _("Letters") . "</h1>";
        $html_toc .= "<div id=\"TableOfContents\">";
        foreach ($letters as $l) {
            $html_toc .= "<a href=\"#Letter$l\">$l</a>";
        }
        $html_toc .= "</div>";



        return $html_toc . $html_trk;
    }
}

?>
