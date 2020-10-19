<?php

class b_rclass_entries extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Entries");
        $this->TextDomain = "acswui";
        $this->PageTitle  = _("Race Class Entries");
        $this->RequirePermissions = ["View_ServerContent"];
        $this->EditPermission     = 'RaceClass_Edit';
        $this->OccupyPermission     = 'RaceClass_Occupy';
    }

    public function getHtml() {

        // access global data
        global $acswuiDatabase;
        global $acswuiUser;
        global $acswuiLog;

        // reused variables
        $raceclass_id = 0;
//         $raceseries_cars = array(); // a list of RaceSeriesCars[Car] that are in the race sieres



        // --------------------------------------------------------------------
        //                        Determine Requested Series
        // --------------------------------------------------------------------

        // get request data
        if (isset($_REQUEST['RACECLASS_ID'])) {
            $raceclass_id = $_REQUEST['RACECLASS_ID'];
            $_SESSION['RACECLASS_ID'] = $_REQUEST['RACECLASS_ID'];
        } else if (isset($_SESSION['RACECLASS_ID'])) {
            $raceclass_id = $_SESSION['RACECLASS_ID'];
        }

        // check requested race series
        $raceclass_id_exists = False;
        $raceclass_id_first  = Null;
        foreach ($acswuiDatabase->fetch_2d_array("RaceClasses", ['Id'], [], []) as $rs) {
            // check if $raceclass_id exists
            if ($rs['Id'] === $raceclass_id) {
                $raceclass_id_exists = True;
            }
            // save first existing Id
            if (is_null($raceclass_id_first)) {
                $raceclass_id_first = $rs['Id'];
            }
        }

        // set race series if request is invalid
        if ($raceclass_id_exists !== True)
            $raceclass_id = $raceclass_id_first;

        // save last requested race series
        $_SESSION['RACECLASS_ID'] = $raceclass_id;



        // --------------------------------------------------------------------
        //                             SAVE ACTION
        // --------------------------------------------------------------------

        if ($acswuiUser->hasPermission($this->EditPermission) && isset($_REQUEST['RaceSeriesCarId'])) {
            $rcc_id = $_REQUEST['RaceSeriesCarId'];

            // check if RaceSeriesCarId is valid
            $RaceSeriesCarId_is_valid = False;
            foreach ($acswuiDatabase->fetch_2d_array("RaceClassCars", ['Id'], ['RaceClass'], [$raceclass_id]) as $rcc) {
                if ($rcc['Id'] == $rcc_id) $RaceSeriesCarId_is_valid = True;
            }

            // generate warning for wrong Id
            if (!$RaceSeriesCarId_is_valid) {
                $acswuiLog->log_warning("RaceSeriesCarId='" . $rcc_id . "' is not valid!");

            // process action if Id is valid
            } else {

                // delete entry
                if (isset($_REQUEST['DELETE_ENTRY'])) {
                    $acswuiDatabase->delete_row("RaceClassEntries", $_REQUEST['DELETE_ENTRY']);
                }

                // add new entry
                if (isset($_REQUEST['ADD_NEW_ENTRY']) && $_REQUEST['ADD_NEW_ENTRY'] == "TRUE") {
                    $acswuiDatabase->insert_row("RaceClassEntries", ['RaceClassCar' => $rcc_id]);
                }

                // save all rsc entries
                if (isset($_REQUEST['SAVE_ENTRIES']) && $_REQUEST['SAVE_ENTRIES'] == "TRUE") {
                    foreach($acswuiDatabase->fetch_2d_array("RaceClassEntries", ['Id'], ['RaceClassCar'], [$rcc_id]) as $rce) {
                        $field_list = array();
                        if (isset($_REQUEST["RaceSeriesEntry_" . $rce['Id'] . "_CarSkin"])) $field_list['CarSkin'] = $_REQUEST["RaceSeriesEntry_" . $rce['Id'] . "_CarSkin"];
                        if (isset($_REQUEST["RaceSeriesEntry_" . $rce['Id'] . "_Ballast"])) $field_list['Ballast'] = $_REQUEST["RaceSeriesEntry_" . $rce['Id'] . "_Ballast"];
                        if (isset($_REQUEST["RaceSeriesEntry_" . $rce['Id'] . "_User"])) $field_list['User'] = $_REQUEST["RaceSeriesEntry_" . $rce['Id'] . "_User"];
                        $field_list['AllowUserOccupation'] = (isset($_REQUEST["RaceSeriesEntry_" . $rce['Id'] . "_AllowUserOccupation"]) && $_REQUEST["RaceSeriesEntry_" . $rce['Id'] . "_AllowUserOccupation"] == "TRUE") ? 1:0;
                        if (count($field_list)) $acswuiDatabase->update_row("RaceClassEntries", $rce['Id'], $field_list);
                    }
                }
            }
        }



        // --------------------------------------------------------------------
        //                             Occupy Car
        // --------------------------------------------------------------------

        if ($acswuiUser->IsLogged && isset($_REQUEST['OCCUPY_CAR']) && $acswuiUser->hasPermission($this->OccupyPermission)) {
            $rcc_id = $_REQUEST['OCCUPY_CAR'];

            if (!$this->car_occupation_possible($raceclass_id, $rcc_id)) {
                $acswuiLog->log_warning("User " . $acswuiUser->Id . "/" . $acswuiUser->Login . " tried to occupy the race series car " . $rcc_id . " without permission.");

            } else {

                $ballast = 0;
                foreach ($acswuiDatabase->fetch_2d_array('RaceClassCars', ['Id'], ['RaceClass'], [$raceclass_id]) as $rcc) {
                    foreach ($acswuiDatabase->fetch_2d_array('RaceSeriesEntries', ['Id', 'Ballast'], ['RaceSeriesCar', 'User'], [$rcc['Id'], $acswuiUser->Id]) as $rce) {
                        // get actual ballast of the user in this race series
                        if ($rce['Ballast'] > $ballast) $ballast = $rce['Ballast'];
                        // delete existing occupation
                        $acswuiDatabase->update_row('RaceClassEntries', $rce['Id'],  ['Ballast' => 0, 'User' => 0]);
                    }
                }

                // set new occupatiuon
                $acswuiDatabase->insert_row("RaceClassEntries", ['RaceClassCar' => $rcc_id, 'Ballast' => $ballast, 'User' => $acswuiUser->Id]);

            }
        }



        // --------------------------------------------------------------------
        //                             Occupy Entry
        // --------------------------------------------------------------------

        if ($acswuiUser->IsLogged && isset($_REQUEST['OCCUPY_ENTRY'])) {
            $rce_id = $_REQUEST['OCCUPY_ENTRY'];
            if (!$this->entry_occupation_possible($raceclass_id, $rce_id)) {
                $acswuiLog->log_warning("User " . $acswuiUser->Id . "/" . $acswuiUser->Login . " tried to occupy the race series car entry " . $rce_id . " without permission.");
            } else {


                $ballast = 0;
                foreach ($acswuiDatabase->fetch_2d_array('RaceClassCars', ['Id'], ['RaceClass'], [$raceclass_id]) as $rcc) {
                    foreach ($acswuiDatabase->fetch_2d_array('RaceClassEntries', ['Id', 'Ballast'], ['RaceClassCar', 'User'], [$rcc['Id'], $acswuiUser->Id]) as $rce) {
                        // get actual ballast of the user in this race series
                        if ($rce['Ballast'] > $ballast) $ballast = $rce['Ballast'];
                        // delete existing occupation
                        $acswuiDatabase->update_row('RaceClassEntries', $rce['Id'],  ['Ballast' => 0, 'User' => 0]);
                    }
                }

                // set new occupatiuon
                $acswuiDatabase->update_row('RaceClassEntries', $rce_id,  ['Ballast' => $ballast, 'User' => $acswuiUser->Id]);

            }
        }



        // --------------------------------------------------------------------
        //                     Intialize Html Output
        // --------------------------------------------------------------------

        $html  = '';
        $html .= '<script src="' . $this->getRelPath() . 'rclass_entries.js"></script>';



        // --------------------------------------------------------------------
        //                     Race Series Selection
        // --------------------------------------------------------------------

        $html .= "<form action=\"\" method=\"post\">";
        $html .= "<select name=\"RACECLASS_ID\" onchange=\"this.form.submit()\">";
        foreach ($acswuiDatabase->fetch_2d_array("RaceClasses", ['Id', "Name"], [], [], "Name") as $rs) {
            $selected = ($rs['Id'] == $raceclass_id) ? "selected" : "";
            $html .= "<option value=\"" . $rs['Id'] . "\" $selected>" . $rs['Name'] ."</option>";
        }
        $html .= "</select>";
        $html .= "</form>";



        // --------------------------------------------------------------------
        //                           Available Cars
        // --------------------------------------------------------------------

        foreach ($acswuiDatabase->fetch_2d_array("RaceClassCars", ['Id', "Car", 'Ballast'], ['RaceClass'], [$raceclass_id]) as $rcc)  {


            // get car infos
            $cars  = $acswuiDatabase->fetch_2d_array("Cars", ['Id', 'Car', "Name", 'Brand'], ['Id'], [$rcc['Car']]);
            $car   = $cars[0];
            $skins = $acswuiDatabase->fetch_2d_array("CarSkins", ['Id', 'Skin'], ['Car'], [$rcc['Car']]);

            // headline for each race series car
            $html .= "<h1>" . $car['Name'] . "</h1>";


            // car entry table
            $html .= "<form action=\"\" method=\"post\">";
            $html .= "<input type=\"hidden\" name=\"RaceSeriesCarId\" value=\"" . $rcc['Id'] . "\">";
            $html .= "<table id=\"available_cars\">";
            $html .= "<tr>";
            $html .= "<th colspan=\"2\">". _("Skin") ."</th>";
            $html .= "<th>" . _("Ballast [Kg]") ."</th>";
            $html .= "<th>" . _("Driver") ."</th>";
            $html .= "</tr>";

            // request entries
            foreach ($acswuiDatabase->fetch_2d_array("RaceClassEntries", ['Id', "CarSkin", 'Ballast', 'User'], ['RaceClassCar'], [$rcc['Id']]) as $rce)  {

                // check edit permissions
                $readonly = ($acswuiUser->hasPermission($this->EditPermission)) ? "":"readonly";
                $disabled = ($acswuiUser->hasPermission($this->EditPermission)) ? "":"disabled";

                $html .= "<tr>";
                // carskin
                $img_id    = "SKIN_IMG_" . $rcc['Id'] . "_" . $rce['Id'];
                $select_id = "SELECT_" . $rcc['Id'] . "_" . $rce['Id'];
                $html .= "<td>" . getImgCarSkin($rce['CarSkin'], $img_id) . "</td>";
                $html .= "<td><select name=\"RaceSeriesEntry_" . $rce['Id'] . "_CarSkin\" id=\"$select_id\" onchange=\"update_img('$img_id', '$select_id', '" . $car['Car'] . "')\" onkeyup=\"update_img('$img_id', '$select_id', '" . $car['Car'] . "')\" $readonly $disabled>";
                $html .= "<option value=\"0\"></option>";
                foreach ($skins as $skin) {
                    $selected = ($rce['CarSkin'] == $skin['Id']) ? "selected" : "";
                    $html .= "<option value=\"" . $skin['Id'] ."\" $selected>" . $skin['Skin'] . "</option>";
                }
                $html .= "<select></td>";

                $html .= "<td><input type=\"number\" name=\"RaceSeriesEntry_" . $rce['Id'] . "_Ballast\" value=\"" . $rce['Ballast'] . "\" $readonly></td>";

                // users
                $html .= "<td><select name=\"RaceSeriesEntry_" . $rce['Id'] . "_User\" $readonly $disabled>";
                $html .= "<option value=\"0\"></option>";
                foreach ($acswuiDatabase->fetch_2d_array("Users", ['Id', 'Login'], [], [], 'Login') as $user) {
                    $selected = ($rce['User'] == $user['Id']) ? "selected":"";
                    $html .= "<option value=\"" . $user['Id'] . "\" $selected>" . $user['Login'] . "</option>";
                }
                $html .= "</select></td>";

                $html .= "<td>";
                // delete button
                if ($acswuiUser->hasPermission($this->EditPermission)) {
                    $html .= "<button type=\"submit\" name=\"DELETE_ENTRY\" value=\"" . $rce['Id'] . "\">" . _("delete") . "</button>";
                }
                // occupation
                if ($this->entry_occupation_possible($raceclass_id, $rce['Id'])) {
                    $html .= "<button type=\"submit\" name=\"OCCUPY_ENTRY\" value=\"" . $rce['Id'] . "\">" . _("Occupy") . "</button>";
                }
                $html .= "</td>";

                $html .= "</tr>";
            }

            // new car entry
            $html .= "<tr><td colspan=\"5\">";
            if ($acswuiUser->hasPermission($this->EditPermission)) {
                $html .= "<button type=\"submit\" name=\"ADD_NEW_ENTRY\" value=\"TRUE\">" . _("New Entry") . "</button>";
                $html .= "<button type=\"submit\" name=\"SAVE_ENTRIES\" value=\"TRUE\">" . _("Save Car Entries") . "</button>";
            }
            // car occupation
            if ($this->car_occupation_possible($raceclass_id, $rcc['Id']) && $acswuiUser->hasPermission($this->OccupyPermission)) {
                $html .= "<button type=\"submit\" name=\"OCCUPY_CAR\" value=\"" . $rcc['Id'] . "\">" . _("Occupy This Car") . "</button>";
            }

            $html .= "</td></tr>";
            $html .= "</table>";
            $html .= "</form>";

        }


        if ($acswuiUser->hasPermission($this->EditPermission)) {
            $html .= "<button type=\"submit\">" . _("Save Race Series") . "</button>";
        }

        return $html;
    }

    private function series_occupation_possible($race_series) {
        // returns True or False wheter the occupation of the race series is possible by the user

        global $acswuiDatabase;
        global $acswuiUser;

        $ret = False;

        // check if user is logged
        if (!$acswuiUser->IsLogged) return False;

        // check if user has Id>0 (no root allowed)
        if ($acswuiUser->Id <= 0) return False;

        // get race series
        $car_query = $acswuiDatabase->fetch_2d_array("RaceClasses", ['AllowOccupation'], ['Id'], [$race_series]);
        if (count($car_query) == 0) return False;
        if ($car_query[0]['AllowOccupation'] > 0) $ret = True;

        return $ret;
    }

    private function car_occupation_possible($race_series, $car) {
        // check if a car can be occupied
        // - series can be occupied in general
        // - user has not already occupied this car
        // - no other unoccupied entries are within this car

        global $acswuiDatabase;
        global $acswuiUser;

        // check if occupation is allowed in the series
        if (!$this->series_occupation_possible($race_series)) return False;

        // check entries of same race series car
        foreach ($acswuiDatabase->fetch_2d_array("RaceClassEntries", ['User'], ['RaceClassCar'], [$car]) as $rce) {
            if ($rce['User'] == $acswuiUser->Id) return False;
            if ($rce['User'] == 0) return False;
        }

        return True;
    }

    private function entry_occupation_possible($race_series, $entry) {
        // check if a entry can be occupied
        // - series can be occupied in general
        // - no other user occupied the entry
        // - user has not already occupied this car

        global $acswuiDatabase;
        global $acswuiUser;

        // check if occupation is allowed in the series
        if (!$this->series_occupation_possible($race_series)) return False;

        // check entry information
        $rce = $acswuiDatabase->fetch_2d_array("RaceClassEntries", ['RaceClassCar', 'User'], ['Id'], [$entry]);
        if ($rce[0]['User'] > 0) return False;

        // check entries of same race series car
        foreach ($acswuiDatabase->fetch_2d_array("RaceClassEntries", ['User'], ['RaceClassCar'], [$rce[0]['RaceClassCar']]) as $rce) {
            if ($rce['User'] == $acswuiUser->Id) return False;
        }

        return True;
    }
}

?>

