<?php

class bm_car_classes extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Car Classes");
        $this->TextDomain = "acswui";
        $this->PageTitle  = _("Car Classes");
        $this->RequirePermissions = ["View_ServerContent"];
        $this->EditPermission     = 'CarClass_Edit';
    }

    public function getHtml() {

        // access global data
        global $acswuiDatabase;
        global $acswuiUser;
        global $acswuiLog;

        // reused variables
        $html  = '';
        $carclass_id = 0;
        $carclass_name = "";
        $carclass_cars = array(); // a list of cars that are in the car class



        // --------------------------------------------------------------------
        //                        Determine Requested Class
        // --------------------------------------------------------------------

        // get requested class
        if (isset($_REQUEST['CARCLASS_ID'])) {
            $carclass_id = $_REQUEST['CARCLASS_ID'];
        } else if (isset($_SESSION['CARCLASS_ID'])) {
            $carclass_id = $_SESSION['CARCLASS_ID'];
        }

        // check requested car class
        if ($carclass_id !== 'NEW_CARCLASS') {
            $carclass_id_exists = False;
            $carclass_id_first  = Null;
            foreach ($acswuiDatabase->fetch_2d_array("CarClasses", ['Id', 'Name'], []) as $rs) {
                // check if $carclass_id exists
                if ($rs['Id'] === $carclass_id) {
                    $carclass_id_exists = True;
                    $carclass_name = $rs['Name'];
                }
                // save first existing Id
                if (is_null($carclass_id_first)) {
                    $carclass_id_first = $rs['Id'];
                }
            }

            // set race class if request is invalid
            if ($carclass_id_exists !== True)
                $carclass_id = $carclass_id_first;

            // save last requested race class
            $_SESSION['CARCLASS_ID'] = $carclass_id;
        }



        // --------------------------------------------------------------------
        //                             SAVE ACTION
        // --------------------------------------------------------------------

        if ($acswuiUser->hasPermission($this->EditPermission) && isset($_REQUEST['ACTION']) && $_REQUEST['ACTION']=="SAVE") {

            // create new car class
            if ($carclass_id === "NEW_CARCLASS" && isset($_POST['CARCLASS_NAME'])) {
                $carclass_id = $acswuiDatabase->insert_row("CarClasses", ["Name" => $_POST['CARCLASS_NAME']]);
                $_SESSION['CARCLASS_ID'] = $carclass_id;
            }

            // save car class
            $carclass_name = $_POST['CARCLASS_NAME'];
            $acswuiDatabase->update_row("CarClasses", $carclass_id, ['Name' => $carclass_name]);

            // remove race class car
            if (isset($_REQUEST['DELETE_CAR'])) {
                $del_id = $_REQUEST['DELETE_CAR'];

                // delete race class car
                $acswuiDatabase->delete_row("CarClassesMap", $del_id);
            }

            // update carclass map
            foreach($acswuiDatabase->fetch_2d_array("CarClassesMap", ['Id'], ['CarClass' => $carclass_id]) as $ccm) {

                // overwrite with new values
                $id = $ccm['Id'];
                $field_list = array();
                if (isset($_POST["BALLAST_$id"])) $field_list["Ballast"] = $_POST["BALLAST_$id"];

                // update database
                if (count($field_list)) $acswuiDatabase->update_row("CarClassesMap", $id, $field_list);
            }

            // map new cars
            foreach($acswuiDatabase->fetch_2d_array("Cars", ["Id"]) as $c) {
                if (isset($_POST['ADD_CAR_' . $c['Id']])) {
                    $acswuiDatabase->insert_row("CarClassesMap", ['CarClass' => $carclass_id, 'Car' => $c['Id']]);
                }
            }
        }



        // --------------------------------------------------------------------
        //                     Car Class Selection
        // --------------------------------------------------------------------

        $html .= "<form action=\"?ACTION=\" method=\"post\">";
        $html .= "<select name=\"CARCLASS_ID\" onchange=\"this.form.submit()\">";

        # list existing classes
        $carclasses = $acswuiDatabase->fetch_2d_array("CarClasses", ['Id', "Name"], [], "Name");
        foreach ($carclasses as $cc) {
            $selected = ($cc['Id'] == $carclass_id) ? "selected" : "";
            $html .= "<option value=\"" . $cc['Id'] . "\" $selected>" . $cc['Name'] ."</option>";
        }

        # add new class
        if ($acswuiUser->hasPermission($this->EditPermission)) {
            # insert empty select if no classes availale
            # workaround for creating a new class when no class is available
            if (count($carclasses) == 0)
                $html .= "<option value=\"\" selected></option>";

            # create new
            $selected = ($carclass_id == 'NEW_CARCLASS') ? "selected" : "";
            $html .= "<option value=\"NEW_CARCLASS\" $selected>&lt;" . _("Create New Car Class") . "&gt;</option>";
        }

        $html .= "</select>";
        $html .= "</form>";



        // --------------------------------------------------------------------
        //                        General Class Setup
        // --------------------------------------------------------------------

        $html .= "<form action=\"?ACTION=SAVE\" method=\"post\">";
        $html .= "<input type=\"hidden\" name=\"CARCLASS_ID\" value=\"$carclass_id\">";

        $html .= "<h1>" . _("General Car Class Setup") . "</h1>";

        # class name
        $permitted = $acswuiUser->hasPermission($this->EditPermission);
        $html .= "Name: <input type=\"text\" name=\"CARCLASS_NAME\" value=\"$carclass_name\" " . (($permitted) ? "" : "readonly") . "/>";



        // --------------------------------------------------------------------
        //                           Available Cars
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Cars Assinged To Class") . "</h1>";

        // race class cars
        $html .= "<table id=\"available_cars\">";
        $html .= "<tr>";
        $html .= "<th>". _("Car") ."</th>";
        $html .= "<th>". _("Ballast") ."</th>";
        $html .= "</tr>";

        foreach ($acswuiDatabase->fetch_2d_array("CarClassesMap", ['Id', "Car", 'Ballast'], ['CarClass' => $carclass_id]) as $ccm)  {
            // remeber existing car
            $carclass_cars[] = $ccm['Car'];

            // get car infos
            $cars  = $acswuiDatabase->fetch_2d_array("Cars", ['Id', 'Car', "Name", 'Brand'], ['Id' => $ccm['Car']]);
            $car   = $cars[0];
            $skins = $acswuiDatabase->fetch_2d_array("CarSkins", ['Id'], ['Car' => $ccm['Car']]);
            $skin  = $skins[0];

            // check edit permissions
            $readonly = ($acswuiUser->hasPermission($this->EditPermission)) ? "":"readonly";
            $disabled = ($acswuiUser->hasPermission($this->EditPermission)) ? "":"disabled";

            // get values
            $ballast = $ccm['Ballast'];

            // output row
            $rsc_id = $ccm['Id'];
            $html .= "<tr>";
            $html .= "<td>" . $car['Name'] . getImgCarSkin($skin['Id'], $car['Car']) . "</td>";
            $html .= "<td><input type=\"number\"   name=\"BALLAST_$rsc_id\" value=\"$ballast\" $readonly></td>";
            if ($acswuiUser->hasPermission($this->EditPermission)) {
                $html .= "<td><button type=\"submit\"  name=\"DELETE_CAR\" value=\"$rsc_id\" >" . _("delete") . "</button></td>";
            }
            $html .= "</tr>";
        }


        if ($acswuiUser->hasPermission($this->EditPermission)) {
            $html .= "<tr><td colspan=\"5\"><button type=\"submit\">" . _("Save Car Class") . "</button></td></tr>";
        }

        $html .= '</table>';


        // --------------------------------------------------------------------
        //                           Car Add Form
        // --------------------------------------------------------------------

        if ($acswuiUser->hasPermission($this->EditPermission)) {

            $html .= "<h1>" . _("Add New Cars") . "</h1>";
            $html .= _("Select below all cars that shall be added to the car class.");

            // find all brands
            $brands = array();
            foreach ($acswuiDatabase->fetch_2d_array("Cars", ["Brand"], [], "Brand") as $b) {
                if (!in_array($b['Brand'], $brands)) $brands[count($brands)] = $b['Brand'];
            }

            // view cars of each brand
            foreach ($brands as $b) {
                // separate heading for each brand
                $html .= "<h2>$b</h2>";

                // scan all cars of a brand
                foreach($acswuiDatabase->fetch_2d_array("Cars", ["Id", "Car", "Brand", "Name"], ['Brand' => $b], "Name") as $c) {

                    // get skins
                    $skins = $acswuiDatabase->fetch_2d_array("CarSkins", ['Id', 'Skin'], ['Car' => $c['Id']]);

                    // view car
                    $html .= '<div class="car_add_option">';
                    if (in_array($c['Id'], $carclass_cars)) {
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
            $html .= "<br><br><button type=\"submit\">" . _("Save Car Class") . "</button>";
        }

        $html .= '</form>';

        return $html;
    }
}

?>
