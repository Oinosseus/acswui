<?php

class racepoll extends cContentPage {

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

        $html = "";
        
        // -------------------------------------------------------------------------------
        //                                  Save Poll
        // -------------------------------------------------------------------------------
        
        if (isset($_POST['ACTION']) && $_POST['ACTION'] == "SAVE") {
            foreach (CarClass::listClasses() as $carclass) {
                $rpcc = RacePollCarClass::fromCarClass($carclass);
                $name = "RPCC_ID_" . $rpcc->id();
                $score = (int) $_POST[$name];
                $rpcc->setScore($score);
            }
        }

        
        // -------------------------------------------------------------------------------
        //                                Score Car Classes
        // -------------------------------------------------------------------------------
        
        $html .= "<form action=\"\" method=\"post\">";
        $html .= '<button type="submit" name="ACTION" value="SAVE">' . _("Save Poll") . '</button>';

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
