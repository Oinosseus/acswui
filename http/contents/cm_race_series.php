<?php

class cm_race_series extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Race Series");
        $this->PageTitle  = _("Race Series");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_ServerContent"];
        $this->EditPermission     = 'RaceSeries_Edit';
    }

    public function getHtml() {

        // access global data
        global $acswuiDatabase;
        global $acswuiUser;
        global $acswuiLog;

        // local vars
        $raceseries_current_id = Null;
        $raceseries_current_name = "";
        $raceseries_add_new = False;
        $html = "";



        // --------------------------------------------------------------------
        //                     Process POST Data
        // --------------------------------------------------------------------

        # get requested ID
        if (isset($_POST['RACESERIES_ID'])) {
            if ($_POST['RACESERIES_ID'] === "RACESERIES_NEW" && $acswuiUser->hasPermission($this->EditPermission)) {
                $raceseries_add_new = True;
                $raceseries_current_id = Null;
            } else {
                $raceseries_add_new = False;
                $raceseries_current_id = (int) $_POST['RACESERIES_ID'];
            }
        } else {
            // find first available ID
            $res = $acswuiDatabase->fetch_2d_array("RaceSeries", ["Id"]);
            if (count($res))
                $raceseries_current_id = $res[0]['Id'];
        }

        // get new name
        $raceseries_current_name  = "";
        if ($acswuiUser->hasPermission($this->EditPermission) && isset($_POST['RACESERIES_NAME'])) {
            $raceseries_current_name = $_POST['RACESERIES_NAME'];
        } else if ($raceseries_current_id >= 0) {
            $res = $acswuiDatabase->fetch_2d_array("RaceSeries", ["Name"], ['Id' => $raceseries_current_id]);
            if (count($res))
                $raceseries_current_name = $res[0]['Name'];
        }

        // save data
        if ($acswuiUser->hasPermission($this->EditPermission) && isset($_POST['ACTION']) && $_POST['ACTION'] == "SAVE") {

            // create new race series
            if ($raceseries_add_new) {
                $raceseries_current_id = $acswuiDatabase->insert_row("RaceSeries", ["Name" => $raceseries_current_name]);
                $raceseries_add_new = False;
            // save new name
            } else if ($raceseries_current_id >= 0) {
                $acswuiDatabase->update_row("RaceSeries", $raceseries_current_id, ['Name' => $raceseries_current_name]);
            }

            # save included car classes
            foreach ($acswuiDatabase->fetch_2d_array("CarClasses", ['Id']) as $cc)  {
                $cc_id = $cc['Id'];
                if (isset($_POST["CARCLASS_$cc_id"]) && $_POST["CARCLASS_$cc_id"] == "TRUE") {
                    $acswuiDatabase->map("RaceSeriesMap", ["RaceSeries" => $raceseries_current_id, "CarClass" => $cc_id]);
                } else {
                    $acswuiDatabase->unmap("RaceSeriesMap", ["RaceSeries" => $raceseries_current_id, "CarClass" => $cc_id]);
                }
            }

        }



        // --------------------------------------------------------------------
        //                     RaceSeries Selection
        // --------------------------------------------------------------------

        $html .= "<form action=\"\" method=\"post\">";
        $html .= "<input type=\"hidden\" name=\"ACTION\" value=\"\">";
        $html .= "<select name=\"RACESERIES_ID\" onchange=\"this.form.submit()\">";

        # list existing race series
        $racesries_available = $acswuiDatabase->fetch_2d_array("RaceSeries", ['Id', "Name"], [], "Name");
        foreach ($racesries_available as $rs) {
            $selected = ($rs['Id'] == $raceseries_current_id) ? "selected" : "";
            $html .= "<option value=\"" . $rs['Id'] . "\" $selected>" . $rs['Name'] ."</option>";
        }

        # add new
        if ($acswuiUser->hasPermission($this->EditPermission)) {
            # insert empty select if no classes availale
            # workaround for creating a new class when no class is available
            if (count($racesries_available) == 0)
                $html .= "<option value=\"\" selected></option>";

            # create new
            $selected = ($raceseries_add_new) ? "selected" : "";
            $html .= "<option value=\"RACESERIES_NEW\" $selected>&lt;" . _("Create New Race Series") . "&gt;</option>";
        }

        $html .= "</select>";
        $html .= "</form>";



        // --------------------------------------------------------------------
        //                        General Information
        // --------------------------------------------------------------------

        $html .= "<form action=\"\" method=\"post\">";
        $html .= "<input type=\"hidden\" name=\"ACTION\" value=\"SAVE\">";
        if ($raceseries_add_new) {
            $html .= "<input type=\"hidden\" name=\"RACESERIES_ID\" value=\"RACESERIES_NEW\">";
        } else {
            $html .= "<input type=\"hidden\" name=\"RACESERIES_ID\" value=\"$raceseries_current_id\">";
        }

        $html .= "<h1>" . _("General Information") . "</h1>";

        # class name
        $permitted = $acswuiUser->hasPermission($this->EditPermission);
        $html .= "Name: <input type=\"text\" name=\"RACESERIES_NAME\" value=\"$raceseries_current_name\" " . (($permitted) ? "" : "readonly") . "/>";



        // --------------------------------------------------------------------
        //                      Included Car Classes
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Included Car Classes") . "</h1>";


        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<th>". _("Car Class") ."</th>";
        $html .= "<th>". _("Is Included") ."</th>";
        $html .= "</tr>";

        $disabled = ($acswuiUser->hasPermission($this->EditPermission)) ? "":"disabled";
        foreach ($acswuiDatabase->fetch_2d_array("CarClasses", ['Id', "Name"]) as $cc)  {
            $cc_name = $cc['Name'];
            $cc_id = $cc['Id'];
            $checked = ($acswuiDatabase->mapped("RaceSeriesMap", ["RaceSeries" => $raceseries_current_id, "CarClass" => $cc_id])) ? "checked" : "";

            $html .= "<tr>";
            $html .= "<td>$cc_name</td>";
            $html .= "<td><input type=\"checkbox\" name=\"CARCLASS_$cc_id\" value=\"TRUE\" $disabled $checked></td>";
            $html .= "</tr>";
        }

        if ($acswuiUser->hasPermission($this->EditPermission)) {
            $html .= "<tr><td colspan=\"2\"><button type=\"submit\">" . _("Save Race Series") . "</button></td></tr>";
        }

        $html .= "</form>";

        return $html;
    }
}

?>
