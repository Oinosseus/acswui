<?php


class driver_ranking extends cContentPage {

    private $CurrentCarClass = NULL;

    public function __construct() {
        $this->MenuName   = _("Driver Ranking");
        $this->PageTitle  = "Driver Leaderboard";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_DriverRanking"];
    }


    public function getHtml() {
        global $acswuiConfig;
        global $acswuiDatabase;
        global $acswuiLog;
        global $acswuiUser;

        $html = "";

        $html .= "<script>";
        $html .= "let SvgUserIds = new Array;\n";
        foreach (DriverRanking::listLatest() as $drv_rnk) {
            if ($drv_rnk->user()->privacyFulfilled() !== TRUE) continue;
            $user_id = $drv_rnk->user()->id();
            $html .= "SvgUserIds.push($user_id);\n";
        }
        $html .= "</script>";

        $html .= '<script src="' . $this->getRelPath() . 'driver_ranking.js"></script>';



        // --------------------------------------------------------------------
        //                             Ranking Diagram
        // --------------------------------------------------------------------

        // scale option
        $svg_weeks = 4;
        if (array_key_exists("ChartWeeks", $_REQUEST)) {
            $svg_weeks = $_REQUEST['ChartWeeks'];
        }
        $html .= "<form method=\"get\">";
        $html .= _("Weeks") . ": ";
        $html .= "<select name=\"ChartWeeks\" onchange=\"this.form.submit()\">";
        foreach ([4, 12, 25, 52, 104] as $weeks) {
            $selected = ($weeks == $svg_weeks)  ? "selected=\"yes\"" : "";
            $html .= "<option value=\"$weeks\"  $selected>$weeks</option>";
        }
        $html .= "</select>";
        $html .= "</form>";

        // find max score
        $max_score = 100;
        foreach (DriverRanking::listLatest() as $drv_rnk) {
            $score = $drv_rnk->getScore();
            if ($score > $max_score) $max_score = $score;
            break;
        }

        // svg configuration data
        $svg_xax_min = 400;
        $svg_yax_min = -20;
        $svg_yax_max = 50 * ceil(($max_score / 50));
        $svg_ygrid = 25;
        $svg_img_zoom = 2;
        $svg_x_zoom = $svg_xax_min / ($svg_weeks * 7);

        $svg_viewbox_x0 = -1 * $svg_xax_min - 7;
        $svg_viewbox_dx = $svg_xax_min + 50;
        $svg_viewbox_y0 = -1 * $svg_yax_max - 10;
        $svg_viewbox_dy = $svg_yax_max - $svg_yax_min + 10;
        $svg_width = $svg_viewbox_dx * $svg_img_zoom;
        $svg_height = $svg_viewbox_dy * $svg_img_zoom;
        $html .= "<svg id=\"driver_ranking_chart\" class=\"chart\" width=\"$svg_width\" height=\"$svg_height\" viewBox=\"$svg_viewbox_x0 $svg_viewbox_y0 $svg_viewbox_dx $svg_viewbox_dy\">";

        $html .= "<defs>";
        $html .= "<marker id=\"axis_arrow\" markerWidth=\"6\" markerHeight=\"4\" refx=\"0\" refy=\"2\" orient=\"auto\">";
        $html .= "<polyline points=\"0,0 6,2 0,4\"/>";
        $html .= "</marker>";
        $html .= "</defs>";

        // box border check
//         $html .= "<rect x=\"$svg_viewbox_x0\" width=\"$svg_viewbox_dx\" y=\"$svg_viewbox_y0\" height=\"$svg_viewbox_dy\" fill=\"none\" stroke=\"red\"/>";

        // grid
        $html .= "<g id=\"grid\">";
        for ($y = 0; $y <= $svg_yax_max; $y += $svg_ygrid) {
            $ny = -1 * $y;
            $html .= "<polyline points=\"5,$ny -$svg_xax_min,$ny\"/>";
        }
        for ($y = -$svg_ygrid; $y >= $svg_yax_min; $y -= $svg_ygrid) {
            $ny = -1 * $y;
            $html .= "<polyline points=\"5,$ny -$svg_xax_min,$ny\"/>";
        }
        $html .= "</g>";

        // axes
        $x = -1 * $svg_xax_min;
        $y0 = -1 * $svg_yax_min;
        $y1 = -1 * $svg_yax_max;
        $html .= "<g id=\"axes\">";
        $html .= "<polyline id=\"axis_x\" points=\"5,0 $x,0\" style=\"marker-end:url(#axis_arrow)\"/>";
        $html .= "<polyline id=\"axis_y\" points=\"0,$y0 0,$y1\" style=\"marker-end:url(#axis_arrow)\"/>";
        for ($y = 0; $y <= $svg_yax_max; $y += 2 * $svg_ygrid) {
            $ny = -1 * $y;
            $html .= "<text x=\"8\" y=\"$ny\" dy=\"0.35em\" stroke=\"none\">$y</text>";
        }
        for ($y = -2 * $svg_ygrid; $y >= $svg_yax_min; $y -= 2 * $svg_ygrid) {
            $ny = -1 * $y;
            $html .= "<text x=\"8\" y=\"$ny\" dy=\"0.35em\" stroke=\"none\">$y</text>";
        }
        $html .= "<text x=\"-$svg_xax_min\" y=\"0\" dy=\"1.0em\" stroke=\"none\">" . _("Weeks") . "</text>";
        for ($d=1; $d <= ($svg_xax_min / $svg_x_zoom); ++$d) {
            $len = (($d % 70) == 0) ? 5 : 3;
            if (($d % 7) == 0) {
                $x = sprintf("%d", $d * $svg_x_zoom);
                $html .= "<polyline points=\"-$x,0 -$x,$len\"/>";
            }
        }
        $html .= "</g>";

        // plots
        $now = new DateTime();
        $then = (new DateTime())->sub(new DateInterval(sprintf("P%dD", $svg_xax_min / $svg_x_zoom)));
        $timestamp = $then->format("Y-m-d");
        $html .= "<g id=\"plots\">";
        foreach (array_reverse(DriverRanking::listLatest()) as $drv_rnk) {
            $polyline_points = "";
            $user_id = $drv_rnk->user()->id();

            // ensure privacy
            if ($drv_rnk->user()->privacyFulfilled() !== TRUE) continue;

            // append data from db
            $query = "SELECT Id FROM DriverRanking WHERE User = '$user_id' AND Timestamp >= '$timestamp' ORDER BY Id DESC";
            foreach ($acswuiDatabase->fetch_raw_select($query) as $row) {
                $dr = new DriverRanking($row['Id']);
                $interval = $now->diff($dr->Timestamp());
                $x = sprintf("%d", -1 * $interval->days * $svg_x_zoom);
                $y = sprintf("%d", -1 * $dr->getScore());
                $polyline_points .= "$x,$y ";
            }

            // append latest data
            $interval = $now->diff($drv_rnk->Timestamp());
            $x = sprintf("%d", -1 * $interval->days * $svg_x_zoom);
            $y = sprintf("%d", -1 * $drv_rnk->getScore());
            $polyline_points = "$x,$y " . $polyline_points;

            // plot line
            $user_color = $drv_rnk->user()->color();
            $visibility = ($user_id == $acswuiUser->Id) ? "visible" : "hidden";
            $html .= "<polyline id=\"plot_user_$user_id\" points=\"$polyline_points\" stroke=\"$user_color\" style=\"visibility:$visibility;\"/>";

        }
        $html .= "</g>";

        $html .= "</svg>";


        // --------------------------------------------------------------------
        //                             Ranking Table
        // --------------------------------------------------------------------

        $driver_rank_list = DriverRanking::listLatest();

        // table head
        $html .= "<table id=\"driver_ranking_table\">";
        $html .= "<tr>";
        $html .= "<th>" . _("Position") . "</th>";
        $html .= "<th>" . _("Driver") . "</th>";
        $html .= "<th>XP</th>";
        $html .= "<th>SX</th>";
        $html .= "<th>SF</th>";
        $html .= "<th>" . _("Score") . "</th>";
        $html .= "<th>" . _("Show") . "</th>";
        $html .= "</tr>";

        $mean = DriverRanking::initCharacteristics();
        $position = 0;
        $last_skipped_by_privacy = FALSE;
        $mean_count = 0;
        $mean_shown_66 = FALSE;
        $mean_shown_33 = FALSE;
        $mean_shown_0 = FALSE;
        foreach ($driver_rank_list as $drv_rnk) {
            $user_id = $drv_rnk->user()->id();
            $position += 1;

            foreach (array_keys($mean) as $group) {
                foreach (array_keys($mean[$group]) as $value) {
                    $mean[$group][$value] += $drv_rnk->getScore($group, $value);
                }
            }

            // mean values
            ++$mean_count;
            if (!$mean_shown_66 && $drv_rnk->getScore() < (0.66 * $driver_rank_list[0]->getScore())) {
                $html .= $this->meanValueRow($driver_rank_list, $mean, $mean_count);
                $mean_shown_66 = TRUE;
            }
            if (!$mean_shown_33 && $drv_rnk->getScore() < (0.33 * $driver_rank_list[0]->getScore())) {
                $html .= $this->meanValueRow($driver_rank_list, $mean, $mean_count);
                $mean_shown_33 = TRUE;
            }
            if (!$mean_shown_0 && $drv_rnk->getScore() < 0) {
                $html .= $this->meanValueRow($driver_rank_list, $mean, $mean_count);
                $mean_shown_0 = TRUE;
            }

            // ensure privacy
            if ($drv_rnk->user()->privacyFulfilled()) {
                $html .= "<tr id=\"table_row_user_$user_id\">";
                $html .= "<td>$position</td>";
                $html .= "<td>" . $drv_rnk->user()->displayName() . "</td>";
                $html .= "<td>" . $this->getScoreHtml($drv_rnk, "XP") . "</td>";
                $html .= "<td>" . $this->getScoreHtml($drv_rnk, "SX", "RT") . "</td>";
                $html .= "<td>" . $this->getScoreHtml($drv_rnk, "SF", "CUT") . "</td>";
                $html .= "<td>" . $this->getScoreHtml($drv_rnk) . "</td>";

                $checked = ($user_id == $acswuiUser->Id) ? "checked=\"true\"": "";
                $html .= "<td><input type=\"checkbox\" id=\"show_$user_id\" $checked/></td>";
                $html .= "</tr>";

                $last_skipped_by_privacy = FALSE;

            } else if ($last_skipped_by_privacy == FALSE) {
                $html .= "<tr><td class=\"HiddenCell\">...</td></tr>";
                $last_skipped_by_privacy = TRUE;
            }
        }

        // final mean value
        $html .= $this->meanValueRow($driver_rank_list, $mean, count($driver_rank_list));

        $html .= "</table>";

        if (count($driver_rank_list) > 0) {
            $d = $driver_rank_list[0]->timestamp()->format("Y-m-d H:i");
            $html .= "<small>$d</small>";
        }


        // --------------------------------------------------------------------
        //                             Description
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Description") . "</h1>";

        $html .= _("Driver ranking points are only cummulated for server runs with race sessions.
                    That means for succeeding Practice->Qualifying->Race, Qualifying->Race or Race-only sessions.
                    Pure practice or qualifying sessions without a race session are ignored.");
        $html .= "<br>";
        $html .= _("Of couse SX-BT is not effected from this");

        $html .= "<table>";

        $html .= "<tr>";
        $html .= "<th colspan=\"2\">" . _("Symbol") . "</th>";
        $html .= "<th>" . _("Score") . "</th>";
        $html .= "<th>" . _("Description") . "</th>";
        $html .= "</tr>";

        // XP
        $html .= "<tr>";
        $html .= "<td rowspan=\"3\">XP</td>";
        $html .= "<td>R</td>";
        $html .= "<td>" . sprintf("%+0.2f/1Mm", $acswuiConfig->DriverRanking['XP']['R']) . "</td>";
        $html .= "<td>" . _("Experience from driven race length") . "<br><small>Experience Race</small></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<td>Q</td>";
        $html .= "<td>" . sprintf("%+0.2f/1Mm", $acswuiConfig->DriverRanking['XP']['Q']) . "</td>";
        $html .= "<td>" . _("Experience from driven qualifying length") . "<br><small>Experience Qualifying</small></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<td>P</td>";
        $html .= "<td>" . sprintf("%+0.2f/1Mm", $acswuiConfig->DriverRanking['XP']['P']) . "</td>";
        $html .= "<td>" . _("Experience from driven practice length") . "<br><small>Experience Practice</small></td>";
        $html .= "</tr>";


        // SX
        $html .= "<tr>";
        $html .= "<td rowspan=\"4\">SX</td>";
        $html .= "<td>R</td>";
        $html .= "<td>" . sprintf("%+0.2f/Position", $acswuiConfig->DriverRanking['SX']['R']) . "</td>";
        $html .= "<td>" . _("Success points for race positions (leading ahead another driver).") . "<br><small>Success Race</small></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<td>Q</td>";
        $html .= "<td>" . sprintf("%+0.2f/Position", $acswuiConfig->DriverRanking['SX']['Q']) . "</td>";
        $html .= "<td>" . _("Success points for qualifying positions (leading ahead another driver).") . "<br><small>Success Qualifying</small></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<td>RT</td>";
        $html .= "<td>" . sprintf("%+0.2f/Race", $acswuiConfig->DriverRanking['SX']['RT']) . "</td>";
        $html .= "<td>" . _("Success points for best time in a race session.") . "<br><small>Success Race Time</small></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<td>BT</td>";
        $html .= "<td>" . sprintf("%+0.2f/Position", $acswuiConfig->DriverRanking['SX']['BT']) . "</td>";
        $html .= "<td>" . _("Success points for overall best time per track per car class (leading ahead another driver).") . "<br><small>Success Best Time</small></td>";
        $html .= "</tr>";


        // SF
        $html .= "<tr>";
        $html .= "<td rowspan=\"3\">SF</td>";
        $html .= "<td>CT</td>";
        $html .= "<td>" . sprintf("%+0.2f/Cut/1Mm", $acswuiConfig->DriverRanking['SF']['CT']) . "</td>";
        $html .= "<td>" . _("Safety deduction points for cuts.") . "<br><small>Safety Cuts</small></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<td>CE</td>";
        $html .= "<td>" . sprintf("%+0.2f/Collision/1Mm", $acswuiConfig->DriverRanking['SF']['CE']) . "</td>";
        $html .= "<td>" . _("Safety deduction points for crashing with the environment") . "<br><small>Safety Collision Environment</small></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<td>CC</td>";
        $html .= "<td>" . sprintf("%+0.2f/Collision/1Mm", $acswuiConfig->DriverRanking['SF']['CC']) . "</td>";
        $html .= "<td>" . _("Safety deduction points for crashing with another car") . "<br><small>Collision Car</small></td>";
        $html .= "</tr>";


        // DEF
        $html .= "<tr>";
        $html .= "<td rowspan=\"1\">DEF</td>";
        $html .= "<td>DAYS</td>";
        $html .= "<td>" . $acswuiConfig->DriverRanking['DEF']['DAYS'] . "</td>";
        $html .= "<td>" . _("Amount of days in the past where sessions are integrated in latest ranking calculation.") . "<br><small>Definition Days</small></td>";
        $html .= "</tr>";


        $html .= "<table>";



        return $html;
    }


    public function getScoreHtml($driver_rank, $group=NULL) {
        if ($group == NULL) {
            $html = sprintf("<span title=\"%0.1f\">", $driver_rank->getScore());
            $html .= sprintf("%0.0f</span>", $driver_rank->getScore());

        } else if ($group == "XP") {
            $val_r = $driver_rank->getScore($group, "R");
            $val_q = $driver_rank->getScore($group, "Q");
            $val_p = $driver_rank->getScore($group, "P");
            $html = sprintf("<span title=\"R = %0.1f\nQ = %0.1f\nP = %0.1f\">", $val_r, $val_q, $val_p);
            $html .= sprintf("%0.0f</span>", $val_r + $val_q + $val_p);

        } else if ($group == "SX") {
            $val_r = $driver_rank->getScore($group, "R");
            $val_q = $driver_rank->getScore($group, "Q");
            $val_rt = $driver_rank->getScore($group, "RT");
            $val_bt = $driver_rank->getScore($group, "BT");
            $html = sprintf("<span title=\"R = %0.1f\nQ = %0.1f\nRT = %0.1f\nBT = %0.1f\">", $val_r, $val_q, $val_rt, $val_bt);
            $html .= sprintf("%0.0f</span>", $val_r + $val_q + $val_rt + $val_bt);

        } else if ($group == "SF") {
            $val_ct = $driver_rank->getScore($group, "CT");
            $val_ce = $driver_rank->getScore($group, "CE");
            $val_cc = $driver_rank->getScore($group, "CC");
            $html = sprintf("<span title=\"CT = %0.1f\nCE = %0.1f\nCC = %0.1f\">", $val_ct, $val_ce, $val_cc);
            $html .= sprintf("%0.0f</span>", $val_ct + $val_ce + $val_cc);

        }

        return $html;
    }


    private function meanValueRow($driver_rank_list, $mean, $mean_count) {
        $html = "";

        // mean value row
        if (count($driver_rank_list) > 0) {
            foreach (array_keys($mean) as $group) {
                foreach (array_keys($mean[$group]) as $value) {
                    $mean[$group][$value] /= $mean_count;
                }
            }
        }
        $html .= "<tr><td class=\"HiddenCell\"></td>";
        $html .= "<td><small><i>" . _("Mean Value") . "</i></small></td>";
        foreach (['XP', 'SX', 'SF'] as $group) {
            $title = "";
            $mean_value = 0;
            foreach (array_keys($mean[$group]) as $value) {
                $title .= "$value=" . sprintf("%0.1f", $mean[$group][$value]) . "\n";
                $mean_value += $mean[$group][$value];
            }
            $html .= "<td><span title=\"$title\"><small><i>" . sprintf("%0.0f", $mean_value) . "</i></small></span></td>";
        }
        $html .= "</tr>";

        return $html;
    }
}

?>
