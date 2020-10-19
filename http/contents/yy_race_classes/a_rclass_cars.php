<?php

class a_rclass_cars extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Classes");
        $this->TextDomain = "acswui";
        $this->PageTitle  = _("Race Class Car Management");
        $this->RequirePermissions = ["View_ServerContent"];
        $this->EditPermission     = 'RaceClass_Edit';
    }

    public function getHtml() {

        // access global data
        global $acswuiDatabase;
        global $acswuiUser;
        global $acswuiLog;

        // reused variables
        $raceclass_id = 0;
        $raceclass_name = "";
        $raceclass_allow_occupation = False;
        $raceclass_auto_fill = False;
        $raceclass_cars = array(); // a list of RaceSeriesCars[Car] that are in the race sieres



        // --------------------------------------------------------------------
        //                        Determine Requested Class
        // --------------------------------------------------------------------

        // get request data
        if (isset($_REQUEST['RACECLASS_ID'])) {
            $raceclass_id = $_REQUEST['RACECLASS_ID'];
        } else if (isset($_SESSION['RACECLASS_ID'])) {
            $raceclass_id = $_SESSION['RACECLASS_ID'];
        }

        // check requested race class
        if ($raceclass_id !== 'NEW_RACE_CLASS') {
            $raceclass_id_exists = False;
            $raceclass_id_first  = Null;
            foreach ($acswuiDatabase->fetch_2d_array("RaceClasses", ['Id', 'Name', 'AllowOccupation', 'AutoFillEntries'], [], []) as $rs) {
                // check if $raceclass_id exists
                if ($rs['Id'] === $raceclass_id) {
                    $raceclass_id_exists = True;
                    $raceclass_name = $rs['Name'];
                    $raceclass_allow_occupation = $rs['AllowOccupation'];
                    $raceclass_auto_fill = $rs['AutoFillEntries'];
                }
                // save first existing Id
                if (is_null($raceclass_id_first)) {
                    $raceclass_id_first = $rs['Id'];
                }
            }

            // set race class if request is invalid
            if ($raceclass_id_exists !== True)
                $raceclass_id = $raceclass_id_first;

            // save last requested race class
            $_SESSION['RACECLASS_ID'] = $raceclass_id;
        }



        // --------------------------------------------------------------------
        //                             SAVE ACTION
        // --------------------------------------------------------------------

        if ($acswuiUser->hasPermission($this->EditPermission) && isset($_REQUEST['ACTION']) && $_REQUEST['ACTION']=="SAVE") {

            // add new race series
            if ($raceclass_id === "NEW_RACE_CLASS" && isset($_POST['RACECLASSNAME'])) {
                $raceclass_id = $acswuiDatabase->insert_row("RaceClasses", ["Name" => $_POST['RACECLASSNAME']]);
                $_SESSION['RACECLASS_ID'] = $raceclass_id;
            }

            // retrieve general settings
            $raceclass_name = $_POST['RACECLASSNAME'];
            $raceclass_allow_occupation = (isset($_POST["ALLOWOCUPATION"]) && $_POST["ALLOWOCUPATION"] === "TRUE") ? True : False;
            $raceclass_auto_fill = (isset($_POST["AUTOFILLENTRIES"]) && $_POST["AUTOFILLENTRIES"] === "TRUE") ? True : False;

            // save general settings
            $general_settings = array();
            $general_settings['Name'] = $raceclass_name;
            $general_settings['AllowOccupation'] = ($raceclass_allow_occupation) ? 1:0;
            $general_settings['AutoFillEntries'] = ($raceclass_auto_fill) ? 1:0;
            $acswuiDatabase->update_row("RaceClasses", $raceclass_id, $general_settings);

            // remove race class car
            if (isset($_REQUEST['DELETE_CAR'])) {
                $del_id = $_REQUEST['DELETE_CAR'];

                // delete RaceClassEntries
                foreach ($acswuiDatabase->fetch_2d_array("RaceClassEntries", ['Id'], ['RaceClassCar'], [$del_id]) as $rse) {
                    $acswuiDatabase->delete_row("RaceClassEntries", $rse['Id']);
                }

                // delete race class car
                $acswuiDatabase->delete_row("RaceSeriesCars", $del_id);
            }

            // update race class car
            foreach($acswuiDatabase->fetch_2d_array("RaceClassCars", ['Id'], ['RaceClass'], [$raceclass_id]) as $rsc) {
                // overwrite with new values
                $rsc_id = $rsc['Id'];
                $field_list = array();
                if (isset($_REQUEST["Ballast_$rsc_id"])) $field_list["Ballast"]             =  $_REQUEST["Ballast_$rsc_id"];
                if (isset($_REQUEST["Count_$rsc_id"]))   $field_list["MinCount"]            =  $_REQUEST["Count_$rsc_id"];

                // update database
                if (count($field_list)) $acswuiDatabase->update_row("RaceClassCars", $rsc_id, $field_list);


            }

            // add new cars
            foreach($acswuiDatabase->fetch_2d_array("Cars", ["Id"]) as $c) {
                if (isset($_POST['ADD_CAR_' . $c['Id']])) {
                    $acswuiDatabase->insert_row("RaceClassCars", ['RaceClass' => $raceclass_id, 'Car' => $c['Id']]);
                }
            }

        }



        // --------------------------------------------------------------------
        //                     Intialize Html Output
        // --------------------------------------------------------------------

        $html  = '';



        // --------------------------------------------------------------------
        //                     Race Class Selection
        // --------------------------------------------------------------------

        $html .= "<form action=\"\" method=\"post\">";
        $html .= "<select name=\"RACECLASS_ID\" onchange=\"this.form.submit()\">";

        # list existing classes
        $raceclasses = $acswuiDatabase->fetch_2d_array("RaceClasses", ['Id', "Name"], [], [], "Name");
        foreach ($raceclasses as $rs) {
            $selected = ($rs['Id'] == $raceclass_id) ? "selected" : "";
            $html .= "<option value=\"" . $rs['Id'] . "\" $selected>" . $rs['Name'] ."</option>";
        }

        # add new class
        if ($acswuiUser->hasPermission($this->EditPermission)) {
            # insert empty select if no classes availale
            # workaround for creating a new class when no class is available
            if (count($raceclasses) == 0)
                $html .= "<option value=\"\" selected></option>";

            # create new
            $selected = ($raceclass_id == 'NEW_RACE_CLASS') ? "selected" : "";
            $html .= "<option value=\"NEW_RACE_CLASS\" $selected>&lt;" . _("Create New Race Class") . "&gt;</option>";
        }

        $html .= "</select>";
        $html .= "</form>";



        // --------------------------------------------------------------------
        //                        General Class Setup
        // --------------------------------------------------------------------

        $html .= "<form action=\"?ACTION=SAVE\" method=\"post\">";
        $html .= "<input type=\"hidden\" name=\"RACECLASS_ID\" value=\"$raceclass_id\">";

        $html .= "<h1>" . _("General Race Class Setup") . "</h1>";

        # class name
        $permitted = $acswuiUser->hasPermission($this->EditPermission);
        $html .= "Name: <input type=\"text\" name=\"RACECLASSNAME\" value=\"$raceclass_name\" " . (($permitted) ? "" : "readonly") . "/>";

        # allow occupation
        $disabled = ($acswuiUser->hasPermission($this->EditPermission)) ? "" : "disabled readonly";
        $checked = ($raceclass_allow_occupation) ? "checked" : "";
        $html .= "Allow Occupation: <input type=\"checkbox\" name =\"ALLOWOCUPATION\" value=\"TRUE\" $checked $disabled>";

        # auto fill
        $disabled = ($acswuiUser->hasPermission($this->EditPermission)) ? "" : "disabled readonly";
        $checked = ($raceclass_auto_fill) ? "checked" : "";
        $html .= "Auto Fill Entries: <input type=\"checkbox\" name =\"AUTOFILLENTRIES\" value=\"TRUE\" $checked $disabled>";



        // --------------------------------------------------------------------
        //                           Available Cars
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Available Cars") . "</h1>";

        // race class cars
        $html .= "<table id=\"available_cars\">";
        $html .= "<tr>";
        $html .= "<th>". _("Car") ."</th>";
        $html .= "<th>" . _("Min Count") ."</th>";
        $html .= "<th>" . _("Ballast [Kg]") ."</th>";
        $html .= "</tr>";

        foreach ($acswuiDatabase->fetch_2d_array("RaceClassCars", ['Id', "Car", 'Ballast', 'MinCount'], ['RaceClass'], [$raceclass_id]) as $rsc)  {
            // remeber existing car
            $raceclass_cars[] = $rsc['Car'];

            // get car infos
            $cars  = $acswuiDatabase->fetch_2d_array("Cars", ['Id', 'Car', "Name", 'Brand'], ['Id'], [$rsc['Car']]);
            $car   = $cars[0];
            $skins = $acswuiDatabase->fetch_2d_array("CarSkins", ['Id'], ['Car'], [$rsc['Car']]);
            $skin  = $skins[0];

            // check edit permissions
            $readonly = ($acswuiUser->hasPermission($this->EditPermission)) ? "":"readonly";
            $disabled = ($acswuiUser->hasPermission($this->EditPermission)) ? "":"disabled";

            // get values
            $count   = $rsc['MinCount'];
            $ballast = $rsc['Ballast'];

            // output row
            $rsc_id = $rsc['Id'];
            $html .= "<tr>";
            $html .= "<td>" . $car['Name'] . getImgCarSkin($skin['Id'], $car['Car']) . "</td>";
            $html .= "<td><input type=\"number\"   name=\"Count_$rsc_id\"               value=\"$count\"     $readonly></td>";
            $html .= "<td><input type=\"number\"   name=\"Ballast_$rsc_id\"             value=\"$ballast\"   $readonly></td>";
            if ($acswuiUser->hasPermission($this->EditPermission)) {
                $html .= "<td><button type=\"submit\"  name=\"DELETE_CAR\"                  value=\"$rsc_id\"             >" . _("delete") . "</button></td>";
            }
            $html .= "</tr>";
        }


        if ($acswuiUser->hasPermission($this->EditPermission)) {
            $html .= "<tr><td colspan=\"5\"><button type=\"submit\">" . _("Save Race Class") . "</button></td></tr>";
        }

        $html .= '</table>';


        // --------------------------------------------------------------------
        //                           Car Add Form
        // --------------------------------------------------------------------

        if ($acswuiUser->hasPermission($this->EditPermission)) {

            $html .= "<h1>" . _("Add New Cars") . "</h1>";
            $html .= _("Select below all cars that shall be added to the race class.");

            // find all brands
            $brands = array();
            foreach ($acswuiDatabase->fetch_2d_array("Cars", ["Brand"], [], [], "Brand") as $b) {
                if (!in_array($b['Brand'], $brands)) $brands[count($brands)] = $b['Brand'];
            }

            // view cars of each brand
            foreach ($brands as $b) {
                // separate heading for each brand
                $html .= "<h2>$b</h2>";

                // scan all cars of a brand
                foreach($acswuiDatabase->fetch_2d_array("Cars", ["Id", "Car", "Brand", "Name"], ['Brand'], [$b], "Name") as $c) {

                    // get skins
                    $skins = $acswuiDatabase->fetch_2d_array("CarSkins", ['Id', 'Skin'], ['Car'], [$c['Id']]);

                    // view car
                    $html .= '<div class="car_add_option">';
                    if (in_array($c['Id'], $raceclass_cars)) {
                        $html .= "<input type=\"checkbox\" id=\"car_add_option_id_" . $c['Id'] . "\" checked disabled></input>";
                    } else {
                        $html .= "<input type=\"checkbox\" id=\"car_add_option_id_" . $c['Id'] . "\" name=\"ADD_CAR_" . $c['Id'] . "\" value=\"TRUE\"></input>";
                    }
                    $html .= "<label for=\"car_add_option_id_" . $c['Id'] . "\">";
                    $html .= $c["Name"];
                    if (count($skins) > 0) $html .= getImgCarSkin($skins[0]['Id']);
                    $html .= "</label></div>";
                }
            }
        }



        if ($acswuiUser->hasPermission($this->EditPermission)) {
            $html .= "<br><br><button type=\"submit\">" . _("Save Race Class") . "</button>";
        }

        $html .= '</form>';

        return $html;
    }
}

?>
