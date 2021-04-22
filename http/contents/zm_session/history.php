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

    public function getHtml() {

        // access global data
        global $acswuiConfig;
        global $acswuiLog;
        global $acswuiDatabase;
        global $acswuiUser;



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

            if (isset($_REQUEST['SESSION_FILTER_TRCK']))
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

        // sanity check
        if ($this->Session !== NULL && $this->Session->id() === NULL) $this->Session = NULL;



        // initialize the html output
        $html  = "";
        $html .= "<script>";
        $html .= "let SvgUserIds = new Array;\n";
        foreach (User::listDrivers() as $d) {
            $uid = $d->id();
            $html .= "SvgUserIds.push($uid);\n";
        }
        $html .= "</script>";

        $html .= '<script src="' . $this->getRelPath() . 'history.js"></script>';


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
        if ($this->Session !== NULL)
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

        if ($this->Session !== NULL) {
            $html .= "<h1>" . _("Session Info") . "</h1>";

            // predecessor/successor chain
            $html .= "<div id=\"session_predecessor_chain\">";
            $html .= $this->predecessorChain($this->Session);
            $html .= $this->Session->name();
            $html .= $this->successorChain($this->Session);
            $html .= "</div>";

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
        }



        // --------------------------------------------------------------------
        //                               Session Results
        // --------------------------------------------------------------------

        if ($this->Session !== NULL) {
            $html .= "<h1>" . _("Session Results") . "</h1>";


            // race position diagram
            $html .= $this->positionDiagram();


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
                $html .= '<td>' . $rslt->user()->displayName() . '</th>';
                $html .= '<td>' . $rslt->carSkin()->htmlImg("", 50) . '</th>';
                $html .= '<td>' . HumanValue::format($rslt->bestlap(), "LAPTIME") . '</td>';
                $html .= '<td>' . HumanValue::format($rslt->totaltime(), "LAPTIME") . '</td>';
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
        }


        // --------------------------------------------------------------------
        //                               Fastest Laps
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Fastest Laps") . "</h1>";

        # diagram
        $html .= $this->laptimeDistributionDiagram();

        $html .= '<table>';
        $html .= '<tr><th>' . _("Lap") . '</th><th>' . _("Laptime") . '</th><th>' . _("Delta") . '</th><th>' . _("Cuts") . '</th><th>' . _("Driver") . '</th><th>' . _("Car") . '</th><th>' . _("Ballast") . '</th><th>' . _("Restrictor") . '</th><th>' . _("Grip") . '</th><th>' . _("Car") . '</th><th>' . _("Show") . '</th>';

        $listed_user_ids = array();
        foreach ($best_laps as $lap) {
            $uid = $lap->user()->id();
            if (in_array($uid, $listed_user_ids)) continue;

            $lap_number = $lap->id() - $this->Session->firstDrivenLap()->id() + 1;

            $class = "class=\"";
            $class .= ($lap->cuts() > 0) ? " lap_invalid" : "";
            $class .= "\"";

            $html .= "<tr $class id=\"table_row_user_$uid\">";
            $html .= "<td>$lap_number</td>";
            $html .= "<td>" . HumanValue::format($lap->laptime(), "LAPTIME") . "</td>";
            $html .= "<td>" . HumanValue::format($lap->laptime() - $laptime_best, "ms") . "</td>";
            $html .= "<td>" . $lap->cuts() . "</td>";
            $html .= "<td>" . $lap->user()->displayName() . "</td>";
            $html .= "<td>" . $lap->carSkin()->car()->name() . "</td>";
            $html .= "<td>" . HumanValue::format($lap->ballast(), "kg") . "</td>";
            $html .= "<td>" . HumanValue::format($lap->restrictor(), "%") . "</td>";
            $html .= "<td>" . HumanValue::format(100 * $lap->grip(), "%") . "</td>";
            $html .= "<td>" . $lap->carSkin()->htmlImg("", 100) . "</td>";

            $checked = ($uid == $acswuiUser->Id) ? "checked=\"true\"": "";
            $html .= "<td><input type=\"checkbox\" id=\"show_$uid\" $checked/></td>";
            $html .= '</tr>';

            $listed_user_ids[] = $uid;
        }
        $html .= '</table>';



        // --------------------------------------------------------------------
        //                               Collisions
        // --------------------------------------------------------------------

        if ($this->Session !== NULL) {
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
                if ($cll->otherUser() != NULL) $other = $cll->otherUser()->displayName();

                $class = "class=\"";
                $class .= ($cll->secondary()) ? " secondary_collision" : "";
                $class .= "\"";

                $html .= "<tr $class>";
                $html .= "<td>" . $cll->user()->displayName() . "</td>";
                $html .= "<td>$other</td>";
                $html .= "<td>" . sprintf("%0.0f", $cll->speed()) . "</td>";
                $html .= "<td>" . $cll->timestamp()->format("Y-m-d H:i:s") . "</td>";
                $html .= "<td>" . $cll->id() . "</td>";
                $html .= "</tr>";
            }

            $html .= "</table>";
        }


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
            $html .= "<td>" . $lap->user()->displayName() . "</td>";
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


    //! @return Html string with svg diagram
    private function positionDiagram() {
        $html = "";

        // sanity check
        if ($this->Session === NULL || count($this->Session->drivers()) == 0)
            return $html;

        // determine length of session
        $drivers = $this->Session->drivers();
        $session_length = count($this->Session->dynamicPositions($drivers[0]));
//         echo "session_length = $session_length<br>";

        // determine driver name langths
        $max_user_login_length = 0;
        foreach ($drivers as $u) {
            $len = strlen($u->displayName());
            if ($len > $max_user_login_length)
                $max_user_login_length = $len;
        }

        // svg configuration data
        $lap_dx = (int) (1000 / $session_length); // units per lap
        $pos_dy = 20; // units per position

        //
        $axis_x_text_distance = 100;
        if ($lap_dx >= 50) $axis_x_text_distance = 1;
        else if ($lap_dx >= 25) $axis_x_text_distance = 2;
        else if ($lap_dx >= 10) $axis_x_text_distance = 5;
        else if ($lap_dx >= 5) $axis_x_text_distance = 10;

        // svg tag
        $svg_viewbox_x0 = -10;
        $svg_viewbox_dx = (0 + $session_length) * $lap_dx + 6 * $max_user_login_length;
        $svg_viewbox_y0 = 0;
        $svg_viewbox_dy = (2.5 + count($drivers)) * $pos_dy + 10;
        $html .= "<svg id=\"race_position_chart\" class=\"chart\" viewBox=\"$svg_viewbox_x0 $svg_viewbox_y0 $svg_viewbox_dx $svg_viewbox_dy\">";

        // box border check
//         $html .= "<rect x=\"$svg_viewbox_x0\" width=\"$svg_viewbox_dx\" y=\"$svg_viewbox_y0\" height=\"$svg_viewbox_dy\" fill=\"none\" stroke=\"red\"/>";

        // axes
        $html .= "<g id=\"axes\">";
        $axe_y = (1.5 + count($drivers)) * $pos_dy;
        $x = ($session_length - 2) * $lap_dx;
        $html .= "<polyline id=\"axis_x\" points=\"0,$axe_y $x,$axe_y\"/>";
        for ($lap_nr = 0; $lap_nr < ($session_length-1); ++$lap_nr) {
            $x = $lap_nr * $lap_dx;

            if (($lap_nr % $axis_x_text_distance) == 0) {
                $y0 = $axe_y - 8;
                $y1 = $axe_y + 8;
                $y2 = $y1 + 2;
                $html .= "<text x=\"$x\" y=\"$y2\" dy=\"1.0em\" dx=\"-0.3em\">$lap_nr</text>";
            } else {
                $y0 = $axe_y - 4;
                $y1 = $axe_y + 4;
                $y2 = $y1 + 4;
            }

            $html .= "<polyline points=\"$x,$y0 $x,$y1\"/>";

        }
        $label = ($this->Session->type() == 3) ? _("Laps") : _("Minutes");
        $x = ($session_length - 2) * $lap_dx + 5;
        $y = $axe_y;
        $html .= "<text x=\"$x\" y=\"$y\" dy=\"0.5em\">$label</text>";
        $html .= "</g>";

        // plots
        $html .= "<g id=\"plots\">";
        foreach ($drivers as $u) {
            $user_id = $u->id();
            $user_login = $u->displayName();
            $user_color = $u->color();
            $polyline_points = "";

            $positions = $this->Session->dynamicPositions($u);
            for ($lap = 0; $lap < (count($positions)-1); ++$lap) {

                if ($positions[$lap] != 0) {
                    $x = $lap_dx * $lap;
                    $y = $positions[$lap];
                    $y *= $pos_dy;
                    $polyline_points .= "$x,$y ";
                }
            }

            $html .= "<g id=\"plot_user_$user_id\">";
            $html .= "<polyline points=\"$polyline_points\" stroke=\"$user_color\" fill=\"None\"/>";
            $x = (count($positions) - 2) * $lap_dx + 5;
            $y = $positions[count($positions) - 1];
            $y *= $pos_dy;
            $html .= "<text x=\"$x\" y=\"$y\" dy=\"0.5em\" stroke=\"none\" fill=\"$user_color\">$user_login</text>";
            $html .= "</g>";
        }
        $html .= "</g>";

        $html .= "</svg>";

        return $html;
    }


    private function laptimeDistributionDiagram() {
        global $acswuiUser;
        $html = "";

        // sanity check
        if ($this->Session === NULL || $this->Session->drivers() == 0)
            return $html;

        // configuration
        $zoom_x = 300;
        $resolutions = [    1,    2,    3,    4,    5,    6,    7,    8,    9,
                           10,   20,   30,   40,   50,   60,   70,   80,   90,
                          100,  200,  300]; // in 100ms steps

        // initialize data
        $driver_densities = array();
        $driver_lap_counts = array();
        foreach ($this->Session->drivers() as $u) {
            $driver_densities[$u->id()] = array();
            $driver_lap_counts[$u->id()] = 0;
        }

        // find best laptime and lap count of session
        $best_laptime = NULL;
        foreach ($this->Session->drivenLaps() as $lap) {
            if ($lap->cuts() != 0) continue;
            $driver_lap_counts[$lap->user()->id()] += 1;
            if ($best_laptime === NULL || $lap->laptime() < $best_laptime)
                $best_laptime = $lap->laptime();
        }

        // gather data
        if ($best_laptime !== NULL) {
            foreach ($this->Session->drivenLaps() as $lap) {
                $uid = $lap->user()->id();
                $delta = $lap->laptime() - $best_laptime;
                $delta /= 100;

                // find resolutuion group
                $res_grp = NULL;
                foreach ($resolutions as $res) {
                    if ($delta < $res) {
                        $res_grp = $res;
                        break;
                    }
                }

                // add plot data
                if ($res_grp !== NULL) {
                    $current_densities = $driver_densities[$uid];
                    if (!array_key_exists($res_grp, $current_densities))
                        $current_densities[$res_grp] = 0;
                    $current_densities[$res_grp] += 1;
                    $driver_densities[$uid] = $current_densities;
                }
            }
        }

        // normalize data
        $normalized_densities = array();
        foreach ($driver_densities as $uid=>$densities) {
            $normdens = array();
            foreach ($densities as $res=>$count) {
                if ($driver_lap_counts[$uid] == 0)
                    $d = 0;
                else
                    $d = (int) (-100 * $count / $driver_lap_counts[$uid]);
                $normdens[$res] = $d;
            }
            $normalized_densities[$uid] = $normdens;
        }

        // svg tag
        $svg_viewbox_x0 = -6;
        $svg_viewbox_dx = ceil(log10($resolutions[count($resolutions) - 1]) * $zoom_x + 12);
        $svg_viewbox_y0 = -106 - 20;
        $svg_viewbox_dy = 100 + 12 + 20 + 20;
        $html .= "<svg id=\"lap_distribution_chart\" class=\"chart\" viewBox=\"$svg_viewbox_x0 $svg_viewbox_y0 $svg_viewbox_dx $svg_viewbox_dy\">";
        $html .= "<defs>";
        $html .= "<marker id=\"axis_arrow\" markerWidth=\"6\" markerHeight=\"4\" refx=\"0\" refy=\"2\" orient=\"auto\">";
        $html .= "<polyline points=\"0,0 6,2 0,4\"/>";
        $html .= "</marker>";
        $html .= "</defs>";

        // box border check
//         $html .= "<rect x=\"$svg_viewbox_x0\" width=\"$svg_viewbox_dx\" y=\"$svg_viewbox_y0\" height=\"$svg_viewbox_dy\" fill=\"none\" stroke=\"red\"/>";

        // grid
        $html .= "<g id=\"grid\">";
        foreach ($resolutions as $r) {
            $x = (int) (log10($r) * $zoom_x);
            $html .= "<polyline points=\"$x,0 $x,-100\"/>";
        }
        $html .= "</g>";

        // axes
        $html .= "<g id=\"axes\">";
        $x = 1 * $zoom_x;
        $html .= "<text x=\"$x\" y=\"-110\" dy=\"0.0em\" dx=\"-0.5em\" stroke=\"none\">" . _("Laptime Distribution") . "</text>";
        $x = ceil(log10($resolutions[count($resolutions) - 1]) * $zoom_x);
        $html .= "<polyline id=\"axis_x\" points=\"-5,0 $x,0\" style=\"marker-end:url(#axis_arrow)\"/>";
        $html .= "<polyline id=\"axis_y\" points=\"0,5 0,-100\" style=\"marker-end:url(#axis_arrow)\"/>";
        $x = (int) (log10(1) * $zoom_x);
        $html .= "<polyline points=\"$x,5 $x,-100\"/>";
        $html .= "<text x=\"$x\" y=\"5\" dy=\"1.0em\" dx=\"-0.0em\" stroke=\"none\">100ms</text>";
        $x = (int) (log10(10) * $zoom_x);
        $html .= "<polyline points=\"$x,5 $x,-100\"/>";
        $html .= "<text x=\"$x\" y=\"5\" dy=\"1.0em\" dx=\"-0.5em\" stroke=\"none\">1s</text>";
        $x = (int) (log10(100) * $zoom_x);
        $html .= "<polyline points=\"$x,5 $x,-100\"/>";
        $html .= "<text x=\"$x\" y=\"5\" dy=\"1.0em\" dx=\"-1.0em\" stroke=\"none\">10s</text>";
        $html .= "</g>";

        // plots
        $html .= "<g id=\"plots\">";
        foreach ($this->Session->drivers() as $u) {
            $uid = $u->id();
            $ul = $u->displayName();
            $uc = $u->color();
            $polyline_points = "";

            foreach ($resolutions as $r) {
                if (array_key_exists($r, $normalized_densities[$uid])) {
                    $x = (int) (log10($r) * $zoom_x);
                    $y = $normalized_densities[$uid][$r];
                    $polyline_points .= "$x,$y ";
                }
            }
            $visibility = ($uid == $acswuiUser->Id) ? "visible" : "hidden";
            $html .= "<polyline id=\"plot_distribution_user_$uid\" points=\"$polyline_points\" stroke=\"$uc\" style=\"visibility:$visibility;\"/>";
        }
        $html .= "</g>";

        $html .= "</svg>";

        return $html;
    }


    private function predecessorChain(Session $s) {
        $html = "";

        if ($s->predecessor() !== NULL) {

            if ($s->predecessor()->predecessor() !== NULL)
                $html .= $this->predecessorChain($s->predecessor());

            $html .= "<a href=\"?SESSION_SELECT&SESSION_ID=" . $s->predecessor()->id() . "\">";
            $html .= $s->predecessor()->name();
            $html .= "</a> -&gt; ";
        }

        return $html;
    }


    private function successorChain(Session $s) {
        $html = "";

        if ($s->successor() !== NULL) {

            $html .= " -&gt; <a href=\"?SESSION_SELECT&SESSION_ID=" . $s->successor()->id() . "\">";
            $html .= $s->successor()->name();
            $html .= "</a>";

            if ($s->successor()->successor() !== NULL)
                $html .= $this->successorChain($s->successor());
        }

        return $html;
    }

}

?>
