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

        $html = "";

        $html .= '<table>';
        $html .= '<tr><th>Popularity</th><th>Car Class</th><th colspan="3">Driven</th></tr>';
        foreach (StatsCarClassPopularity::listLatest() as $sccp) {
            $html .= '<tr>';
            $html .= '<td>' . HumanValue::format($sccp->popularity(), "%") . '</td>';

            $link_url = "?CONTENT=/sa_statistics//records_car&CARCLASS_ID=" . $sccp->carClass()->id();
            $link_name = $sccp->carClass()->name();
            $html .= "<td><a href=\"$link_url\">$link_name</a></td>";

            $html .= '<td>' . HumanValue::format($sccp->lapCount(), "L") . '</td>';
            $html .= '<td>' . HumanValue::format($sccp->timeCount(), "s") . '</td>';
            $html .= '<td>' . HumanValue::format($sccp->meterCount(), "m") . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }
}

?>
