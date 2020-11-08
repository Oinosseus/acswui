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

        function compare_driven_length($t1, $t2) {
            $driven_t1 = $t1['DrivenLaps'] * $t1['Length'];
            $driven_t2 = $t2['DrivenLaps'] * $t2['Length'];
            return ($driven_t1 > $driven_t2) ? -1 : 1;
        }


        // scan tracks
        $tracks = array();
        foreach ($acswuiDatabase->fetch_2d_array("Tracks", ['Id', 'Name', 'Config', 'Length', 'Pitboxes']) as $row) {

            $t['Name'] = $row['Name'];
            if ($row['Config'] != "") $t['Name'] .= " - " . $row['Config'];
            $t['Length'] = $row['Length'];
            $t['Pitboxes'] = $row['Pitboxes'];
            $t['DrivenLaps'] = 0;
            $t['DrivenSeconds'] = 0;

            // count driven laps
            $query = "SELECT Laps.Laptime FROM Laps";
            $query .= " INNER JOIN Sessions ON Sessions.Id=Laps.Session";
            $query .= " WHERE Sessions.Track=" . $row['Id'];
            $res = $acswuiDatabase->fetch_raw_select($query);
            $t['DrivenLaps'] = count($res);
            foreach ($res as $row) {
                $t['DrivenSeconds'] += $row['Laptime'] / 1000;
            }

            if (count($res) > 0) $tracks[] = $t;
        }

        $html = "";

        // sort
        uasort($tracks, 'compare_driven_length');

        $html .= '<table>';
        $html .= '<tr><th>Track</th><th>Pitboxes</th><th>Length [km]</th><th>Driven Laps</th><th colspan="2">Driven</th></tr>';
        foreach ($tracks as $t) {
            $html .= '<tr>';
            $html .= '<td>' . $t['Name'] . '</td>';
            $html .= '<td>' . $t['Pitboxes'] . '</td>';
            $html .= '<td>' . sprintf("%.2f", $t['Length'] / 1000) . 'km</td>';
            $html .= '<td>' . $t['DrivenLaps'] . '</td>';
            $html .= '<td>' . sprintf("%.1f", $t['DrivenSeconds'] / 3600) . 'h</td>';
            $html .= '<td>' . sprintf("%.0f", $t['Length'] * $t['DrivenLaps'] / 1000) . 'km</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }
}

?>
