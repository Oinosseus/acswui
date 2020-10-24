<?php

class control extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Control");
        $this->PageTitle  = "Server Control";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_ServerContent"];
        $this->EditPermission = "Server_Control";
    }

    private function serverStatus() {
        exec("sudo -u gamesrv /home/gamesrv/bin/minecraft.sh status", $cmd_str, $cmd_ret);
        return  (strstr($cmd_str[0], "not running") === FALSE) ?  TRUE : FALSE;
    }

    public function getHtml() {

        // access global data
        global $acswuiDatabase;
        global $acswuiUser;

        // --------------------------------------------------------------------
        //                        Process Post Data
        // --------------------------------------------------------------------

        $current_preset_id = Null;
        if (isset($_POST['PRESET_ID'])) {
            $current_preset_id = (int) $_POST['PRESET_ID'];
        }

        $current_carclass_id = Null;
        if (isset($_POST['CARCLASS_ID'])) {
            $current_carclass_id = (int) $_POST['CARCLASS_ID'];
        }


        // initialize the html output
        $html  = "";



        // --------------------------------------------------------------------
        //                     Process Requested Action
        // --------------------------------------------------------------------

        if (isset($_POST['ACTION'])) {
            if ($_POST['ACTION'] == "START_SERVER") {
                echo "START<br>";
            }
        }


        // --------------------------------------------------------------------
        //                            Start Server
        // --------------------------------------------------------------------

        $html .= "<h1>Start Server</h1>";
        $html .= '<form action="" method ="post">';

        // preset
        $html .= "Server Preset";
        $html .= '<select name="PRESET_ID">';
        foreach ($acswuiDatabase->fetch_2d_array("ServerPresets", ['Id', "Name"], [], "Name") as $sp) {
            $selected = ($current_preset_id == $sp['Id']) ? "selected" : "";
            $html .= '<option value="' . $sp['Id'] . '"' . $selected . '>' . $sp['Name'] . '</option>';
        }
        $html .= '</select>';
        $html .= '<br>';

        # car class
        $html .= "Car Class";
        $html .= '<select name="CARCLASS_ID">';
        foreach ($acswuiDatabase->fetch_2d_array("CarClasses", ['Id', "Name"], [], "Name") as $sp) {
            $selected = ($current_carclass_id == $sp['Id']) ? "selected" : "";
            $html .= '<option value="' . $sp['Id'] . '"' . $selected . '>' . $sp['Name'] . '</option>';
        }
        $html .= '</select>';
        $html .= '<br>';

        # start
        $html .= '<button type="submit" name="ACTION" value="START_SERVER">' . _("Start Server") . '</button>';


        $html .= '</form>';


        return $html;
    }
}

?>
