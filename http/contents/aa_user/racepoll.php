<?php

class racepoll extends cContentPage {

    private $CanAddDate = FALSE;
    private $CanDeleteDate = FALSE;
    private $CanVoteTrack = FALSE;
    private $CanDeleteTrack = FALSE;
    private $CanAddTrack = FALSE;

    public function __construct() {
        $this->MenuName    = _("Race Poll");
        $this->PageTitle   = _("Race Poll");
        $this->TextDomain  = "acswui";
        $this->RequireRoot = false;
        $this->RequirePermissions = ["RacePoll"];
    }

    public function getHtml() {
        global $acswuiUser;
        global $acswuiDatabase;

        // check rights
        if ($acswuiUser->hasPermission("RacePoll_AddDates")) $this->CanAddDate = TRUE;
        if ($acswuiUser->hasPermission("RacePoll_DeleteDates")) $this->CanDeleteDate = TRUE;
        if ($acswuiUser->hasPermission("RacePoll_VoteTracks")) $this->CanVoteTrack = TRUE;
        if ($acswuiUser->hasPermission("RacePoll_AddTracks")) $this->CanAddTrack = TRUE;
        if ($acswuiUser->hasPermission("RacePoll_DeleteTracks")) $this->CanDeleteTrack = TRUE;

        // prepare html output
        $html = "";
        $html .= "<form action=\"\" method=\"post\" id=\"mainpoll\">";
        $html .= '<button type="submit" name="ACTION" value="SAVE">' . _("Save Poll") . '</button>';


        // -------------------------------------------------------------------------------
        //                                  Process Resquests
        // -------------------------------------------------------------------------------

        // delete date
        if ($this->CanDeleteDate && isset($_POST['DELETE_DATE'])) {
            $id = $_POST['DELETE_DATE'];
            RacePollDate::delete($id);
        }

        // delete track
        if ($this->CanDeleteTrack && isset($_POST['DELETE_TRACK'])) {
            $id = $_POST['DELETE_TRACK'];
            RacePollTrack::delete($id);
        }

        // save
        if (isset($_POST['ACTION'])) {

            // add new date
            if ($this->CanAddDate && $_POST['ACTION'] == "NEW_DATE") {
                $date = $_POST['NEW_DATE'];
                $time = $_POST['NEW_TIME'];
                $rpd = RacePollDate::newDate("$date $time");
            }

            // add new track
            if ($this->CanAddTrack && $_POST['ACTION'] == "NEW_TRACK") {
                $cc = new CarClass($_POST['CAR_CLASS']);
                $t = new Track($_POST['TRACK']);
                RacePollTrack::newTrack($cc, $t);
            }

            // save poll
            if ($_POST['ACTION'] == "SAVE") {

                // dates
                foreach (RacePollDate::listDates() as $rpd) {
                    $name = "AVAILABLE_DATE_ID" . $rpd->id();
                    $rpd->setAvailable(isset($_POST[$name]) && $_POST[$name] == "TRUE");
                }

                // car classes
                foreach (CarClass::listClasses() as $carclass) {
                    $rpcc = RacePollCarClass::fromCarClass($carclass);
                    $name = "RPCC_ID_" . $rpcc->id();
                    $score = (int) $_POST[$name];
                    $rpcc->setScore($score);
                }

                // tracks
                foreach (CarClass::listClasses() as $carclass) {
                    foreach (RacePollTrack::listTracks($carclass) as $rpt) {
                        $rpt_id = $rpt->id();
                        if (!array_key_exists("RPT_ID_$rpt_id", $_POST)) continue;
                        $new_score = $_POST["RPT_ID_$rpt_id"];
                        $rpt->setScoreUser($new_score);
                    }
                }

            }
        }


        // -------------------------------------------------------------------------------
        //                                Vote Race Date
        // -------------------------------------------------------------------------------

        $html .= "<h1>" . _("Vote Race Date") . "</h1>";

        $html .= "<table>";
        $rpds = RacePollDate::listDates();

        // delete button
        if ($this->CanDeleteDate === TRUE) {
        $html .= "<tr>";
        $html .= "<td></td>";
        foreach ($rpds as $rpd) {
            $html .= "<td>";
            $id = $rpd->id();
            $html .= "<button type=\"submit\" name=\"DELETE_DATE\" value=\"$id\">" . _("Delete") . "</button>";
            $html .= "</td>";
        }
        $html .= "</tr>";
        }

        // table head
        $html .= "<tr>";
        $html .= "<th>" . _("Driver") . "</th>";
        foreach ($rpds as $rpd) {
            $html .= "<th>";
            $html .= $rpd->date()->format("Y-m-d") . "<br>";
            $html .= $rpd->date()->format("H:i");
            $html .= "</th>";
        }
        $html .= "</tr>";

        foreach (User::listUsers() as $user) {
            $html .= "<tr>";
            $html .= "<td>" . $user->displayName() . "</td>";
            foreach ($rpds as $rpd) {

                $class = "availability";

                if ($rpd->isAvailable($user)) {
                    $checked = 'checked="checked"';
                    $class .= " available";
                } else if ($rpd->isUnAvailable($user)) {
                    $checked = "";
                    $class .= " unavailable";
                } else {
                    $checked = "";
                }

                if ($user->id() == $acswuiUser->Id) {
                    $disabled = "";
                    $name = "AVAILABLE_DATE_ID" . $rpd->id();
                    $value = "TRUE";
                    $class .= " user";
                    $html .= "<td class=\"$class\"><input type=\"checkbox\" name=\"$name\" value=\"$value\" $checked $disabled form=\"mainpoll\"></td>";
                } else {
                    $disabled = 'disabled="disabled"';
                    $name = "";
                    $value = "";
                    $class .= " other";
                    $html .= "<td class=\"$class\">&nbsp;</td>";
                }

            }
            $html .= "</tr>";
        }

        // summarize
        $html .= "<tr>";
        $html .= "<td></td>";
        foreach ($rpds as $rpd) {
            $html .= "<td>" . $rpd->availabilities() . "</td>";
        }
        $html .= "</tr>";

        $html .= "</table>";

        // add new date
        if ($this->CanAddDate === TRUE) {
            $current_date = date("Y-m-d");
            $current_time = date("H:i");
            $html .= _("New Date:");
            $html .= " <input type=\"date\" name=\"NEW_DATE\"  value=\"$current_date\"/>";
            $html .= " <input type=\"time\" name=\"NEW_TIME\"  value=\"$current_time\"/>";
            $html .= ' <button type="submit" name="ACTION" value="NEW_DATE">' . _("Add Date") . '</button>';
//             $html .= "</th>";
        }


        // -------------------------------------------------------------------------------
        //                                Vote Car Classes
        // -------------------------------------------------------------------------------

        $html .= "<h1>" . _("Vote Race Classes") . "</h1>";

        // create list of polls
        $rpcc_list = array();
        foreach (CarClass::listClasses() as $carclass) {
            $rpcc_list[] = RacePollCarClass::fromCarClass($carclass);
        }
        function compare_rpcc($left, $right) {
            return ($left->scoreOverall() < $right->scoreOverall()) ? 1 : -1;
        }
        usort($rpcc_list, "compare_rpcc");


        // table head
        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<th>" . _("Car Class") . "</th>";
        $html .= "<th>" . _("My Score") . "</th>";
        $html .= "<th>" . _("Overall Score") . "</th>";
        $html .= "</tr>";

        // table rows
        foreach ($rpcc_list as $rpcc) {
            $html .= "<tr>";

            $cc_name = $rpcc->carClass()->name();
            $cc_id = $rpcc->carClass()->id();
            $html .= "<td><a href=\"#CARCLASS_$cc_id\">$cc_name</a></td>";

            $score = $rpcc->score();
            $name = "RPCC_ID_" . $rpcc->id();

            $html .= "<td><input name=\"$name\" type=\"range\" min=\"0\" max=\"100\" step=\"20\" value=\"$score\" form=\"mainpoll\"></td>";

            $html .= "<td>" . HumanValue::format($rpcc->scoreOverall(), "%"). "</td>";

            $html .= "<tr>";
        }

        // table end
        $html .= "</table>";



        // -------------------------------------------------------------------------------
        //                                  Vote Tracks
        // -------------------------------------------------------------------------------

        function compare_racepolltrack_overallscore($left, $right) {
            return ($left->getScoreOverall() < $right->getScoreOverall()) ? 1 : -1;
        }

        if ($this->CanVoteTrack) {
            $html .= "<h1>" . _("Vote Tracks") . "</h1>";

            $tracklist = Track::listTracks();

            foreach (CarClass::listClasses() as $carclass) {
                $cc_name = $carclass->name();
                $cc_id = $carclass->id();
                $html .= "<h2 id=\"CARCLASS_$cc_id\">$cc_name</h2>";

                # get track polls ordered by overall score
                $rpt_list = RacePollTrack::listTracks($carclass);
                usort($rpt_list, "compare_racepolltrack_overallscore");


                # list tracks
                foreach ($rpt_list as $rpt) {
                    $t = $rpt->track();
                    $rpt_id = $rpt->id();
                    $score_user = $rpt->getScoreUser();
                    $score_overall = $rpt->getScoreOverall();
                    $html .= "<div class=\"trackbox\">";
                    $html .= "<label>" . $t->name() . " (" . HumanValue::format($score_overall, "%") . ")</label>";
                    $html .= $t->htmlImg("", 100);
                    $html .= "<input name=\"RPT_ID_$rpt_id\" type=\"range\" min=\"0\" max=\"100\" step=\"20\" value=\"$score_user\" form=\"mainpoll\">";

                    if ($this->CanDeleteTrack) {
                        $form_id = "TrackPollDelete$rpt_id";
                        $html .= "<form id=\"$form_id\" action=\"\" method=\"post\">";
                        $html .= "<button type=\"submit\" name=\"DELETE_TRACK\" value=\"$rpt_id\" form=\"$form_id\">" . _("Delete") . "</button>";
                        $html .= "</form>";
                    }

                    $html .= "</div>";
                }

                // add new track
                if ($this->CanAddTrack === TRUE) {
                    $form_id = "TrackPollAddTrackToCarClass$cc_id";
                    $html .= "<form id=\"$form_id\" action=\"\" method=\"post\">";
                    $html .= "<input type=\"hidden\" name=\"CAR_CLASS\" value=\"$cc_id\" form=\"$form_id\">";
                    $html .= "<select name=\"TRACK\" form=\"$form_id\">";
                    foreach ($tracklist as $t) {
                        $name_str = $t->name();
                        $name_str .= " (" . sprintf("%0.1f", $t->length()/1000) . "km";
                        $name_str .= ", " . $t->pitboxes() . "pits)";
                        $html .= "<option value=\"" . $t->id() . "\">" . $name_str . "</option>";
                    }
                    $html .= '</select>';
                    $html .= " <button type=\"submit\" name=\"ACTION\" value=\"NEW_TRACK\" form=\"$form_id\">" . _("Add Track") . "</button>";
                    $html .= "</form>";
                }
            }
        }


        $html .= "</form>";
        return $html;
    }

}

?>
