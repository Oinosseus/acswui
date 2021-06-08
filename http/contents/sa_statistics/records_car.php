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
                $html .= '<tr>';
                $html .= '<th>' . _("Position") . '</th>';
                $html .= '<th>' . _("Laptime") . '</th>';
                $html .= '<th>' . _("Delta") . '</th>';
                $html .= '<th>' . _("Driver") . '</th>';
                $html .= '<th colspan="2">' . _("Car") . '</th>';
                $html .= '<th>' . _("Ballast") . '</th>';
                $html .= '<th>' . _("Restrictor") . '</th>';
                $html .= '<th>' . _("Grip") . '</th>';
                $html .= '<th>' . _("Date") . '</th>';
                $html .= '<th>' . _("Session / Lap") . '</th>';
                $html .= '</tr>';

                $best_laptime = NULL;
                $position = 1;
                $last_entry_skipped = FALSE;
                for ($records_count = 0; $records_count < count($records); ++$records_count) {

                    $lap_previous = ($records_count > 0) ? $records[$records_count - 1] : NULL;
                    $lap = $records[$records_count];
                    $lap_next = ($records_count < (count($records)-1)) ? $records[$records_count + 1] : NULL;

                    if ($best_laptime === NULL) $best_laptime = $lap->laptime();

                    $html_row = '<tr>';
                    $html_row .= "<td>$position</td>";
                    $html_row .= '<td>' . HumanValue::format($lap->laptime(), "LAPTIME") . '</td>';
                    $html_row .= '<td>' . HumanValue::format($lap->laptime() - $best_laptime, "ms") . '</td>';
                    $html_row .= '<td>' . $lap->user()->displayName() . '</td>';
                    $html_row .= '<td>' . $lap->carSkin()->htmlImg("", 50) . '</td>';
                    $html_row .= '<td>' . $lap->carSkin()->car()->name() . '</td>';
                    $html_row .= '<td>' . HumanValue::format($lap->ballast(), "kg") . '</td>';
                    $html_row .= '<td>' . HumanValue::format($lap->restrictor(), "%") . '</td>';
                    $html_row .= '<td>' . HumanValue::format($lap->grip() * 100, "%") . '</td>';
                    $html_row .= '<td>' . $lap->timestamp()->format("c") . '</td>';
                    $link_url = "?CONTENT=/zm_session//history&SESSION_ID=" . $lap->session()->id();
                    $link_name = $lap->session()->id() . " / " . $lap->id();
                    $html_row .= "<td><a href=\"$link_url\">$link_name</a></td>";
                    $html_row .= '</tr>';

                    if ($lap->user()->privacyFulfilled()) {
                        $html .= $html_row;
                        $last_entry_skipped = FALSE;
                    } else if ($lap_previous !== NULL && $lap_previous->user()->privacyFulfilled()) {
                        $html .= $html_row;
                        $last_entry_skipped = FALSE;
                    } else if ($lap_next !== NULL && $lap_next->user()->privacyFulfilled()) {
                        $html .= $html_row;
                        $last_entry_skipped = FALSE;
                    } else if ($records_count == 0) {
                        $html .= $html_row;
                        $last_entry_skipped = FALSE;
                    } else if ($records_count == (count($records)-1)) {
                        $html .= $html_row;
                        $last_entry_skipped = FALSE;
                    } else if (!$last_entry_skipped) {
                        $html .= "<tr><td>...</td></tr>";;
                        $last_entry_skipped = TRUE;
                    } else {
                        $last_entry_skipped = TRUE;
                    }

                    $position += 1;
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
