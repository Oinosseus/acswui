<?php

class overview extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Overview");
        $this->PageTitle  = "Session Overview";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["Session", "Session_Overview"];

        // class local vars
        $this->SessionId = Null;
    }

    private function getCarName($carskin_id) {
        global $acswuiDatabase;

        $carname = "";
        $carskins = $acswuiDatabase->fetch_2d_array("CarSkins", ['Car'], ['Id'=>$carskin_id]);
        if (count($carskins) == 1) {
            $cars = $acswuiDatabase->fetch_2d_array("Cars", ['Name'], ['Id'=>$carskins[0]['Car']]);
            if (count($cars) == 1) {
                $carname = $cars[0]['Name'];
            }
        }

        return $carname;
    }

    public function getHtml() {

        // access global data
        global $acswuiConfig;
        global $acswuiLog;
        global $acswuiDatabase;
        global $acswuiUser;

        // get dictionary of drivers
        $drivers = array();
        foreach ($acswuiDatabase->fetch_2d_array('Users', ['Id', 'Login']) as $row) {
            $id = $row['Id'];
            $drivers[$id] = $row['Login'];
        }


        // --------------------------------------------------------------------
        //                        Process Post Data
        // --------------------------------------------------------------------

        if (isset($_GET['SESSION_ID'])) {
            $this->SessionId = (int) $_GET['SESSION_ID'];
        }



        // initialize the html output
        $html  = "";



        // --------------------------------------------------------------------
        //                            Session Select
        // --------------------------------------------------------------------

            // preset
        $html .= '<form action="" method="get">';
        $html .= _("Session Select");
        $html .= ' <select name="SESSION_ID" onchange="this.form.submit()">';
        foreach ($acswuiDatabase->fetch_2d_array("Sessions", ['Id', 'Timestamp', 'Name', 'Track'], [], "Id", FALSE) as $row) {

            // take first session if none is selected yet
            if ($this->SessionId === Null) {
                $this->SessionId = $row['Id'];
            }

            // get track
            $track = "";
            $tracks = $acswuiDatabase->fetch_2d_array("Tracks", ['Name'], ['Id'=>$row['Track']]);
            if (count($tracks) == 1) {
                $track = $tracks[0]['Name'];
            }

            $selected = ($this->SessionId == $row['Id']) ? "selected" : "";
            $id = $row['Id'];
            $name = $row['Timestamp'] . " - $track - " . $row['Name'];
            $html .= "<option value=\"$id\" $selected >$name</option>";
        }
        $html .= '</select>';
        $html .= '<br>';
        $html .= '</form>';



        // --------------------------------------------------------------------
        //                          Get Session Data
        // --------------------------------------------------------------------

        $session = new Session($this->SessionId);

//         foreach ($session->drivers() as $driver) {
//             $html .= "<table>";
//             foreach ($session->drivenLaps() as $lap) {
//                 if ($lap->user()->id() != $driver->id()) continue;
//                 if ($lap->cuts() != 0) continue;
//                 $html .= "<tr>";
//                 $html .= "<td>" . ($lap->id() - $session->firstDrivenLap()->id() + 1) . "</td>";
//                 $html .= "<td>" . $lap->laptime() . "</td>";
//                 $html .= "<td>" . $lap->user()->login() . "</td>";
//                 $html .= "</tr>";
//             }
//             $html .= "<table>";
//         }

        // get laps, ordered by laptime
        $laps = array();
        $laps = $session->drivenLaps();
        function compare_laptime($l1, $l2) {
            return ($l1->laptime() < $l2->laptime()) ? -1 : 1;
        }
        usort($laps, "compare_laptime");

        // get best laptime
        $laptime_best = NULL;
        foreach ($laps as $lap) {
            if ($laptime_best === NULL || $lap->laptime() < $laptime_best)
                $laptime_best = $lap->laptime();
        }




        // --------------------------------------------------------------------
        //                               Session Info
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Session Info") . "</h1>";

        // sesion info
        $html .= '<table>';

        $html .= '<tr>';
        $html .= '<th>' . _("Server/Session Name") . '</th>';
        $html .= '<th>' . _("Track") . '</th>';
        $html .= '<th>' . (($session->laps() == 0) ? _("Time") : _("Laps")) . '</th>';
        $html .= '<th>' . _("Temp Amb / Road") . '</th>';
        $html .= '<th>' . _("Grip") . '</th>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td>' . $session->serverName() . " / " . $session->name() . '</td>';
        $html .= '<td>' . $session->track()->name() . '</td>';
        $html .= '<td>' . (($session->laps() == 0) ? $session->time() : $session->laps()) . '</td>';
        $html .= '<td>' . HumanValue::format($session->tempAmb(), "°C") . " / " . HumanValue::format($session->tempRoad(), "°C") . '</td>';
        $html .= '<td>';
        if (count($session->drivenLaps()) > 0) {
            $html .= HumanValue::format($session->drivenLaps()[count($session->drivenLaps()) - 1]->grip() * 100, "%");
            $html .= " - ";
            $html .= HumanValue::format($session->drivenLaps()[0]->grip() * 100, "%");
        }
        $html .= '</td>';
        $html .= '</tr>';

        $html .= '</table>';

        // driver summary
        $html .= '<table>';

        $html .= '<tr>';
        $html .= '<th>' . _("Driver") . '</th>';
        $html .= '<th colspan="3">' . _("Driven") . '</th>';
        $html .= '</tr>';

        foreach ($session->drivers() as $user) {

            $lap_count = 0;
            foreach ($laps as $lap) {
                if ($lap->user()->id() == $user->id()) ++$lap_count;
            }

            $html .= '<tr>';
            $html .= '<td>' . $user->login() . '</th>';
            $html .= '<td>' . HumanValue::format($lap_count, "laps") . '</td>';
            $html .= '<td>' . HumanValue::format($lap_count * $session->track()->length(), "m") . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';


        // --------------------------------------------------------------------
        //                               Leaderboard
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Leaderboard") . "</h1>";


        $html .= '<table>';
        $html .= '<tr><th>' . _("Lap") . '</th><th>' . _("Laptime") . '</th><th>' . _("Delta") . '</th><th>' . _("Cuts") . '</th><th>' . _("Driver") . '</th><th>' . _("Car") . '</th><th>' . _("Ballast") . '</th><th>' . _("Restrictor") . '</th><th>' . _("Grip") . '</th>';

        $listed_user_ids = array();
        foreach ($laps as $lap) {
            if (in_array($lap->user()->id(), $listed_user_ids)) continue;

            $lap_number = $lap->id() - $session->firstDrivenLap()->id() + 1;

            $class = "class=\"";
            $class .= ($lap->cuts() > 0) ? " lap_invalid" : "";
            $class .= "\"";

            $html .= "<tr $class>";
            $html .= "<td>$lap_number</td>";
            $html .= "<td>" . HumanValue::format($lap->laptime(), "LAPTIME") . "</td>";
            $html .= "<td>" . HumanValue::format($lap->laptime() - $laptime_best, "ms") . "</td>";
            $html .= "<td>" . $lap->cuts() . "</td>";
            $html .= "<td>" . $lap->user()->login() . "</td>";
            $html .= "<td>" . $lap->carSkin()->car()->name() . "</td>";
            $html .= "<td>" . HumanValue::format($lap->ballast(), "kg") . "</td>";
            $html .= "<td>" . HumanValue::format($lap->restrictor(), "%") . "</td>";
            $html .= "<td>" . HumanValue::format(100 * $lap->grip(), "%") . "</td>";
            $html .= "<td>" . $lap->carSkin()->htmlImg("", 100) . "</td>";
            $html .= '</tr>';

            $listed_user_ids[] = $lap->user()->id();
        }
        $html .= '</table>';


        return $html;
    }

}

?>
