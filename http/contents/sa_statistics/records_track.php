<?php

class records_track extends cContentPage {

    private $CurrentTrack = NULL;


    public function __construct() {
        $this->MenuName   = _("Track Records");
        $this->PageTitle  = "Best Track Lap Times";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_Statistics"];

    }


    public function getHtml() {
        global $acswuiConfig;

        if (isset($_POST['TRACK_ID'])) {
            $this->CurrentTrack = new Track((int) $_POST['TRACK_ID']);
            $_SESSION['TRACK_ID'] = $this->CurrentTrack->id();
        } else if (isset($_SESSION['TRACK_ID'])) {
            $this->CurrentTrack = new Track((int) $_SESSION['TRACK_ID']);
        }

        // load statistics
        $file_path = $acswuiConfig->AcsContent . "/stats_track_records.json";
        $track_records = json_decode(file_get_contents($file_path), TRUE);

        $html = "";

        # track select
        $html .= "Track";
        $html .= '<form action="" method="post">';
        $html .= '<select name="TRACK_ID" onchange="this.form.submit()">';
        foreach (Track::listTracks() as $t) {
            if (!array_key_exists($t->id(), $track_records)) continue;
            if ($this->CurrentTrack === NULL) $this->CurrentTrack = $t;
            $selected = ($this->CurrentTrack->id() == $t->id()) ? "selected" : "";
            $html .= '<option value="' . $t->id() . '"' . $selected . ">" . $t->name() . "</option>";
        }
        $html .= '</select>';
        $html .= '</form>';

        if ($this->CurrentTrack !== NULL) {

            foreach ($track_records[$this->CurrentTrack->id()] as $carclass_id => $lap_ids) {

                $carclass = new CarClass($carclass_id);
                $html .= "<h1>" . $carclass->name() . "</h1>";

                $html .= '<table>';
                $html .= '<tr><th>' . _("Laptime") . '</th><th>' . _("Delta") . '</th><th>' . _("Driver") . '</th><th colspan="2">' . _("Car") . '</th><th>' . _("Ballast") . '</th><th>' . _("Restrictor") . '</th><th>' . _("Grip") . '</th><th>' . _("Date") . '</th><th>' . _("Lap Id") . '</th>';

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
                    $html .= '<td>' . $lap->id() . '</td>';
                    $html .= '</tr>';
                }

                $html .= '</table>';
            }
        }


        return $html;
    }
}

?>
