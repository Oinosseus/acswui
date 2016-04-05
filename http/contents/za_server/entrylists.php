<?php

class entrylists extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Race Series");
        $this->TextDomain = "acswui";
        $this->PageTitle  = _("Race Series / Race Grid Management");
        $this->RequirePermissions = ["View_ServerContent"];
        $this->EditPermission     = 'Edit_ServerRaceSeries';
        $this->OccupyPermission   = 'Occupy_RaceSeriesGrid';
    }

    public function getHtml() {

        // access global data
        global $acswuiDatabase;
        global $acswuiUser;

        // identify sub content
        if (isset($_REQUEST['RACESERIES']) && isset($_REQUEST['VIEW']) && $_REQUEST['VIEW'] == "SERIES") {
            return $this->_getHtml_RaceSeries($_REQUEST['RACESERIES']);
        } else {
            return $this->_getHtml_RaceSeriesOverview();
        }

        return $html;
    }


    function _getHtml_RaceSeriesOverview() {
        global $acswuiDatabase;
        global $acswuiUser;

        // insert new race series
        if ($acswuiUser->hasPermission($this->EditPermission)) {
            if (isset($_REQUEST['ACTION']) && $_REQUEST['ACTION']=="NEWRACESERIES") {
                if (isset($_POST['NEWRACESERIES_NAME']) && strlen($_POST['NEWRACESERIES_NAME']) > 0) {
                    $acswuiDatabase->insert_row("RaceSeries", ["Name" => $_POST['NEWRACESERIES_NAME']]);
                }
            }
        }

        // initialize the html output
        $html  = '';

        $html .= "";
        $html .= '<table><tr><th>Race Series</th><th>Allow Occupation</th><th>Auto Fill</th></tr>';

        // read RaceSeries
        foreach ($acswuiDatabase->fetch_2d_array("RaceSeries", ['Id', "Name", 'AllowUserOccupation', 'GridAutoFill'], [], [], "Name") as $rs) {
            $name = $rs['Name'];
            $id   = $rs['Id'];
            $occu = ($rs['AllowUserOccupation'] == 1) ? "checked" : "";
            $fill = ($rs['GridAutoFill'] == 1) ? "checked" : "";

            $html .= "<tr><td><a href=\"?VIEW=SERIES&RACESERIES=$id\">$name</a></td>";
            $html .= "<td><input type=\"checkbox\" $occu disabled></td>";
            $html .= "<td><input type=\"checkbox\" $fill disabled></td>";
            $html .= '</tr>';
        }

        if ($acswuiUser->hasPermission($this->EditPermission)) {
            $html .= "<tr><form action=\"?ACTION=NEWRACESERIES\" method=\"post\">";
            $html .= "<td><input type=\"text\" name=\"NEWRACESERIES_NAME\" placeholder=\"" . _("Name") . "\"></td>";
            $html .= "<td colspan=\"2\"><button>" . _("Create New Race Series") . "</button></td>";
            $html .= "</form></tr>";
        }


        $html .= '</table>';

        return $html;
    }

    function _getHtml_RaceSeries($id) {
        global $acswuiDatabase;
        global $acswuiUser;
        global $acswuiLog;

        $rs      = $acswuiDatabase->fetch_2d_array("RaceSeries", ['Id', "Name", 'AllowUserOccupation', 'GridAutoFill'], ['Id'], [$id]);
        $rs_id   = $rs[0]['Id'];
        $rs_name = $rs[0]['Name'];
        $rs_occu = ($rs[0]['AllowUserOccupation'] == 1) ? "checked":"";
        $rs_fill = ($rs[0]['GridAutoFill'] == 1) ? "checked":"";

        // initialize the html output
        $html  = '<form>';

        // general race series information
        $html .= "<label>" . _("Name") . "<input type=\"text\" name=\"\" value=\"$rs_name\"></label>";
        $html .= "<label><input type=\"checkbox\" name=\"\" $rs_occu disabled>" . _("AllowUserOccupation") . "</label>";
        $html .= "<label><input type=\"checkbox\" name=\"\" $rs_fill disabled>" . _("GridAutoFill") . "</label>";

        // cars
        foreach ($acswuiDatabase->fetch_2d_array("RaceSeriesCars", ['Id', "Car", 'Ballast', 'Count', 'AllowUserOccupation', 'GridAutoFill'], ['RaceSeries'], [$id]) as $rsc)  {
            // get car infos
            $cars  = $acswuiDatabase->fetch_2d_array("Cars", ['Id', 'Car', "Name", 'Brand'], ['Id'], [$rsc['Car']]);
            $car   = $cars[0];
            $skins = $acswuiDatabase->fetch_2d_array("CarSkins", ['Id'], ['Car'], [$rsc['Car']]);
            $skin  = $skins[0];

            $readonly = ($acswuiUser->hasPermission($this->EditPermission)) ? "readonly":"";
            $disabled = ($acswuiUser->hasPermission($this->EditPermission)) ? "disabled":"";
            $html .= '<fieldset><legend>' . $car['Name'] . '</legend>';
            $html .= getImgCarSkin($skin['Id'], $car['Car']);
            $html .= "<label><input type=\"number\" $readonly>" . _("Count") . '</label>';
            $html .= "<label><input type=\"number\" $readonly>" . _("Ballast") . '</label>';
            $html .= "<label><input type=\"checkbox\" $disabled>" . _("AllowUserOccupation") . '</label>';
            $html .= "<label><input type=\"checkbox\" $disabled>" . _("GridAutoFill") . '</label>';
            $html .= '</fieldset>';
        }

        $html .= '</form>';

        return $html;
    }
}

?>
