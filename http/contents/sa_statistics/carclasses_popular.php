<?php

class carclasses_popular extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Popular Car Classes");
        $this->PageTitle  = "Popular Car-Classes";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_Statistics"];

    }

    public function getHtml() {
        // access global data
        global $acswuiConfig;
        global $acswuiLog;
        global $acswuiDatabase;
        global $acswuiUser;




        $html = "";

        // list of car classes
        $carclasses = CarClass::listClasses();
        function compare_popularity($cc1, $cc2) {
            return ($cc1->popularity() > $cc2->popularity()) ? -1 : 1;
        }
        uasort($carclasses, 'compare_popularity');

        $html .= '<table>';
        $html .= '<tr><th>Popularity</th><th>Car Class</th><th>Drivers</th><th colspan="3">Driven</th></tr>';
        foreach ($carclasses as $cc) {
            if ($cc->drivenLaps() == 0) continue;
            $html .= '<tr>';
            $html .= '<td>' . HumanValue::format($cc->popularity() * 100, "%") . '</td>';
            $html .= '<td>' . $cc->name() . '</td>';
            $html .= '<td>' . count($cc->drivers()) . '</td>';
            $html .= '<td>' . HumanValue::format($cc->drivenLaps(), "L") . '</td>';
            $html .= '<td>' . HumanValue::format($cc->drivenSeconds(), "s") . '</td>';
            $html .= '<td>' . HumanValue::format($cc->drivenMeters(), "m") . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }
}

?>
