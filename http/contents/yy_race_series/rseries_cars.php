<?php

class rseries_cars extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Race Series Cars");
        $this->TextDomain = "acswui";
        $this->PageTitle  = _("Race Series / Race Grid Management");
        $this->RequirePermissions = ["View_ServerContent"];
        $this->EditPermission     = 'Edit_RaceSeries_Cars';
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

        if ($acswuiUser->hasPermission($this->EditPermission) && isset($_REQUEST['ACTION']) && $_REQUEST['ACTION']=="SAVE") {

            // remove race series car
            if (isset($_REQUEST['DELETE_CAR'])) {
                $del_id = $_REQUEST['DELETE_CAR'];

                // delete RaceSeriesEntries
                foreach ($acswuiDatabase->fetch_2d_array("RaceSeriesEntries", ['Id'], ['RaceSeriesCar'], [$del_id]) as $rse) {
                    $acswuiDatabase->delete_row("RaceSeriesEntries", $rse['Id']);
                }

                // delete race series car
                $acswuiDatabase->delete_row("RaceSeriesCars", $del_id);
            }

            // update race series car
            foreach($acswuiDatabase->fetch_2d_array("RaceSeriesCars", ['Id'], ['RaceSeries'], [$raceseries_id]) as $rsc) {
                // overwrite with new values
                $rsc_id = $rsc['Id'];
                $field_list = array();
                if (isset($_REQUEST["AllowUserOccupation_$rsc_id"])) $field_list["AllowUserOccupation"] = 1;
                if (isset($_REQUEST["GridAutoFill_$rsc_id"]))        $field_list["GridAutoFill"]        = 1;
                if (isset($_REQUEST["Ballast_$rsc_id"]))             $field_list["Ballast"]             =  $_REQUEST["Ballast_$rsc_id"];
                if (isset($_REQUEST["Count_$rsc_id"]))               $field_list["Count"]               =  $_REQUEST["Count_$rsc_id"];

                // update database
                if (count($field_list)) $acswuiDatabase->update_row("RaceSeriesCars", $rsc_id, $field_list);


            }

            // add new cars
            foreach($acswuiDatabase->fetch_2d_array("Cars", ["Id"]) as $c) {
                if (isset($_POST['ADD_CAR_' . $c['Id']])) {
                    $acswuiDatabase->insert_row("RaceSeriesCars", ['RaceSeries' => $raceseries_id, 'Car' => $c['Id']]);
                }
            }
        }



        // --------------------------------------------------------------------
        //                     Intialize Html Output
        // --------------------------------------------------------------------

        $html  = '';



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

        $html .= "<h1>" . _("Available Cars") . "</h1>";

        // race series cars
        $html .= "<form action=\"?ACTION=SAVE\" method=\"post\">";
        $html .= "<table id=\"available_cars\">";
        $html .= "<tr>";
        $html .= "<th>". _("Car") ."</th>";
        $html .= "<th>" . _("Allow User Occupation") ."</th>";
        $html .= "<th>" . _("Automatically Fill Grid") ."</th>";
        $html .= "<th>" . _("Fill Count") ."</th>";
        $html .= "<th>" . _("Ballast [Kg]") ."</th>";
        $html .= "</tr>";

        foreach ($acswuiDatabase->fetch_2d_array("RaceSeriesCars", ['Id', "Car", 'Ballast', 'Count', 'AllowUserOccupation', 'GridAutoFill'], ['RaceSeries'], [$raceseries_id]) as $rsc)  {
            // remeber existing car
            $raceseries_cars[] = $rsc['Car'];

            // get car infos
            $cars  = $acswuiDatabase->fetch_2d_array("Cars", ['Id', 'Car', "Name", 'Brand'], ['Id'], [$rsc['Car']]);
            $car   = $cars[0];
            $skins = $acswuiDatabase->fetch_2d_array("CarSkins", ['Id'], ['Car'], [$rsc['Car']]);
            $skin  = $skins[0];

            // check edit permissions
            $readonly = ($acswuiUser->hasPermission($this->EditPermission)) ? "":"readonly";
            $disabled = ($acswuiUser->hasPermission($this->EditPermission)) ? "":"disabled";

            // get values
            $occu    = ($rsc['AllowUserOccupation'] == 1) ? "checked" : "";
            $fill    = ($rsc['GridAutoFill'] == 1) ? "checked" : "";
            $count   = $rsc['Count'];
            $ballast = $rsc['Ballast'];

            // output row
            $rsc_id = $rsc['Id'];
            $html .= "<tr>";
            $html .= "<td>" . $car['Name'] . getImgCarSkin($skin['Id'], $car['Car']) . "</td>";
            $html .= "<td><input type=\"checkbox\" name=\"AllowUserOccupation_$rsc_id\" value=\"TRUE\" $occu $disabled></td>";
            $html .= "<td><input type=\"checkbox\" name=\"GridAutoFill_$rsc_id\"        value=\"TRUE\" $fill $disabled></td>";
            $html .= "<td><input type=\"number\"   name=\"Count_$rsc_id\"               value=\"$count\"     $readonly></td>";
            $html .= "<td><input type=\"number\"   name=\"Ballast_$rsc_id\"             value=\"$ballast\"   $readonly></td>";
            $html .= "<td><button type=\"submit\"  name=\"DELETE_CAR\"                  value=\"$rsc_id\"             >" . _("delete") . "</button></td>";
            $html .= "</tr>";
        }


        if ($acswuiUser->hasPermission($this->EditPermission)) {
            $html .= "<tr><td colspan=\"5\"><button type=\"submit\">" . _("Save Race Series") . "</button></td></tr>";
        }

        $html .= '</table>';


        // --------------------------------------------------------------------
        //                           Car Add Form
        // --------------------------------------------------------------------

        if ($acswuiUser->hasPermission($this->EditPermission)) {

            $html .= "<h1>" . _("Add New Cars") . "</h1>";

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
                    if (in_array($c['Id'], $raceseries_cars)) {
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
            $html .= "<br><br><button type=\"submit\">" . _("Save Race Series") . "</button>";
        }

        $html .= '</form>';

        return $html;
    }
}

?>
