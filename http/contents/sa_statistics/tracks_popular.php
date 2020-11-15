<?php

class tracks_popular extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Popular Tracks");
        $this->PageTitle  = "Popular Tracks";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_Statistics"];

    }

    public function getHtml() {
        // access global data
        global $acswuiConfig;
        global $acswuiLog;
        global $acswuiDatabase;
        global $acswuiUser;




        $html = "";

        // list of tracks
        $tracks = Track::listTracks();
        function compare_popularity($t1, $t2) {
            return ($t1->popularity() > $t2->popularity()) ? -1 : 1;
        }
        uasort($tracks, 'compare_popularity');

        $html .= '<table>';
        $html .= '<tr><th>Popularity</th><th>Track</th><th>Pitboxes</th><th>Length</th><th>Drivers</th><th colspan="3">Driven</th></tr>';
        foreach ($tracks as $t) {
            if ($t->drivenLaps() == 0) continue;
            $html .= '<tr>';
            $html .= '<td>' . HumanValue::format($t->popularity() * 100, "%") . '</td>';
            $html .= '<td>' . $t->name() . '</td>';
            $html .= '<td>' . $t->pitboxes() . '</td>';
            $html .= '<td>' . HumanValue::format($t->length(), "m") . '</td>';
            $html .= '<td>' . count($t->drivers()) . '</td>';
            $html .= '<td>' . HumanValue::format($t->drivenLaps(), "L") . '</td>';
            $html .= '<td>' . HumanValue::format($t->drivenSeconds(), "s") . '</td>';
            $html .= '<td>' . HumanValue::format($t->drivenMeters(), "m") . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }
}

?>
