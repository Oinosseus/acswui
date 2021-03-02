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

        $last_section = "";
        foreach (Track::listTracks() as $t) {

            // check for section
            $current_section = strtoupper(substr($t->name(), 0, 1));
            if ($current_section != $last_section) {
                $html .= "<h1>$current_section</h1>";
                $last_section = $current_section;
            }


            $html .= '<div style="display: inline-block; margin: 5px;">';
            $html .= "<strong>" . $t->name() . "</strong><br>";
            $html .= $t->htmlImg("", 300);
            $html .= "</div>";
        }

        return $html;
    }
}

?>
