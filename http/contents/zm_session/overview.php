<?php

class overview extends cContentPage {

    private $Session = NULL;


    public function __construct() {
        $this->MenuName   = _("Overview");
        $this->PageTitle  = "Session Overview";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["Session", "Session_Overview"];
    }


    public function getHtml() {
        global $acswuiDatabase;

        // get latest Session
        $res = $acswuiDatabase->fetch_2d_array("Sessions", ['Id'], [], "Id", FALSE);
        $this->Session = new Session($res[0]['Id']);


        // initialize the html output
        $html  = "";



        // --------------------------------------------------------------------
        //                          Get Session Data
        // --------------------------------------------------------------------

        // get laps, ordered by laptime
        $laps = array();
        $laps = $this->Session->drivenLaps();
        function compare_laptime($l1, $l2) {
            return ($l1->laptime() < $l2->laptime()) ? -1 : 1;
        }
        usort($laps, "compare_laptime");

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

        // driver summary
        $html .= '<table>';

        $html .= '<tr>';
        $html .= '<th>' . _("Driver") . '</th>';
        $html .= '<th colspan="3">' . _("Driven") . '</th>';
        $html .= '</tr>';

        foreach ($this->Session->drivers() as $user) {

            $lap_count = 0;
            foreach ($laps as $lap) {
                if ($lap->user()->id() == $user->id()) ++$lap_count;
            }

            $html .= '<tr>';
            $html .= '<td>' . $user->login() . '</th>';
            $html .= '<td>' . HumanValue::format($lap_count, "laps") . '</td>';
            $html .= '<td>' . HumanValue::format($lap_count * $this->Session->track()->length(), "m") . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';



        // --------------------------------------------------------------------
        //                               Leaderboard
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Leaderboard") . "</h1>";


        $html .= '<table>';
        $html .= '<tr><th>' . _("Lap") . '</th><th>' . _("Laptime") . '</th><th>' . _("Delta") . '</th><th>' . _("Cuts") . '</th><th>' . _("Driver") . '</th><th>' . _("Car") . '</th><th>' . _("Ballast") . '</th><th>' . _("Restrictor") . '</th><th>' . _("Grip") . '</th>';

        $listed_user_ids = array();
        foreach ($laps as $lap) {
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


        return $html;
    }

}

?>
