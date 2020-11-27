<?php

class sa_statistics extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Statistics");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_ServerContent", "View_Statistics"];
    }

    public function getHtml() {
        global $acswuiConfig;

        // load statistics
        $file_path = $acswuiConfig->AcsContent . "/stats_general.json";
        $stats = json_decode(file_get_contents($file_path), TRUE);

        // initialize the html output
        $html  = "";

        $html .= "<table>";

        # driven laps
        $laps_valid = $stats['DrivenLapsValid'];
        $laps_invalid = $stats['DrivenLapsInvalid'];
        $laps_valid_perc = 100 * $laps_valid / ($laps_valid + $laps_invalid);
        $html .= "<tr><th>Driven Laps</th><td>";
        $html .= HumanValue::format($laps_valid + $laps_invalid, "L");
        $html .= "</td><td>";
        $html .= "(valid: " . HumanValue::format($laps_valid_perc, "%") . ")";
        $html .= "</td></tr>";

        # driven length
        $meters_valid = $stats['DrivenMetersValid'];
        $meters_invalid = $stats['DrivenMetersInValid'];
        $meters_valid_perc = 100 * $meters_valid / ($meters_valid + $meters_invalid);
        $html .= "<tr><th>Driven Length</th><td>";
        $html .= HumanValue::format($meters_valid + $meters_invalid, "m");
        $html .= "</td><td>";
        $html .= "(valid: " . HumanValue::format($meters_valid_perc, "%") . ")";
        $html .= "</td></tr>";

        # driven time
        $seconds_valid = $stats['DrivenSecondsValid'];
        $seconds_invalid = $stats['DrivenSecondsInValid'];
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
