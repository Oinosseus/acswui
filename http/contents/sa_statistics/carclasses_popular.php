<?php

class carclasses_popular extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Popular Car Classes");
        $this->PageTitle  = "Popular Car-Classes";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_Statistics"];

    }

    public function getHtml() {
        global $acswuiConfig;

        // get statistics
        $file_path = $acswuiConfig->AcsContent . "/stats_carclass_popularity.json";
        $stats = json_decode(file_get_contents($file_path), TRUE);



        $html = "";

        $html .= '<table>';
        $html .= '<tr><th>Popularity</th><th>Car Class</th><th>Drivers</th><th colspan="3">Driven</th></tr>';
        foreach ($stats as $s) {
            $html .= '<tr>';
            $html .= '<td>' . HumanValue::format($s['Popularity'] * 100, "%") . '</td>';
            $html .= '<td>' . $s['Name'] . '</td>';
            $html .= '<td>' . count($s['DriversList']) . '</td>';
            $html .= '<td>' . HumanValue::format($s['DrivenLaps'], "L") . '</td>';
            $html .= '<td>' . HumanValue::format($s['DrivenSeconds'], "s") . '</td>';
            $html .= '<td>' . HumanValue::format($s['DrivenMeters'], "m") . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }
}

?>
