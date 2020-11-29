<?php

class records_car extends cContentPage {

    private $CurrentCarClass = NULL;

    public function __construct() {
        $this->MenuName   = _("Car Records");
        $this->PageTitle  = "Best Car Lap Times";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_Statistics"];
    }


    public function getHtml() {
        global $acswuiConfig;

        if (isset($_POST['CARCLASS_ID'])) {
            $this->CurrentCarClass = new CarClass((int) $_POST['CARCLASS_ID']);
            $_SESSION['CARCLASS_ID'] = $this->CurrentCarClass->id();
        } else if (isset($_SESSION['CARCLASS_ID'])) {
            $this->CurrentCarClass = new CarClass((int) $_SESSION['CARCLASS_ID']);
        }

        // load statistics
        $file_path = $acswuiConfig->AcsContent . "/stats_carclass_records.json";
        $class_records = json_decode(file_get_contents($file_path), TRUE);

        $html = "";

        # class select
        $html .= "Car Class";
        $html .= '<form action="" method="post">';
        $html .= '<select name="CARCLASS_ID" onchange="this.form.submit()">';
        foreach (CarClass::listClasses() as $cc) {
            if (!array_key_exists($cc->id(), $class_records)) continue;
            if ($this->CurrentCarClass === NULL) $this->CurrentCarClass = $cc;
            $selected = ($this->CurrentCarClass->id() == $cc->id()) ? "selected" : "";
            $html .= '<option value="' . $cc->id() . '"' . $selected . ">" . $cc->name() . "</option>";
        }
        $html .= '</select>';
        $html .= '</form>';

        if ($this->CurrentCarClass !== NULL) {

            foreach ($class_records[$this->CurrentCarClass->id()] as $track_id => $lap_ids) {

                $track = new Track($track_id);
                $html .= "<h1>" . $track->name() . "</h1>";

                $html .= '<table>';
                $html .= '<tr><th>' . _("Laptime") . '</th><th>' . _("Delta") . '</th><th>' . _("Driver") . '</th><th colspan="2">' . _("Car") . '</th><th>' . _("Ballast") . '</th><th>' . _("Restrictor") . '</th><th>' . _("Grip") . '</th><th>' . _("Date") . '</th><th>' . _("Session / Lap") . '</th>';

                $best_laptime = NULL;
                foreach ($lap_ids as $lap_id) {
                    $lap = new Lap($lap_id);
                    if ($best_laptime === NULL) $best_laptime = $lap->laptime();

                    $html .= '<tr>';
                    $html .= '<td>' . HumanValue::format($lap->laptime(), "LAPTIME") . '</td>';
                    $html .= '<td>' . HumanValue::format($lap->laptime() - $best_laptime, "ms") . '</td>';
                    $html .= '<td>' . $lap->user()->login() . '</td>';
                    $html .= '<td>' . $lap->carSkin()->htmlImg("", 50) . '</td>';
                    $html .= '<td>' . $lap->carSkin()->car()->name() . '</td>';
                    $html .= '<td>' . HumanValue::format($lap->ballast(), "kg") . '</td>';
                    $html .= '<td>' . HumanValue::format($lap->restrictor(), "%") . '</td>';
                    $html .= '<td>' . HumanValue::format($lap->grip() * 100, "%") . '</td>';
                    $html .= '<td>' . $lap->timestamp()->format("c") . '</td>';

                    $link_url = "?CONTENT=/zm_session//laps&SESSION_ID=" . $lap->session()->id();
                    $link_name = $lap->session()->id() . " / " . $lap->id();
                    $html .= "<td><a href=\"$link_url\">$link_name</a></td>";

                    $html .= '</tr>';
                }

                $html .= '</table>';
            }
        }


        return $html;
    }
}

?>
