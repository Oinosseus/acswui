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

        if (isset($_REQUEST['CARCLASS_ID'])) {
            $this->CurrentCarClass = new CarClass((int) $_REQUEST['CARCLASS_ID']);
            $_SESSION['CARCLASS_ID'] = $this->CurrentCarClass->id();
        } else if (isset($_SESSION['CARCLASS_ID'])) {
            $this->CurrentCarClass = new CarClass((int) $_SESSION['CARCLASS_ID']);
        }

        $html = "";

        # class select
        $html .= "Car Class";
        $html .= '<form action="" method="post">';
        $html .= '<select name="CARCLASS_ID" onchange="this.form.submit()">';
        foreach (CarClass::listClasses() as $cc) {
            if ($this->CurrentCarClass === NULL) $this->CurrentCarClass = $cc;
            $selected = ($this->CurrentCarClass->id() == $cc->id()) ? "selected" : "";
            $html .= '<option value="' . $cc->id() . '"' . $selected . ">" . $cc->name() . "</option>";
        }
        $html .= '</select>';
        $html .= '</form>';

        if ($this->CurrentCarClass !== NULL) {

            foreach (Track::listTracks() as $track) {
                $records = $this->listRecordLaps($this->CurrentCarClass, $track);
                if (count($records) == 0) continue;

                $html .= "<h1>" . $track->name() . "</h1>";
                $html .= '<table>';
                $html .= '<tr><th>' . _("Laptime") . '</th><th>' . _("Delta") . '</th><th>' . _("Driver") . '</th><th colspan="2">' . _("Car") . '</th><th>' . _("Ballast") . '</th><th>' . _("Restrictor") . '</th><th>' . _("Grip") . '</th><th>' . _("Date") . '</th><th>' . _("Session / Lap") . '</th>';

                $best_laptime = NULL;
                foreach($records as $lap) {
                    if ($best_laptime === NULL) $best_laptime = $lap->laptime();
                    $html .= '<tr>';
                    $html .= '<td>' . HumanValue::format($lap->laptime(), "LAPTIME") . '</td>';
                    $html .= '<td>' . HumanValue::format($lap->laptime() - $best_laptime, "ms") . '</td>';
                    $html .= '<td>' . $lap->user()->displayName() . '</td>';
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
    private function listRecordLaps($carclass, $track) {
        global $acswuiConfig;
        $ret = array();

        $records_path = $acswuiConfig->AbsPathData. "/htcache/stats_track_records.json";
        $records = json_decode(file_get_contents($records_path), TRUE);
        if (!array_key_exists($track->id(), $records['Data'])) return $ret;
        $track_records = $records['Data'][$track->id()];

        // scan carclass records for each user
        $user_records = array();
        foreach ($track_records as $uid=>$user_data) {
            foreach ($user_data as $cid=>$lid) {
                $lap = new Lap($lid);

                // check for valid car
                if (!$carclass->validLap($lap)) continue;

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


}

?>
