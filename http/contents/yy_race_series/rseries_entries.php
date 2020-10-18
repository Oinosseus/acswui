<?php

class rseries_entries extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Race Series Entries");
        $this->TextDomain = "acswui";
        $this->PageTitle  = _("Race Series Entry Management");
        $this->RequirePermissions = ["View_ServerContent"];
        $this->EditPermission     = 'Edit_RaceSeries_Entries';
    }

    public function getHtml() {

        // access global data
        global $acswuiDatabase;
        global $acswuiUser;
        global $acswuiLog;

        // reused variables
        $raceseries_id = 0;
        $raceseries_cars = array(); // a list of RaceSeriesCars[Car] that are in the race sieres



        // --------------------------------------------------------------------
        //                        Determine Requested Series
        // --------------------------------------------------------------------

        // get request data
        if (isset($_REQUEST['RACESERIES_ID'])) {
            $raceseries_id = $_REQUEST['RACESERIES_ID'];
            $_SESSION['RACESERIES_ID'] = $_REQUEST['RACESERIES_ID'];
        } else if (isset($_SESSION['RACESERIES_ID'])) {
            $raceseries_id = $_SESSION['RACESERIES_ID'];
        }

        // check requested race series
        $raceseries_id_exists = False;
        $raceseries_id_first  = Null;
        foreach ($acswuiDatabase->fetch_2d_array("RaceSeries", ['Id'], [], []) as $rs) {
            // check if $raceseries_id exists
            if ($rs['Id'] === $raceseries_id) {
                $raceseries_id_exists = True;
            }
            // save first existing Id
            if (is_null($raceseries_id_first)) {
                $raceseries_id_first = $rs['Id'];
            }
        }

        // set race series if request is invalid
        if ($raceseries_id_exists !== True)
            $raceseries_id = $raceseries_id_first;

        // save last requested race series
        $_SESSION['RACESERIES_ID'] = $raceseries_id;



        // --------------------------------------------------------------------
        //                             SAVE ACTION
        // --------------------------------------------------------------------

        if ($acswuiUser->hasPermission($this->EditPermission) && isset($_REQUEST['RaceSeriesCarId'])) {
            $rsc_id = $_REQUEST['RaceSeriesCarId'];

            // check if RaceSeriesCarId is valid
            $RaceSeriesCarId_is_valid = False;
            foreach ($acswuiDatabase->fetch_2d_array("RaceSeriesCars", ['Id'], ['RaceSeries'], [$raceseries_id]) as $rsc) {
                if ($rsc['Id'] == $rsc_id) $RaceSeriesCarId_is_valid = True;
            }

            // generate warning for wrong Id
            if (!$RaceSeriesCarId_is_valid) {
                $acswuiLog->log_warning("RaceSeriesCarId='" . $rsc_id . "' is not valid!");

            // process action if Id is valid
            } else {

                // delete entry
                if (isset($_REQUEST['DELETE_ENTRY'])) {
                    $acswuiDatabase->delete_row("RaceSeriesEntries", $_REQUEST['DELETE_ENTRY']);
                }

                // add new entry
                if (isset($_REQUEST['ADD_NEW_ENTRY']) && $_REQUEST['ADD_NEW_ENTRY'] == "TRUE") {
                    $acswuiDatabase->insert_row("RaceSeriesEntries", ['RaceSeriesCar' => $rsc_id]);
                }

                // save all rsc entries
                if (isset($_REQUEST['SAVE_ENTRIES']) && $_REQUEST['SAVE_ENTRIES'] == "TRUE") {
                    foreach($acswuiDatabase->fetch_2d_array("RaceSeriesEntries", ['Id'], ['RaceSeriesCar'], [$rsc_id]) as $rse) {
                        $field_list = array();
                        if (isset($_REQUEST["RaceSeriesEntry_" . $rse['Id'] . "_CarSkin"])) $field_list['CarSkin'] = $_REQUEST["RaceSeriesEntry_" . $rse['Id'] . "_CarSkin"];
                        if (isset($_REQUEST["RaceSeriesEntry_" . $rse['Id'] . "_Ballast"])) $field_list['Ballast'] = $_REQUEST["RaceSeriesEntry_" . $rse['Id'] . "_Ballast"];
                        if (isset($_REQUEST["RaceSeriesEntry_" . $rse['Id'] . "_User"])) $field_list['User'] = $_REQUEST["RaceSeriesEntry_" . $rse['Id'] . "_User"];
                        $field_list['AllowUserOccupation'] = (isset($_REQUEST["RaceSeriesEntry_" . $rse['Id'] . "_AllowUserOccupation"]) && $_REQUEST["RaceSeriesEntry_" . $rse['Id'] . "_AllowUserOccupation"] == "TRUE") ? 1:0;
                        if (count($field_list)) $acswuiDatabase->update_row("RaceSeriesEntries", $rse['Id'], $field_list);
                    }
                }
            }
        }



        // --------------------------------------------------------------------
        //                             Occupy Car
        // --------------------------------------------------------------------

        if ($acswuiUser->IsLogged && isset($_REQUEST['OCCUPY_CAR'])) {
            $rsc_id = $_REQUEST['OCCUPY_CAR'];
            if (!$this->car_occupation_possible($raceseries_id, $rsc_id)) {
                $acswuiLog->log_warning("User " . $acswuiUser->Id . "/" . $acswuiUser->Login . " tried to occupy the race series car " . $rsc_id . " without permission.");
            } else {

                $ballast = 0;
                foreach ($acswuiDatabase->fetch_2d_array('RaceSeriesCars', ['Id'], ['RaceSeries'], [$raceseries_id]) as $rsc) {
                    foreach ($acswuiDatabase->fetch_2d_array('RaceSeriesEntries', ['Id', 'Ballast'], ['RaceSeriesCar', 'User'], [$rsc['Id'], $acswuiUser->Id]) as $rse) {
                        // get actual ballast of the user in this race series
                        if ($rse['Ballast'] > $ballast) $ballast = $rse['Ballast'];
                        // delete existing occupation
                        $acswuiDatabase->update_row('RaceSeriesEntries', $rse['Id'],  ['Ballast' => 0, 'User' => 0]);
                    }
                }

                // set new occupatiuon
                $acswuiDatabase->insert_row("RaceSeriesEntries", ['RaceSeriesCar' => $rsc_id, 'Ballast' => $ballast, 'User' => $acswuiUser->Id]);

            }
        }



        // --------------------------------------------------------------------
        //                             Occupy Entry
        // --------------------------------------------------------------------

        if ($acswuiUser->IsLogged && isset($_REQUEST['OCCUPY_ENTRY'])) {
            $rse_id = $_REQUEST['OCCUPY_ENTRY'];
            if (!$this->entry_occupation_possible($raceseries_id, $rse_id)) {
                $acswuiLog->log_warning("User " . $acswuiUser->Id . "/" . $acswuiUser->Login . " tried to occupy the race series car entry " . $rse_id . " without permission.");
            } else {


                $ballast = 0;
                foreach ($acswuiDatabase->fetch_2d_array('RaceSeriesCars', ['Id'], ['RaceSeries'], [$raceseries_id]) as $rsc) {
                    foreach ($acswuiDatabase->fetch_2d_array('RaceSeriesEntries', ['Id', 'Ballast'], ['RaceSeriesCar', 'User'], [$rsc['Id'], $acswuiUser->Id]) as $rse) {
                        // get actual ballast of the user in this race series
                        if ($rse['Ballast'] > $ballast) $ballast = $rse['Ballast'];
                        // delete existing occupation
                        $acswuiDatabase->update_row('RaceSeriesEntries', $rse['Id'],  ['Ballast' => 0, 'User' => 0]);
                    }
                }

                // set new occupatiuon
                $acswuiDatabase->update_row('RaceSeriesEntries', $rse_id,  ['Ballast' => $ballast, 'User' => $acswuiUser->Id]);

            }
        }



        // --------------------------------------------------------------------
        //                     Intialize Html Output
        // --------------------------------------------------------------------

        $html  = '';
        $html .= '<script src="' . $this->getRelPath() . 'rseries_entries.js"></script>';



        // --------------------------------------------------------------------
        //                     Race Series Selection
        // --------------------------------------------------------------------

        $html .= "<form action=\"\" method=\"post\">";
        $html .= "<select name=\"RACESERIES_ID\" onchange=\"this.form.submit()\">";
        foreach ($acswuiDatabase->fetch_2d_array("RaceSeries", ['Id', "Name"], [], [], "Name") as $rs) {
            $selected = ($rs['Id'] == $raceseries_id) ? "selected" : "";
            $html .= "<option value=\"" . $rs['Id'] . "\" $selected>" . $rs['Name'] ."</option>";
        }
        $html .= "</select>";
        $html .= "</form>";



        // --------------------------------------------------------------------
        //                           Available Cars
        // --------------------------------------------------------------------

        foreach ($acswuiDatabase->fetch_2d_array("RaceSeriesCars", ['Id', "Car", 'Ballast'], ['RaceSeries'], [$raceseries_id]) as $rsc)  {


            // get car infos
            $cars  = $acswuiDatabase->fetch_2d_array("Cars", ['Id', 'Car', "Name", 'Brand'], ['Id'], [$rsc['Car']]);
            $car   = $cars[0];
            $skins = $acswuiDatabase->fetch_2d_array("CarSkins", ['Id', 'Skin'], ['Car'], [$rsc['Car']]);

            // headline for each race series car
            $html .= "<h1>" . $car['Name'] . "</h1>";


            // car entry table
            $html .= "<form action=\"\" method=\"post\">";
            $html .= "<input type=\"hidden\" name=\"RaceSeriesCarId\" value=\"" . $rsc['Id'] . "\">";
            $html .= "<table id=\"available_cars\">";
            $html .= "<tr>";
            $html .= "<th colspan=\"2\">". _("Skin") ."</th>";
            $html .= "<th>" . _("Ballast [Kg]") ."</th>";
            $html .= "<th>" . _("Driver") ."</th>";
            $html .= "</tr>";

            // request entries
            foreach ($acswuiDatabase->fetch_2d_array("RaceSeriesEntries", ['Id', "CarSkin", 'Ballast', 'User'], ['RaceSeriesCar'], [$rsc['Id']]) as $rse)  {

                // check edit permissions
                $readonly = ($acswuiUser->hasPermission($this->EditPermission)) ? "":"readonly";
                $disabled = ($acswuiUser->hasPermission($this->EditPermission)) ? "":"disabled";

                $html .= "<tr>";
                // carskin
                $img_id    = "SKIN_IMG_" . $rsc['Id'] . "_" . $rse['Id'];
                $select_id = "SELECT_" . $rsc['Id'] . "_" . $rse['Id'];
                $html .= "<td>" . getImgCarSkin($rse['CarSkin'], $img_id) . "</td>";
                $html .= "<td><select name=\"RaceSeriesEntry_" . $rse['Id'] . "_CarSkin\" id=\"$select_id\" onchange=\"update_img('$img_id', '$select_id', '" . $car['Car'] . "')\" onkeyup=\"update_img('$img_id', '$select_id', '" . $car['Car'] . "')\" $readonly $disabled>";
                $html .= "<option value=\"0\"></option>";
                foreach ($skins as $skin) {
                    $selected = ($rse['CarSkin'] == $skin['Id']) ? "selected" : "";
                    $html .= "<option value=\"" . $skin['Id'] ."\" $selected>" . $skin['Skin'] . "</option>";
                }
                $html .= "<select></td>";

                $html .= "<td><input type=\"number\" name=\"RaceSeriesEntry_" . $rse['Id'] . "_Ballast\" value=\"" . $rse['Ballast'] . "\" $readonly></td>";

                // users
                $html .= "<td><select name=\"RaceSeriesEntry_" . $rse['Id'] . "_User\" $readonly $disabled>";
                $html .= "<option value=\"0\"></option>";
                foreach ($acswuiDatabase->fetch_2d_array("Users", ['Id', 'Login'], [], [], 'Login') as $user) {
                    $selected = ($rse['User'] == $user['Id']) ? "selected":"";
                    $html .= "<option value=\"" . $user['Id'] . "\" $selected>" . $user['Login'] . "</option>";
                }
                $html .= "</select></td>";

                $html .= "<td>";
                // delete button
                if ($acswuiUser->hasPermission($this->EditPermission)) {
                    $html .= "<button type=\"submit\" name=\"DELETE_ENTRY\" value=\"" . $rse['Id'] . "\">" . _("delete") . "</button>";
                }
                // occupation
                if ($this->entry_occupation_possible($raceseries_id, $rse['Id'])) {
                    $html .= "<button type=\"submit\" name=\"OCCUPY_ENTRY\" value=\"" . $rse['Id'] . "\">" . _("Occupy") . "</button>";
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
            if ($this->car_occupation_possible($raceseries_id, $rsc['Id'])) {
                $html .= "<button type=\"submit\" name=\"OCCUPY_CAR\" value=\"" . $rsc['Id'] . "\">" . _("Occupy This Car") . "</button>";
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
        $car_query = $acswuiDatabase->fetch_2d_array("RaceSeries", ['AllowOccupation'], ['Id'], [$race_series]);
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
        foreach ($acswuiDatabase->fetch_2d_array("RaceSeriesEntries", ['User'], ['RaceSeriesCar'], [$car]) as $rse) {
            if ($rse['User'] == $acswuiUser->Id) return False;
            if ($rse['User'] == 0) return False;
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
        $rse = $acswuiDatabase->fetch_2d_array("RaceSeriesEntries", ['RaceSeriesCar', 'User'], ['Id'], [$entry]);
        if ($rse[0]['User'] > 0) return False;

        // check entries of same race series car
        foreach ($acswuiDatabase->fetch_2d_array("RaceSeriesEntries", ['User'], ['RaceSeriesCar'], [$rse[0]['RaceSeriesCar']]) as $rse) {
            if ($rse['User'] == $acswuiUser->Id) return False;
        }

        return True;
    }
}

?>

