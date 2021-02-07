<?php

class records_track extends cContentPage {

    private $CurrentTrack = NULL;
    private $JsonPath = NULL;


    public function __construct() {
        global $acswuiConfig;

        $this->MenuName   = _("Track Records");
        $this->PageTitle  = "Best Track Lap Times";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_Statistics"];
        $this->JsonPath = $acswuiConfig->AcServerPath. "/http_cache/stats_track_records.json";
    }


    public function getHtml() {
        global $acswuiConfig;

        if (isset($_REQUEST['TRACK_ID'])) {
            $this->CurrentTrack = new Track((int) $_REQUEST['TRACK_ID']);
            $_SESSION['TRACK_ID'] = $this->CurrentTrack->id();
        } else if (isset($_SESSION['TRACK_ID'])) {
            $this->CurrentTrack = new Track((int) $_SESSION['TRACK_ID']);
        }

        $html = "";

        # track select
        $html .= "Track";
        $html .= '<form action="" method="post">';
        $html .= '<select name="TRACK_ID" onchange="this.form.submit()">';
        foreach ($this->listRecordTracks() as $t) {
            if ($this->CurrentTrack === NULL) $this->CurrentTrack = $t;
            $selected = ($this->CurrentTrack->id() == $t->id()) ? "selected" : "";
            $html .= '<option value="' . $t->id() . '"' . $selected . ">" . $t->name() . "</option>";
        }
        $html .= '</select>';
        $html .= '</form>';

        if ($this->CurrentTrack !== NULL) {

            foreach (CarClass::listClasses() as $carclass) {
                $records = $this->listClassRecords($this->CurrentTrack, $carclass);
                if (count($records) == 0) continue;

                $html .= "<h1>" . $carclass->name() . "</h1>";
                $html .= '<table>';
                $html .= '<tr><th>' . _("Laptime") . '</th><th>' . _("Delta") . '</th><th>' . _("Driver") . '</th><th colspan="2">' . _("Car") . '</th><th>' . _("Ballast") . '</th><th>' . _("Restrictor") . '</th><th>' . _("Grip") . '</th><th>' . _("Date") . '</th><th>' . _("Session / Lap") . '</th>';

                $best_laptime = NULL;
                foreach ($records as $lap) {
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
                    $link_url = "?CONTENT=/zm_session//history&SESSION_ID=" . $lap->session()->id();
                    $link_name = $lap->session()->id() . " / " . $lap->id();
                    $html .= "<td><a href=\"$link_url\">$link_name</a></td>";
                    $html .= '</tr>';
                }

                $html .= '</table>';
            }
        }


        return $html;
    }


    //! @return a list of Lap objects for the requested records
    private function listClassRecords($track, $carclass) {
        $ret = array();

        $records = json_decode(file_get_contents($this->JsonPath), TRUE);
        $track_records = $records['Data'][$track->id()];

        // scan carclass records for each user
        $user_records = array();
        foreach ($track_records as $uid=>$user_data) {
            foreach ($user_data as $cid=>$lid) {
                $lap = new Lap($lid);
                $car = new Car($cid);

                // check for valid car
                if (!$carclass->validCar($car, $lap->ballast(), $lap->restrictor())) continue;

                if (!array_key_exists($uid, $user_records)) {
                    $user_records[$uid] = $lap;
                } else if ($lap->laptime() < $user_records[$uid]->laptime()) {
                    $user_records[$uid] = $lap;
                }
            }
        }

        // format data
        foreach ($user_records as $uid=>$lap) {
            $ret[] = $lap;
        }
        usort($ret, "Lap::compareLaptime");

        return $ret;
    }


    //! @return A List of Track objects for which records are available
    private function listRecordTracks() {
        $ret = array();

        $records = json_decode(file_get_contents($this->JsonPath), TRUE);
        $track_records = $records['Data'];
        foreach ($track_records as $tid=>$data) {
                $ret[] = new Track($tid);
        }

        return $ret;
    }
}

?>
