<?php

class occupation extends cContentPage {

    private $CanOccupy = FALSE;
    private $CurrentCarClass = NULL;


    public function __construct() {
        $this->MenuName   = _("Seat Occupation");
        $this->PageTitle  = "Car-Class Seat Occupation";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_ServerContent"];

    }


    public function getHtml() {
        global $acswuiUser;


        // check permisstions
        if ($acswuiUser->hasPermission('CarClass_Occupy')) {
            $this->CanOccupy = TRUE;
        }

        // get requested class
        if (isset($_REQUEST['CARCLASS_ID'])) {
            $this->CurrentCarClass = new CarClass((int) $_REQUEST['CARCLASS_ID']);
            $_SESSION['CARCLASS_ID'] = $this->CurrentCarClass->id();
        } else if (isset($_SESSION['CARCLASS_ID'])) {
            $this->CurrentCarClass = new CarClass((int) $_SESSION['CARCLASS_ID']);
        }


        // --------------------------------------------------------------------
        //                           Generate Html
        // --------------------------------------------------------------------

        if (isset($_POST['ACTION'])) {
            if ($_POST['ACTION'] == "SAVE") $this->saveOccupation();
            if ($_POST['ACTION'] == "DELETE") $this->deleteOccupation();
        }




        // --------------------------------------------------------------------
        //                           Generate Html
        // --------------------------------------------------------------------

        $html = "";

        # car class selection
        $html .= "<form method=\"post\">";
        $html .= "<select name=\"CARCLASS_ID\" onchange=\"this.form.submit()\">";
        # list existing classes
        foreach (CarClass::listClasses() as $cc) {
            if ($this->CurrentCarClass === NULL) $this->CurrentCarClass = $cc;
            $selected = ($cc->id() == $this->CurrentCarClass->id()) ? "selected" : "";
            $html .= "<option value=\"" . $cc->id() . "\" $selected>" . $cc->name() ."</option>";
        }
        $html .= "</select>";
        $html .= "</form>";

        // show current occupations
        $html .= "<h1>" . _("Current Occupations") . "</h1>";
        if ($this->CurrentCarClass !== NULL) {
            $html .= "<table>";
            $html .= "<tr><th>Skin</th><th>Driver</th></tr>";
            foreach ($this->CurrentCarClass->cars() as $car) {
                foreach ($this->CurrentCarClass->occupations($car) as $occupation) {
                    $html .= "<tr>";
                    $html .= "<td>" . $occupation->skin()->htmlImg("", 100) . "</td>";
                    $html .= "<td>" . $occupation->user()->login() . "</td>";
                    $html .= "</tr>";
                }
            }
            $html .= "</table>";
        }

        // form for occupation selection
        $html .= "<h1>" . _("Occupy Seat") . "</h1>";
        $html .= "<form method=\"post\">";
        $html .= '<input type="hidden" name="CARCLASS_ID" value="' . $this->CurrentCarClass->id() . '">';
        $html .= '<button type="submit" name="ACTION" value="DELETE">' . _("DELETE Seat Occupation") . '</button> ';
        $html .= '<button type="submit" name="ACTION" value="SAVE">' . _("Save Seat Occupation") . '</button>';


        # list all cars
        if ($this->CurrentCarClass !== NULL) {
            foreach ($this->CurrentCarClass->cars() as $car) {
                $html .= "<h2>" . $car->name() . "</h2>";



                // find occupations of other drivers
                $current_occupations = array();
                foreach ($this->CurrentCarClass->occupations($car) as $occupation) {
                    $current_occupations[$occupation->skin()->id()] = $occupation;
                }

                // offer user occupation
                if ($this->CanOccupy === TRUE) {

                    foreach ($car->skins() as $skin) {

                        // ignore broken skins
                        if ($skin->skin() == "") continue;

                        // check occupations
                        $disabled = "";
                        $checked = "";
                        $occupying_driver = "";
                        if (array_key_exists($skin->id(), $current_occupations)) {
                            if ($current_occupations[$skin->id()]->user()->id() == $acswuiUser->Id) {
                                $disabled = "";
                                $checked = "checked=\"checked\"";
                            } else {
                                $disabled = "disabled";
                                $checked = "";
                            }
                            $occupying_driver = $current_occupations[$skin->id()]->user()->login();
                        }

                        $input_id = "OCCUPATE_CAR_" . $car->id() . "_SKIN_" . $skin->id();
                        $input_value = $skin->id();

                        $html .= "<div class=\"car_skin_option\">";
                        $html .= "<input type=\"radio\" id=\"$input_id\" name=\"CARSKIN_OCCUPY\" value=\"$input_value\" $disabled $checked>";
                        $html .= "<label for=\"$input_id\">";
                        $html .= "<div class=\"driver_name\">$occupying_driver</div>";
                        $html .= $skin->htmlImg("", 100);
                        $html .= "</label>";
                        $html .= "</div>";
                    }

                }

            }
        }
        $html .= "</form>";

        return $html;
    }


    private function saveOccupation() {
        global $acswuiUser;

        // sanity check
        if ($this->CanOccupy !== TRUE) return;
        if (!isset($_POST['CARSKIN_OCCUPY'])) return;

        // get data
        $skin = new CarSkin((int) $_POST['CARSKIN_OCCUPY']);
        $user = new User($acswuiUser->Id);

        // set occupation
        $this->CurrentCarClass->occupy($user, $skin);

    }


    private function deleteOccupation() {
        global $acswuiUser;

        // sanity check
        if ($this->CanOccupy !== TRUE) return;

        // get data
        $user = new User($acswuiUser->Id);

        // set occupation
        $this->CurrentCarClass->occupy($user, $skin=NULL);

    }
}

?>
