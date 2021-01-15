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

        $html = "";



        // --------------------------------------------------------------------
        //                             Ranking Table
        // --------------------------------------------------------------------

        $driver_rank_list = DriverRanking::calculateRanks();

        // table head
        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<th>" . _("Driver") . "</th>";
        $html .= "<th>XP</th>";
        $html .= "<th>SX</th>";
        $html .= "<th>SF</th>";
        $html .= "<th>" . _("Score") . "</th>";
        $html .= "</tr>";

        $mean_score_xp = array();
        $mean_score_sx = array();
        $mean_score_sf = array();
        foreach ($driver_rank_list as $drv_rnk) {

            $mean_score_xp[] = $drv_rnk->getScore("XP");
            $mean_score_sx[] = $drv_rnk->getScore("SX");
            $mean_score_sf[] = $drv_rnk->getScore("SF");

            $html .= "<tr>";
            $html .= "<td>" . $drv_rnk->user()->login() . "</td>";
            $html .= "<td>" . $this->getScoreHtml($drv_rnk, "XP") . "</td>";
            $html .= "<td>" . $this->getScoreHtml($drv_rnk, "SX", "RT") . "</td>";
            $html .= "<td>" . $this->getScoreHtml($drv_rnk, "SF", "CUT") . "</td>";
            $html .= "<td>" . $this->getScoreHtml($drv_rnk) . "</td>";
            $html .= "</tr>";
        }
        if (count($driver_rank_list) > 0) {
            $mean_score_xp = array_sum($mean_score_xp) / count($mean_score_xp);
            $mean_score_sx = array_sum($mean_score_sx) / count($mean_score_sx);
            $mean_score_sf = array_sum($mean_score_sf) / count($mean_score_sf);
        } else {
            $mean_score_xp = 0;
            $mean_score_sx = 0;
            $mean_score_sf = 0;
        }

        $html .= "<tr>";
        $html .= "<td><small><i>" . _("Mean Value") . "</i></small></td>";
        $html .= "<td><small><i>" . sprintf("%0.0f", $mean_score_xp) . "</i></small></td>";
        $html .= "<td><small><i>" . sprintf("%0.0f", $mean_score_sx) . "</i></small></td>";
        $html .= "<td><small><i>" . sprintf("%0.0f", $mean_score_sf) . "</i></small></td>";
        $html .= "</tr>";

        $html .= "</table>";


        // --------------------------------------------------------------------
        //                             Description
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Description") . "</h1>";

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
        $html .= "<td>" . _("Success points for overall track time per car class (leading ahead another driver). This is independent of the lease time.") . "<br><small>Success Best Time</small></td>";
        $html .= "</tr>";


        // SF
        $html .= "<tr>";
        $html .= "<td rowspan=\"5\">SF</td>";
        $html .= "<td>CT</td>";
        $html .= "<td>" . sprintf("%+0.2f/Cut/1Mm", $acswuiConfig->DriverRanking['SF']['CT']) . "</td>";
        $html .= "<td>" . _("Safety deduction points for cuts.") . "<br><small>Safety Cuts</small></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<td>CE</td>";
        $html .= "<td>" . sprintf("%+0.2f/Collision/1Mm", $acswuiConfig->DriverRanking['SF']['CE']) . "</td>";
        $html .= "<td>" . _("Safety deduction points for crashing with the environment") . "<br><small>Safety Collision Environment High</small></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<td>CC</td>";
        $html .= "<td>" . sprintf("%+0.2f/Collision/1Mm", $acswuiConfig->DriverRanking['SF']['CC']) . "</td>";
        $html .= "<td>" . _("Safety deduction points for crashing with another car") . "<br><small>Collision Car High</small></td>";
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
}

?>
