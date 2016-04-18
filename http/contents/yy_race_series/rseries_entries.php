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

//             // remove race series car
//             if (isset($_REQUEST['DELETE_CAR'])) {
//                 $del_id = $_REQUEST['DELETE_CAR'];
//
//                 // delete RaceSeriesEntries
//                 foreach ($acswuiDatabase->fetch_2d_array("RaceSeriesEntries", ['Id'], ['RaceSeriesCar'], [$del_id]) as $rse) {
//                     $acswuiDatabase->delete_row("RaceSeriesEntries", $rse['Id']);
//                 }
//
//                 // delete race series car
//                 $acswuiDatabase->delete_row("RaceSeriesCars", $del_id);
//             }

//             // update race series car
//             foreach($acswuiDatabase->fetch_2d_array("RaceSeriesCars", ['Id'], ['RaceSeries'], [$raceseries_id]) as $rsc) {
//                 // overwrite with new values
//                 $rsc_id = $rsc['Id'];
//                 $field_list = array();
//                 if (isset($_REQUEST["AllowUserOccupation_$rsc_id"])) $field_list["AllowUserOccupation"] = 1;
//                 if (isset($_REQUEST["GridAutoFill_$rsc_id"]))        $field_list["GridAutoFill"]        = 1;
//                 if (isset($_REQUEST["Ballast_$rsc_id"]))             $field_list["Ballast"]             =  $_REQUEST["Ballast_$rsc_id"];
//                 if (isset($_REQUEST["Count_$rsc_id"]))               $field_list["Count"]               =  $_REQUEST["Count_$rsc_id"];
//
//                 // update database
//                 if (count($field_list)) $acswuiDatabase->update_row("RaceSeriesCars", $rsc_id, $field_list);
//
//
//             }

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

        foreach ($acswuiDatabase->fetch_2d_array("RaceSeriesCars", ['Id', "Car", 'Ballast', 'Count', 'AllowUserOccupation', 'GridAutoFill'], ['RaceSeries'], [$raceseries_id]) as $rsc)  {


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
            $html .= "<th>" . _("Allow User Occupation") ."</th>";
            $html .= "<th>" . _("Driver") ."</th>";
            $html .= "</tr>";

            // request entries
            foreach ($acswuiDatabase->fetch_2d_array("RaceSeriesEntries", ['Id', "CarSkin", 'Ballast', 'AllowUserOccupation', 'User'], ['RaceSeriesCar'], [$rsc['Id']]) as $rse)  {

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
                $html .= "<td><input type=\"checkbox\" name=\"RaceSeriesEntry_" . $rse['Id'] . "_AllowUserOccupation\" value=\"TRUE\" $disabled" . (($rse['AllowUserOccupation']) ? "checked":"") . "></td>";

                // users
                $html .= "<td><select name=\"RaceSeriesEntry_" . $rse['Id'] . "_User\" $readonly $disabled>";
                $html .= "<option value=\"0\"></option>";
                foreach ($acswuiDatabase->fetch_2d_array("Users", ['Id', 'Login'], [], [], 'Login') as $user) {
                    $selected = ($rse['User'] == $user['Id']) ? "selected":"";
                    $html .= "<option value=\"" . $user['Id'] . "\" $selected>" . $user['Login'] . "</option>";
                }
                $html .= "</select></td>";

                // delete button
                if ($acswuiUser->hasPermission($this->EditPermission)) {
                    $html .= "<td>";
                    $html .= "<button type=\"submit\" name=\"DELETE_ENTRY\" value=\"" . $rse['Id'] . "\">" . _("delete") . "</button>";
                    $html .= "</td>";
                }

                $html .= "</tr>";
            }

            // new car entry
            $html .= "<tr><td colspan=\"5\">";
            if ($acswuiUser->hasPermission($this->EditPermission)) {
                $html .= "<button type=\"submit\" name=\"ADD_NEW_ENTRY\" value=\"TRUE\">" . _("New Entry") . "</button>";
                $html .= "<button type=\"submit\" name=\"SAVE_ENTRIES\" value=\"TRUE\">" . _("Save Car Entries") . "</button>";
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
}

?>

