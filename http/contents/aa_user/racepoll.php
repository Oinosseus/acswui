<?php

class racepoll extends cContentPage {

    private $CanAddDate = FALSE;

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

        // prepare html output
        $html = "";
        $html .= "<form action=\"\" method=\"post\">";
        $html .= '<button type="submit" name="ACTION" value="SAVE">' . _("Save Poll") . '</button>';


        // -------------------------------------------------------------------------------
        //                                  Save Poll
        // -------------------------------------------------------------------------------

        if (isset($_POST['ACTION'])) {

            // add new date
            if ($_POST['ACTION'] == "NEW_DATE") {
                $date = $_POST['NEW_DATE'];
                $time = $_POST['NEW_TIME'];
                $rpd = RacePollDate::newDate("$date $time");
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
            }
        }


        // -------------------------------------------------------------------------------
        //                                Vote Race Date
        // -------------------------------------------------------------------------------

        $html .= "<h1>" . _("Vote Race Date") . "</h1>";

        // table head
        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<th>" . _("Driver") . "</th>";
        $rpds = RacePollDate::listDates();
        foreach ($rpds as $rpd) {
            $html .= "<th>";
            $html .= $rpd->date()->format("Y-m-d") . "<br>";
            $html .= $rpd->date()->format("H:i");
            $html .= "</th>";
        }
        $html .= "</tr>";

        foreach (User::listUsers() as $user) {
            $html .= "<tr>";
            $html .= "<td>" . $user->login() . "</td>";
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
                    $html .= "<td class=\"$class\"><input type=\"checkbox\" name=\"$name\" value=\"$value\" $checked $disabled></td>";
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

        // table head
        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<th>" . _("Car Class") . "</th>";
        $html .= "<th>" . _("My Score") . "</th>";
        $html .= "<th>" . _("Overall Score") . "</th>";
        $html .= "</tr>";

        // table rows
        foreach (CarClass::listClasses() as $carclass) {
            $html .= "<tr>";
            $html .= "<td>" . $carclass->name() . "</td>";

            $rpcc = RacePollCarClass::fromCarClass($carclass);
            $score = $rpcc->score();
            $name = "RPCC_ID_" . $rpcc->id();

            $html .= "<td><input name=\"$name\" type=\"range\" min=\"0\" max=\"100\" step=\"20\" value=\"$score\"></td>";

            $html .= "<td>" . HumanValue::format($rpcc->scoreOverall(), "%"). "</td>";

            $html .= "<tr>";
        }

        // table end
        $html .= "</table>";



        $html .= "</form>";
        return $html;
    }

}

?>
