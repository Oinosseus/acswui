<?php

namespace Content\Json;

/**
 */
class TrackRecords extends \Core\JsonContent {


    public function __construct() {
        $this->requirePermission("Json");
    }


    public function getDataArray() {
        $cu = \Core\UserManager::currentUser();

        // get track
        if (!array_key_exists("TrackId", $_GET)) {
            \Core\Log::warning("No TrackId given!");
            return [];
        }
        $track = \DbEntry\Track::fromId($_GET["TrackId"]);
        if ($track === NULL) {
            \Core\Log::warning("Invalid TrackId '" . $_GET["TrackId"] . "'!");
            return [];
        }

        $data = array();

        foreach (\DbEntry\CarClass::listClasses() as $cc) {
            $html = "";

            $best_laps = $track->bestLaps($cc);
            if (count($best_laps) > 0) {
                $html .= "<h2>" . $cc->name() . "</h2>";
                $html .= "<table>";
                $html .= "<tr>";
                $html .= "<th>" . _("Position") . "</th>";
                $html .= "<th>" . _("Laptime") . "</th>";
                $html .= "<th>" . _("Delta") . "</th>";
                $html .= "<th>" . _("Driver") . "</th>";
                $html .= "<th colspan=\"2\">" . _("Car") . "</th>";
                $html .= "<th>" . _("BOP") . "</th>";
                $html .= "<th>" . _("Grip") . "</th>";
                $html .= "<th>" . _("Date") . "</th>";
                $html .= "<th>" . _("Session") . "</th>";
                $html .= "<th>" . _("Lap") . "</th>";
                $html .= "</tr>";

                $skip_last = FALSE;
                $skip_this = FALSE;
                $skip_next = FALSE;
                $last_actually_skipped = FALSE;
                for ($i=0; $i < count($best_laps); ++$i) {
                    $lap = $best_laps[$i];

                    // check if skipping this row
                    if ($cu->getParam("UserRecordsSkipPrivate")) {

                        // check if next shall be skipped
                        $skip_next = (($i+1) == count($best_laps)) ? TRUE : !$best_laps[$i+1]->user()->privacyFulfilled();
                        $skip_this = !$lap->user()->privacyFulfilled();

                        if (($i+1) == count($best_laps)) $skip_this = FALSE;
                    }

                    if (!$skip_this || $i==0 || !$skip_last || !$skip_next) {
                        $html .= "<tr>";
                        $html .= "<td>" . ($i + 1) . "</td>";
                        $html .= "<td>" . $cu->formatLaptime($lap->laptime()) . "</td>";
                        $html .= "<td>" . $cu->formatLaptimeDelta($lap->laptime() - $best_laps[0]->laptime()) . "</td>";
                        $html .= "<td>" . $lap->user()->html() . "</td>";
                        $html .= "<td class=\"CarImage\">" . $lap->carSkin()->html(TRUE, FALSE, TRUE) . "</td>";
                        $html .= "<td class=\"CarName\">" . $lap->carSkin()->car()->html($cc, TRUE, TRUE, FALSE) . "</td>";
                        $html .= "<td>" . sprintf("%+dkg, %+d&percnt;", $lap->ballast(), $lap->restrictor()) . "</td>";
                        $html .= "<td>" . sprintf("%0.1f", $lap->grip() * 100) . " &percnt;</td>";
                        $html .= "<td>" . $cu->formatDateTimeNoSeconds($lap->timestamp()) . "</td>";
                        $html .= "<td>" . $lap->session()->htmlName() . "</td>";
                        $html .= "<td>" . $lap->id() . "</td>";
                        $html .= "</tr>";
                        $last_actually_skipped = FALSE;

                    } else if (!$last_actually_skipped) {
                        $last_actually_skipped = TRUE;
                        $html .= "<tr><td>...</td></tr>";
                    }

                    $skip_last = $skip_this;
                }

                $html .= "</table>";
                $data[] = $html;
            }
        }


        return $data;
    }


}
