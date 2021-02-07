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

        $html = "";

        $html .= '<table>';
        $html .= '<tr><th>Popularity</th><th>Track</th><th>Pitboxes</th><th>Length</th><th colspan="2">Driven</th></tr>';
        foreach (StatsTrackPopularity::listLatest() as $stp) {
            if ($stp->popularity() == 0) continue;

            $track = $stp->track();

            $html .= '<tr>';
            $html .= '<td>' . HumanValue::format($stp->popularity(), "%") . '</td>';

            $link_url = "?CONTENT=/sa_statistics//records_track&TRACK_ID=" . $track->id();
            $link_name = $track->name();
            $html .= "<td><a href=\"$link_url\">$link_name</a></td>";

            $html .= '<td>' . $track->pitboxes() . '</td>';
            $html .= '<td>' . HumanValue::format($track->length(), "m") . '</td>';
            $html .= '<td>' . HumanValue::format($stp->lapCount(), "L") . '</td>';
            $html .= '<td>' . HumanValue::format($stp->lapCount() * $track->length(), "m") . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }
}

?>
