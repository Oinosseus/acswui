<?php

class tracks_popular extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Popular Tracks");
        $this->PageTitle  = "Popular Tracks";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_Statistics"];

    }

    public function getHtml() {
        global $acswuiConfig;

        // get statistics
        $file_path = $acswuiConfig->AcsContent . "/stats_track_popularity.json";
        $popular_tracks = json_decode(file_get_contents($file_path), TRUE);

        $html = "";

        $html .= '<table>';
        $html .= '<tr><th>Popularity</th><th>Track</th><th>Pitboxes</th><th>Length</th><th>Drivers</th><th colspan="2">Driven</th></tr>';
        foreach ($popular_tracks as $pt) {
            $html .= '<tr>';
            $html .= '<td>' . HumanValue::format($pt['Popularity'] * 100, "%") . '</td>';
            $html .= '<td>' . $pt['Name'] . '</td>';
            $html .= '<td>' . $pt['Pitboxes'] . '</td>';
            $html .= '<td>' . HumanValue::format($pt['Length'], "m") . '</td>';
            $html .= '<td>' . count($pt['DriversList']) . '</td>';
            $html .= '<td>' . HumanValue::format($pt['DrivenLaps'], "L") . '</td>';
            $html .= '<td>' . HumanValue::format($pt['DrivenMeters'], "m") . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }
}

?>
