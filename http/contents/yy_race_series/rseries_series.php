<?php

class rseries_series extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Race Series");
        $this->TextDomain = "acswui";
        $this->PageTitle  = _("Race Series Management");
        $this->RequirePermissions = ["View_ServerContent"];
        $this->EditPermission     = 'Edit_RaceSeries_Series';
    }

    public function getHtml() {

        // access global data
        global $acswuiDatabase;
        global $acswuiUser;

        // save data
        if ($acswuiUser->hasPermission($this->EditPermission) && isset($_REQUEST['ACTION']) && $_REQUEST['ACTION']=="SAVE") {

            // delete series
            if (isset($_POST['DELETE_SERIES'])) {

                // delete Id
                $rs_id = $_POST['DELETE_SERIES'];

                // delete items from sub-tables
                foreach ($acswuiDatabase->fetch_2d_array("RaceSeriesCars", ['Id'], ['RaceSeries'], [$rs_id]) as $rs_cars) {

                    // delete RaceSeriesEntries
                    foreach ($acswuiDatabase->fetch_2d_array("RaceSeriesEntries", ['Id'], ['RaceSeriesCar'], [$rs_cars['Id']]) as $rs_entry) {
                        $acswuiDatabase->delete_row("RaceSeriesEntries", $rs_entry['Id']);
                    }

                    // delete RaceSeriesCars
                    $acswuiDatabase->delete_row("RaceSeriesCars", $rs_cars['Id']);
                }

                // delete RaceSeries
                $acswuiDatabase->delete_row("RaceSeries", $rs_id);
            }

            // save existing race series
            foreach ($acswuiDatabase->fetch_2d_array("RaceSeries", ['Id', "Name", 'AllowUserOccupation', 'GridAutoFill'], [], [], "Name") as $rs) {
                $id   = $rs['Id'];
                $field_list = array();
                $field_list['Name']                = (isset($_REQUEST["SERIES_" . $id . "_NAME"])               && strlen(trim($_REQUEST["SERIES_" . $id . "_NAME"]))       > 0) ? trim($_REQUEST["SERIES_" . $id . "_NAME"]) : $rs['Name'];
                $field_list['AllowUserOccupation'] = (isset($_REQUEST["SERIES_" . $id . "_ALLOWUSEROCUPATION"]) && $_REQUEST["SERIES_" . $id . "_ALLOWUSEROCUPATION"] == "TRUE") ? 1 : 0;
                $field_list['GridAutoFill']        = (isset($_REQUEST["SERIES_" . $id . "_GRIDAUTOFILL"])       && $_REQUEST["SERIES_" . $id . "_GRIDAUTOFILL"]       == "TRUE") ? 1 : 0;
                if (count($field_list)) $acswuiDatabase->update_row("RaceSeries", $id, $field_list);
            }

            // add new race series
            if (isset($_POST['NEWRACESERIES_NAME']) && strlen($_POST['NEWRACESERIES_NAME']) > 0) {
                $acswuiDatabase->insert_row("RaceSeries", ["Name" => $_POST['NEWRACESERIES_NAME']]);
            }
        }

        // initialize the html output
        $html  = '';

        $html .= "<form action=\"?ACTION=SAVE\" method=\"post\">";
        $html .= '<table><tr><th>Race Series</th><th>Allow Occupation</th><th>Auto Fill</th></tr>';

        // read RaceSeries
        foreach ($acswuiDatabase->fetch_2d_array("RaceSeries", ['Id', "Name", 'AllowUserOccupation', 'GridAutoFill'], [], [], "Name") as $rs) {
            $name = $rs['Name'];
            $id   = $rs['Id'];
            $occu = ($rs['AllowUserOccupation'] == 1) ? "checked" : "";
            $fill = ($rs['GridAutoFill'] == 1) ? "checked" : "";
            $disabled = ($acswuiUser->hasPermission($this->EditPermission)) ? "" : "disabled readonly";

            $html .= "<tr><td><input type=\"text\" name=\"SERIES_" . $id . "_NAME\" value=\"$name\" $disabled></td>";
            $html .= "<td><input type=\"checkbox\" name =\"SERIES_" . $id . "_ALLOWUSEROCUPATION\" value=\"TRUE\" $occu $disabled></td>";
            $html .= "<td><input type=\"checkbox\" name =\"SERIES_" . $id . "_GRIDAUTOFILL\"       value=\"TRUE\" $fill $disabled></td>";
            if ($acswuiUser->hasPermission($this->EditPermission)) {
                $html .= "<td><button type=\"submit\" name=\"DELETE_SERIES\" value=\"$id\">" . _("delete") . "</button></td>";
            }
            $html .= '</tr>';
        }

        if ($acswuiUser->hasPermission($this->EditPermission)) {
            $html .= "<tr>";
            $html .= "<td><input type=\"text\" name=\"NEWRACESERIES_NAME\" placeholder=\"" . _("New Race Series Name") . "\"></td>";
            $html .= "<td colspan=\"2\"><button>" . _("Save") . "</button></td>";
            $html .= "</tr>";
        }


        $html .= '</table>';
        $html .= "</form>";

        return $html;
    }
}

?>
