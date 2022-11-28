<?php

namespace Content\Html;

class DriverRanking extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Driver Ranking"),  _("Driver Ranking"));
        $this->requirePermission("User_DriverRanking_View");
        $this->addScript("driver_ranking.js");
    }

    public function getHtml() {
        $current_user = \Core\UserManager::currentUser();
        $html = "";

        // determine maximum points
        $max_ranking_points = 0;
        foreach (\DbEntry\DriverRanking::listLatest() as $rnk) {
            if ($rnk->points() > $max_ranking_points) $max_ranking_points = $rnk->points();
        }

        // laptime diagram
        $html .= "<div id=\"DriverRankingDiagram\">";
        $title = _("Driver Ranking Diagram");
        $axis_y_title = _("Days");
        $axis_x_title = _("Ranking Points");
        $current_user_id = ($current_user !== NULL) ? $current_user->id() : 0;
        $max = round($max_ranking_points);
        $html .= "<canvas axYTitle=\"$axis_y_title\" axXTitle=\"$axis_x_title\" title=\"$title\" currentUser=\"$current_user_id\" maxRankingPoints=\"$max\"></canvas>";
        $html .= "</div>";

        // show ranking per group
        for ($rnk_grp = 1; $rnk_grp <= \Core\Config::DriverRankingGroups; ++$rnk_grp) {

            if (\Core\Config::DriverRankingGroups > 1) {
                $html .= "<h1>" . \Core\ACswui::getPAram("DriverRankingGroup$rnk_grp". "Name") . "</h1>";
            }

            $html .= "<table>";
            $html .= "<tr>";
            $html .= "<th>" . _("Pos.") . "</th>";
            $html .= "<th colspan=\"2\">" . _("Driver") . "</th>";
            $html .= "<th><span title=\"" . _("Experience") . "\">XP</span></th>";
            $html .= "<th><span title=\"" . _("Success") . "\">SX</span></th>";
            $html .= "<th><span title=\"" . _("Safety") . "\">SF</span></th>";
            $html .= "<th><span title=\"" . _("Sum") . "\">&#x2211;</span></th>";
            $html .= "</tr>";

            $pos = 0;
            $mean_values = array();
            $mean_values['XP'] = array('P'=>0,  'Q'=>0,  'R'=>0);
            $mean_values['SX'] = array('RT'=>0, 'Q'=>0,  'R'=>0, 'BT'=>0);
            $mean_values['SF'] = array('CT'=>0, 'CE'=>0, 'CC'=>0);
            $latest_ranks = \DbEntry\DriverRanking::listLatest($rnk_grp);
            foreach ($latest_ranks as $rnk) {
                $pos += 1;

                $html .= "<tr>";

                // position
                $html .= "<td>$pos</td>";

                // driver
                $html .= "<td class=\"DriverFlagCell\">" . $rnk->user()->parameterCollection()->child("UserCountry")->valueLabel() . "</td>";
                $html .= "<td>" . $rnk->user()->html() . "</td>";

                // XP
                $title = sprintf("P = %0.1f\nQ = %0.1f\nR = %0.1f",
                                 $rnk->points("XP", "P"),
                                 $rnk->points("XP", "Q"),
                                 $rnk->points("XP", "R"));
                $html .= "<td><span title=\"$title\">" . round($rnk->points("XP")) . "</span></td>";

                // SX
                $title = sprintf("Q = %0.1f\nR = %0.1f\nRT = %0.1f\nBT = %0.1f",
                                 $rnk->points("SX", "Q"),
                                 $rnk->points("SX", "R"),
                                 $rnk->points("SX", "RT"),
                                 $rnk->points("SX", "BT"));
                $html .= "<td><span title=\"$title\">" . round($rnk->points("SX")) . "</span></td>";

                // SF
                $title = sprintf("CT = %0.1f\nCE = %0.1f\nCC = %0.1f",
                                 $rnk->points("SF", "CT"),
                                 $rnk->points("SF", "CE"),
                                 $rnk->points("SF", "CC"));
                $html .= "<td><span title=\"$title\">" . round($rnk->points("SF")) . "</span></td>";

                // sum
                $html .= "<td>";
                $title = sprintf("%0.1f", $rnk->points());
                $html .= "<span title=\"$title\">" . round($rnk->points()) . "</span>";
                $points_increase = $rnk->points() - $rnk->user()->rankingPoints();
                if (abs($points_increase) >= 0.1) {
                    $css_class = ($points_increase > 0) ? "TrendRising" : "TrendFalling";
                    $html .= " <small class=\"$css_class\">(" . sprintf("%+0.1f", $points_increase) . ")</small>";
                }
                if ($rnk->groupNext() < $rnk->user()->rankingGroup()) {
                    $html .= " <span title=\"" . _("Driver will rise to next group") . "\" class=\"TrendRising\">&#x2b06;</span>";
                } else if ($rnk->groupNext() > $rnk->user()->rankingGroup()) {
                    $html .= " <span title=\"" . _("Driver will fall to previous group") . "\" class=\"TrendFalling\">&#x2b07;</span>";
                }
                $html .= "</td>";

                if ($current_user->id() != $rnk->user()->id()) {
                    $html .= "<td>";
                    $html .= "<button type=\"button\" onclick=\"loadDriverRankingData({$rnk->user()->id()}, this)\" title=\"" . _("Load Diagram Data") . "\">";
                    $html .= "&#x1f4c8;</button> ";
                    $html .= "</td>";
                }


                $html .= "</tr>";

                // summ for mean values
                foreach (["P", "Q", "R"] as $key)
                    $mean_values['XP'][$key] += $rnk->points("XP", $key);
                foreach (["RT", "Q", "R", "BT"] as $key)
                    $mean_values['SX'][$key] += $rnk->points("SX", $key);
                foreach (["CT", "CE", "CC"] as $key)
                    $mean_values['SF'][$key] += $rnk->points("SF", $key);
            }

            // show mean values
            if ($pos > 0) {
                // calculate mean and sum
                foreach (array_keys($mean_values) as $grp) {
                    $sum = 0;
                    foreach (array_keys($mean_values[$grp]) as $key) {
                        $mean_values[$grp][$key] /= $pos;
                        $sum += $mean_values[$grp][$key];
                    }
                    $mean_values[$grp]["Sum"] = $sum;
                }
                $mean_values["Sum"] = $mean_values["XP"]["Sum"] + $mean_values["SX"]["Sum"] + $mean_values["SF"]["Sum"];

                $html .= "<tr>";
                $html .= "<td colspan=\"3\"><small>" . _("Mean Value") . "</small></td>";

                $title = sprintf("P = %0.1f\nQ = %0.1f\nR = %0.1f",
                                    $mean_values["XP"]["P"],
                                    $mean_values["XP"]["Q"],
                                    $mean_values["XP"]["R"]);
                $html .= "<td><small><span title=\"$title\">" . round($mean_values["XP"]["Sum"]) . "</span></small></td>";

                $title = sprintf("RT = %0.1f\nQ = %0.1f\nR = %0.1f\nBT = %0.1f",
                                    $mean_values["SX"]["RT"],
                                    $mean_values["SX"]["Q"],
                                    $mean_values["SX"]["R"],
                                    $mean_values["SX"]["BT"]);
                $html .= "<td><small><span title=\"$title\">" . round($mean_values["SX"]["Sum"]) . "</span></small></td>";

                $title = sprintf("CT = %0.1f\nCE = %0.1f\nCC = %0.1f",
                                    $mean_values["SF"]["CT"],
                                    $mean_values["SF"]["CE"],
                                    $mean_values["SF"]["CC"]);
                $html .= "<td><small><span title=\"$title\">" . round($mean_values["SF"]["Sum"]) . "</span></small></td>";

                $title = sprintf("%0.1f", $mean_values["Sum"]);
                $html .= "<td><small><span title=\"$title\">" . round($mean_values["Sum"]) . "</span></small></td>";
                $html .= "</tr>";
            }

            $html .= "</table>";

            // inform about required points
            $min_points = \DbEntry\DriverRanking::groupThreshold($rnk_grp);
            if ($min_points !== NULL) {
                $html .= "<small>";
                $html .= _("Minimum points required for this Group") . ": ";
                $html .= "<span title=\"" . round($min_points, 1) . "\">" . round($min_points) . "</span>";
                $html .= "</small><br>";
            }
        }

        return $html;
    }
}
