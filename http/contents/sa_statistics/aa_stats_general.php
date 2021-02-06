<?php

class aa_stats_general extends cContentPage {
    public function __construct() {
        $this->MenuName   = _("General");
        $this->PageTitle  = _("General Statistics");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_Statistics"];
    }


    public function getHtml() {
        // initialize the html output
        $html  = "";

        $stats = StatsGeneral::latest();

        $html .= "<table>";

        # driven laps
        $laps_valid = $stats->lapsValid();
        $laps_invalid = $stats->lapsInvalid();
        $laps_valid_perc = 100 * $laps_valid / ($laps_valid + $laps_invalid);
        $html .= "<tr><th>Driven Laps</th><td>";
        $html .= HumanValue::format($laps_valid + $laps_invalid, "L");
        $html .= "</td><td>";
        $html .= "(valid: " . HumanValue::format($laps_valid_perc, "%") . ")";
        $html .= "</td></tr>";

        # driven length
        $meters_valid = $stats->metersValid();
        $meters_invalid = $stats->metersInvalid();
        $meters_valid_perc = 100 * $meters_valid / ($meters_valid + $meters_invalid);
        $html .= "<tr><th>Driven Length</th><td>";
        $html .= HumanValue::format($meters_valid + $meters_invalid, "m");
        $html .= "</td><td>";
        $html .= "(valid: " . HumanValue::format($meters_valid_perc, "%") . ")";
        $html .= "</td></tr>";

        # driven time
        $seconds_valid = $stats->secondsValid();
        $seconds_invalid = $stats->secondsInvalid();
        $seconds_valid_perc = 100 * $seconds_valid / ($seconds_valid + $seconds_invalid);
        $html .= "<tr><th>Driven Time</th><td>";
        $html .= HumanValue::format($seconds_valid + $seconds_invalid, "s");
        $html .= "</td><td>";
        $html .= "(valid: " . HumanValue::format($seconds_valid_perc, "%") . ")";
        $html .= "</td></tr>";

        $html .= "</table>";

        return $html;
    }
}

?>
