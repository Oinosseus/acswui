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



        // scan tracks
        $tracks = array();
        foreach ($acswuiDatabase->fetch_2d_array("Tracks", ['Id', 'Name', 'Length', 'Pitboxes']) as $row) {

            $t['Name'] = $row['Name'];
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
        $tracks = Track::listTracks();
        function compare_driven_length($t1, $t2) {
            return ($t1->drivenMeters() > $t2->drivenMeters()) ? -1 : 1;
        }
        uasort($tracks, 'compare_driven_length');

        $html .= '<table>';
        $html .= '<tr><th>Track</th><th>Pitboxes</th><th>Length [km]</th><th>Driven Laps</th><th colspan="2">Driven</th></tr>';
        foreach ($tracks as $t) {
            if ($t->drivenLaps() == 0) continue;
            $html .= '<tr>';
            $html .= '<td>' . $t->name() . '</td>';
            $html .= '<td>' . $t->pitboxes() . '</td>';
            $html .= '<td>' . HumanValue::format($t->length(), "m") . '</td>';
            $html .= '<td>' . $t->drivenLaps() . '</td>';
            $html .= '<td>' . HumanValue::format($t->drivenSeconds(), "s") . '</td>';
            $html .= '<td>' . HumanValue::format($t->drivenMeters(), "m") . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }
}

?>
