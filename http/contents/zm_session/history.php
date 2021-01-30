<?php

class history extends cContentPage {
    private $Session = NULL;
    private $FilterRace = TRUE;
    private $FilterQual = TRUE;
    private $FilterPrtc = TRUE;
    private $FilterTrck = NULL;


    public function __construct() {
        $this->MenuName   = _("History");
        $this->PageTitle  = "Session History";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["Session", "Session_History"];
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

        if (array_key_exists("SESSION_SELECT", $_REQUEST)) {

            if (isset($_REQUEST['SESSION_ID'])) {
                $this->Session = new Session((int) $_REQUEST['SESSION_ID']);
                $_SESSION['SESSION_ID'] = $this->Session->id();
            }

            $_SESSION['SESSION_FILTER_RACE'] = array_key_exists("SESSION_FILTER_RACE", $_REQUEST);
            $_SESSION['SESSION_FILTER_QUAL'] = array_key_exists("SESSION_FILTER_QUAL", $_REQUEST);
            $_SESSION['SESSION_FILTER_PRTC'] = array_key_exists("SESSION_FILTER_PRTC", $_REQUEST);
            $_SESSION['SESSION_FILTER_TRCK'] = $_REQUEST["SESSION_FILTER_TRCK"];
        }

        if (isset($_SESSION['SESSION_ID'])) {
            $this->Session = new Session((int) $_SESSION['SESSION_ID']);
        }

        if (isset($_SESSION['SESSION_FILTER_RACE'])) {
            $this->FilterRace = $_SESSION['SESSION_FILTER_RACE'];
        }

        if (isset($_SESSION['SESSION_FILTER_QUAL'])) {
            $this->FilterQual = $_SESSION['SESSION_FILTER_QUAL'];
        }

        if (isset($_SESSION['SESSION_FILTER_PRTC'])) {
            $this->FilterPrtc = $_SESSION['SESSION_FILTER_PRTC'];
        }

        if (isset($_SESSION['SESSION_FILTER_TRCK'])) {
            if ($_SESSION['SESSION_FILTER_TRCK'] > 0) {
                $this->FilterTrck = $_SESSION['SESSION_FILTER_TRCK'];
            } else {
                $this->FilterTrck = NULL;
            }
        }



        // initialize the html output
        $html  = "";



        // --------------------------------------------------------------------
        //                            Session Select
        // --------------------------------------------------------------------

        $html .= '<form action="" method="post">';
        $html .= "<input type=\"hidden\" name=\"SESSION_SELECT\" value=\"\">";
        $html .= _("Session Select");
        $html .= ' <select name="SESSION_ID" onchange="this.form.submit()">';
        foreach ($acswuiDatabase->fetch_2d_array("Sessions", ['Id', 'Timestamp', 'Name', 'Track', 'Type'], [], "Id", FALSE) as $row) {

            // filter
            if ($row['Type'] == 1 && $this->FilterPrtc == FALSE) continue;
            if ($row['Type'] == 2 && $this->FilterQual == FALSE) continue;
            if ($row['Type'] == 3 && $this->FilterRace == FALSE) continue;
            if ($this->FilterTrck !== NULL && $this->FilterTrck != $row['Track']) continue;

            // take first session if none is selected yet
            if ($this->Session === Null) {
                $this->Session = new Session($row['Id']);
            }

            // get track
            $track = "";
            $tracks = $acswuiDatabase->fetch_2d_array("Tracks", ['Name'], ['Id'=>$row['Track']]);
            if (count($tracks) == 1) {
                $track = $tracks[0]['Name'];
            }

            $selected = ($this->Session->id() == $row['Id']) ? "selected" : "";
            $id = $row['Id'];
            $name = "[" . $row['Id'] . "] " . $row['Timestamp'] . " - $track - " . $row['Name'];
            $html .= "<option value=\"$id\" $selected >$name</option>";
        }
        $html .= '</select>';
        $html .= '<br>';

        // filter race
        $checked = ($this->FilterRace) ? "checked=\"yes\"" : "";
        $html .= "Filter Races: <input type=\"checkbox\" name=\"SESSION_FILTER_RACE\" value=\"TRUE\" $checked onchange=\"this.form.submit()\"><br>";

        // filter qualifying
        $checked = ($this->FilterQual) ? "checked=\"yes\"" : "";
        $html .= "Filter Qualifying: <input type=\"checkbox\" name=\"SESSION_FILTER_QUAL\" value=\"TRUE\" $checked onchange=\"this.form.submit()\"><br>";

        // filter practice
        $checked = ($this->FilterPrtc) ? "checked=\"yes\"" : "";
        $html .= "Filter Practice: <input type=\"checkbox\" name=\"SESSION_FILTER_PRTC\" value=\"TRUE\" $checked onchange=\"this.form.submit()\"><br>";

        // filter track
        $html .= "Filter Track: <select name=\"SESSION_FILTER_TRCK\" onchange=\"this.form.submit()\">";
        $selected = ($this->FilterTrck === NULL) ? "selected" : "";
        $html .= "<option value=\"0\"  $selected>&lt;" . _("any Track") . "&gt;</option>";
        foreach (Track::listTracks() as $t) {
            $id = $t->id();
            $name = $t->name();
            $selected = ($this->FilterTrck == $t->id()) ? "selected" : "";
            $html .= "<option value=\"$id\"  $selected>$name</option>";
        }
        $html .= "</select>";

        $html .= '</form>';



        // --------------------------------------------------------------------
        //                          Get Session Data
        // --------------------------------------------------------------------

        // get laps, ordered by laptime
        $laps = array();
        $laps = $this->Session->drivenLaps();

        // laps ordered by laptime
        $best_laps = array();
        foreach ($laps as $lap) {
            if ($lap->cuts() == 0) $best_laps[] = $lap;
        }
        function compare_laptime($l1, $l2) {
            return ($l1->laptime() < $l2->laptime()) ? -1 : 1;
        }
        usort($best_laps, "compare_laptime");

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
        $html .= '<th>' . (($this->Session->laps() == 0) ? _("Time") : _("Laps")) . '</th>';
        $html .= '<th>' . _("Temp Amb / Road") . '</th>';
        $html .= '<th>' . _("Grip") . '</th>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td>' . $this->Session->serverName() . " / " . $this->Session->name() . '</td>';
        $html .= '<td>' . $this->Session->track()->name() . '</td>';
        $html .= '<td>' . (($this->Session->laps() == 0) ? $this->Session->time() : $this->Session->laps()) . '</td>';
        $html .= '<td>' . HumanValue::format($this->Session->tempAmb(), "°C") . " / " . HumanValue::format($this->Session->tempRoad(), "°C") . '</td>';
        $html .= '<td>';
        if (count($this->Session->drivenLaps()) > 0) {
            $html .= HumanValue::format($this->Session->drivenLaps()[count($this->Session->drivenLaps()) - 1]->grip() * 100, "%");
            $html .= " - ";
            $html .= HumanValue::format($this->Session->drivenLaps()[0]->grip() * 100, "%");
        }
        $html .= '</td>';
        $html .= '</tr>';

        $html .= '</table>';



        // --------------------------------------------------------------------
        //                               Session Results
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Session Results") . "</h1>";


        // race position diagram
        if ($this->Session->type() == 3) {
            $html .= $this->diagramRacePosition();
        }


        // Driver Summary
        $html .= '<table>';

        $html .= '<tr>';
        $html .= '<th rowspan="2">' . _("Position") . '</th>';
        $html .= '<th rowspan="2">' . _("Driver") . '</th>';
        $html .= '<th rowspan="2">' . _("Car") . '</th>';
        $html .= '<th rowspan="2">' . _("Best Lap") . '</th>';
        $html .= '<th rowspan="2">' . _("Total Time") . '</th>';
        $html .= '<th rowspan="2">' . _("Ballast") . '</th>';
        $html .= '<th rowspan="2">' . _("Restrictor") . '</th>';
        $html .= '<th rowspan="1" colspan="2">' . _("Driven") . '</th>';
        $html .= '<th rowspan="2">' . _("Cuts") . '</th>';
        $html .= '<th rowspan="1" colspan="2">' . _("Collisions") . '</th>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<th>' . _("Laps") . '</th>';
        $html .= '<th>' . _("Distance") . '</th>';
        $html .= '<th>' . _("Environment") . '</th>';
        $html .= '<th>' . _("Other Car") . '</th>';
        $html .= '</tr>';

        foreach ($this->Session->results() as $rslt) {

            $distance = $rslt->amountLaps() * $rslt->session()->track()->length();

            $html .= '<tr>';
            $html .= '<td>' . $rslt->position() . '</th>';
            $html .= '<td>' . $rslt->user()->login() . '</th>';
            $html .= '<td>' . $rslt->carSkin()->htmlImg("", 50) . '</th>';
            $html .= '<td>' . HumanValue::format($rslt->bestlap(), "LAPTIME") . '</td>';
            $html .= '<td>' . HumanValue::format($rslt->totaltime(), "ms") . '</td>';
            $html .= '<td>' . HumanValue::format($rslt->ballast(), "kg") . '</td>';
            $html .= '<td>' . HumanValue::format($rslt->restrictor(), "%") . '</td>';
            $html .= '<td>' . $rslt->amountLaps() . '</th>';
            $html .= '<td>' . HumanValue::format($distance, "m") . '</td>';
            $html .= '<td>' . $rslt->amountCuts() . '</th>';
            $html .= '<td>' . $rslt->amountCollisionEnv() . '</th>';
            $html .= '<td>' . $rslt->amountCollisionCar() . '</th>';
            $html .= '</tr>';
        }

        $html .= '</table>';



        // --------------------------------------------------------------------
        //                               Collisions
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Collisions") . "</h1>";

        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<th>" . _("Driver") . "</th>";
        $html .= "<th>" . _("Other Driver") . "</th>";
        $html .= "<th>" . _("Speed") . "</th>";
        $html .= "<th>" . _("Timestamp") . "</th>";
        $html .= "</tr>";

        foreach ($this->Session->collisions() as $cll) {

            $other = "";
            if ($cll->otherUser() != NULL) $other = $cll->otherUser()->login();

            $class = "class=\"";
            $class .= ($cll->secondary()) ? " secondary_collision" : "";
            $class .= "\"";

            $html .= "<tr $class>";
            $html .= "<td>" . $cll->user()->login() . "</td>";
            $html .= "<td>$other</td>";
            $html .= "<td>" . sprintf("%0.0f", $cll->speed()) . "</td>";
            $html .= "<td>" . $cll->timestamp()->format("Y-m-d H:i:s") . "</td>";
            $html .= "<td>" . $cll->id() . "</td>";
            $html .= "</tr>";
        }

        $html .= "</table>";



        // --------------------------------------------------------------------
        //                               Fastest Laps
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Fastest Laps") . "</h1>";

        # diagram
        $svg_path = $acswuiConfig->AcsContent . "/session_lap_diagrams/session_" . $this->Session->id() . ".svg";
        if (file_exists($svg_path)) {
            $html .= "<img src=\"$svg_path\" class=\"session_lap_diagram\">";
        }

        $html .= '<table>';
        $html .= '<tr><th>' . _("Lap") . '</th><th>' . _("Laptime") . '</th><th>' . _("Delta") . '</th><th>' . _("Cuts") . '</th><th>' . _("Driver") . '</th><th>' . _("Car") . '</th><th>' . _("Ballast") . '</th><th>' . _("Restrictor") . '</th><th>' . _("Grip") . '</th>';

        $listed_user_ids = array();
        foreach ($best_laps as $lap) {
            if (in_array($lap->user()->id(), $listed_user_ids)) continue;

            $lap_number = $lap->id() - $this->Session->firstDrivenLap()->id() + 1;

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



        // --------------------------------------------------------------------
        //                                 All Laps
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("All Laps") . "</h1>";

        $html .= '<table>';
        $html .= '<tr><th>' . _("Lap") . '</th><th>' . _("Laptime") . '</th><th>' . _("Cuts") . '</th><th>' . _("Driver") . '</th><th>' . _("Car") . '</th><th>' . _("Ballast") . '</th><th>' . _("Restrictor") . '</th><th>' . _("Grip") . '</th><th>' . _("Lap Id") . '</th>';
        foreach ($laps as $lap) {
            $class = "class=\"";
            $class .= ($lap->cuts() > 0) ? " lap_invalid" : "";
            $class .= "\"";

            $lap_number = $lap->id() - $this->Session->firstDrivenLap()->id() + 1;

            $html .= "<tr $class>";
            $html .= "<td>" . $lap_number . "</td>";
            $html .= "<td>" . HumanValue::format($lap->laptime(), "LAPTIME") . "</td>";
            $html .= "<td>" . $lap->cuts() . "</td>";
            $html .= "<td>" . $lap->user()->login() . "</td>";
            $html .= "<td>" . $lap->carSkin()->car()->name() . "</td>";
            $html .= "<td>" . HumanValue::format($lap->ballast(), "kg") . "</td>";
            $html .= "<td>" . HumanValue::format($lap->restrictor(), "%") . "</td>";
            $html .= "<td>" . HumanValue::format(100 * $lap->grip(), "%") . "</td>";
            $html .= "<td>" . $lap->id() . "</td>";
            $html .= '</tr>';
        }
        $html .= '</table>';




        return $html;
    }


    /**
     * Calculate an array with position information of race laps.
     * The first array elements represents the start position
     * The second last element is the positions of the last race lap
     * The last element contains the race results
     * Each element contains a list of user IDs in order of their position.
     */
    private function calculateLapPositions() {
        $lap_positions = array();

        // only race sessions
        if ($this->Session === NULL || $this->Session->type() != 3) return $lap_positions;

        // get qualifying position
        $qual = $this->Session->predecessor();
        if ($qual->type() == 2) {
            $lap_positions[0] = array();
            $results = $qual->results();
            usort($results, "SessionResult::comparePosition");
            foreach ($results as $rslt) {
                $lap_positions[0][] = $rslt->user()->id();
            }
        }

        // race lap positions
        $driver_laps_amount = array(); // stores the amount of driven laps for a certain user
        foreach (array_reverse($this->Session->drivenLaps()) as $lap) {
            $user_id = $lap->user()->id();

            // find current lap number of user
            if (!array_key_exists($user_id, $driver_laps_amount))
                $driver_laps_amount[$user_id] = 0;
            $driver_laps_amount[$user_id] += 1;
            $user_lap_nr = $driver_laps_amount[$user_id];

            // grow lap position information
            while ($user_lap_nr >= count($lap_positions))
                $lap_positions[] = array();

            // put user into lap position array
            $lap_positions[$user_lap_nr][] = $user_id;
        }

        // add race results
        $final_positions = array();
        $results = $this->Session->results();
        usort($results, "SessionResult::comparePosition");
        foreach ($results as $rslt) {
            $final_positions[] = $rslt->user()->id();
        }
        $lap_positions[] = $final_positions;

        return $lap_positions;
    }


    //! @return Html string with svg diagram
    private function diagramRacePosition() {
        $html = "";

        $positions = $this->calculateLapPositions();
        $drivers = $this->Session->drivers();

        // determine driver name langths
        $max_user_login_length = 0;
        foreach ($drivers as $u) {
            $len = strlen($u->login());
            if ($len > $max_user_login_length)
                $max_user_login_length = $len;
        }

        // svg configuration data
        $lap_dx = 40; // units per lap
        $pos_dy = 20; // units per position

        // svg tag
        $svg_viewbox_x0 = -10;
        $svg_viewbox_dx = (0 + count($positions)) * $lap_dx + 6 * $max_user_login_length;
        $svg_viewbox_y0 = 0;
        $svg_viewbox_dy = (2 + count($drivers)) * $pos_dy + 10;
        $html .= "<svg id=\"race_position_chart\" class=\"chart\" viewBox=\"$svg_viewbox_x0 $svg_viewbox_y0 $svg_viewbox_dx $svg_viewbox_dy\">";

        // box border check
//         $html .= "<rect x=\"$svg_viewbox_x0\" width=\"$svg_viewbox_dx\" y=\"$svg_viewbox_y0\" height=\"$svg_viewbox_dy\" fill=\"none\" stroke=\"red\"/>";

        // axes
        $html .= "<g id=\"axes\">";
        $axe_y = (1 + count($drivers)) * $pos_dy;
        $x = (count($positions) - 2) * $lap_dx;
        $html .= "<polyline id=\"axis_x\" points=\"0,$axe_y $x,$axe_y\"/>";
        for ($lap_nr = 0; $lap_nr < (count($positions)-1); ++$lap_nr) {
            $x = $lap_nr * $lap_dx;
            $y0 = $axe_y - 5;
            $y1 = $axe_y + 5;
            $html .= "<polyline points=\"$x,$y0 $x,$y1\"/>";
            $y2 = $y1 + 3;
            if (($lap_nr % 5) == 0) {
                $html .= "<text x=\"$x\" y=\"$y2\" dy=\"1.0em\" dx=\"-0.3em\">$lap_nr</text>";
            }
        }
        $html .= "</g>";

        // plots
        $html .= "<g id=\"plots\">";
        foreach ($drivers as $u) {
            $user_id = $u->id();
            $user_login = $u->login();
            $user_color = $u->color();
            $polyline_points = "";

            for ($lap = 0; $lap < (count($positions)-1); ++$lap) {

                if (in_array($user_id, $positions[$lap])) {
                    $x = $lap_dx * $lap;
                    $y = 1 + array_search($user_id, $positions[$lap]);
                    $y *= $pos_dy;
                    $polyline_points .= "$x,$y ";
                }
            }

            $html .= "<g id=\"plot_user_$user_id\">";
            $html .= "<polyline points=\"$polyline_points\" stroke=\"$user_color\" fill=\"None\"/>";
            $x = (count($positions) - 2) * $lap_dx + 5;
            $y = 1 + array_search($user_id, $positions[count($positions) - 1]);
            $y *= $pos_dy;
            $html .= "<text x=\"$x\" y=\"$y\" dy=\"0.5em\" stroke=\"none\" fill=\"$user_color\">$user_login</text>";
            $html .= "</g>";
        }
        $html .= "</g>";

        $html .= "</svg>";

        return $html;
    }

}

?>
