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
            $svg_path = $acswuiConfig->AcsContent . "/session_standing_diagrams/session_" . $this->Session->id() . ".svg";
            if (file_exists($svg_path)) {
                $html .= "<img src=\"$svg_path\" class=\"session_lap_diagram\">";
            }
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

}

?>
