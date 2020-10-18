<?php

class tracks extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Tracks");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_ServerContent"];
    }

    public function getHtml() {

        // access global data
        global $acswuiDatabase;

        // initialize the html output
        $html  = "";

        foreach($acswuiDatabase->fetch_2d_array("Tracks", ["Id", "Track", "Config", "Name", "Length"], [], [], "Name") as $t) {
            $html .= '<div style="display: inline-block; margin: 5px;">';
            $html .= "<strong>" . $t["Name"] . "</strong><br>";
            $html .= getImgTrack($t['Id']);
            $html .= "</div>";
        }

        return $html;
    }
}

?>
