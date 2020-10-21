<?php

class presets extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Presets");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_ServerContent"];
        $this->EditPermission = 'Edit_ServerPresets';
    }

    public function getHtml() {

        // access global data
        global $acswuiDatabase;
        global $acswuiUser;

        $preset_fields_srv = ['srv_NAME', 'srv_CARS', 'srv_TRACK', 'srv_CONFIG_TRACK', 'srv_SUN_ANGLE', 'srv_MAX_CLIENTS', 'srv_RACE_OVER_TIME', 'srv_ALLOWED_TYRES_OUT', 'srv_UDP_PORT', 'srv_TCP_PORT', 'srv_HTTP_PORT', 'srv_PASSWORD', 'srv_LOOP_MODE', 'srv_REGISTER_TO_LOBBY', 'srv_PICKUP_MODE_ENABLED', 'srv_SLEEP_TIME', 'srv_VOTING_QUORUM', 'srv_VOTE_DURATION', 'srv_BLACKLIST_MODE', 'srv_TC_ALLOWED', 'srv_ABS_ALLOWED', 'srv_STABILITY_ALLOWED', 'srv_AUTOCLUTCH_ALLOWED', 'srv_DAMAGE_MULTIPLIER', 'srv_FUEL_RATE', 'srv_TYRE_WEAR_RATE', 'srv_CLIENT_SEND_INTERVAL_HZ', 'srv_TYRE_BLANKETS_ALLOWED', 'srv_ADMIN_PASSWORD', 'srv_QUALIFY_MAX_WAIT_PERC', 'srv_WELCOME_MESSAGE', 'srv_FORCE_VIRTUAL_MIRROR', 'srv_LEGAL_TYRES', 'srv_MAX_BALLAST_KG', 'srv_UDP_PLUGIN_LOCAL_PORT', 'srv_UDP_PLUGIN_ADDRESS', 'srv_AUTH_PLUGIN_ADDRESS'];
        $preset_fields_dyt = ['dyt_SESSION_START', 'dyt_RANDOMNESS', 'dyt_LAP_GAIN', 'dyt_SESSION_TRANSFER'];
        $preset_fields_bok = ['bok_NAME', 'bok_TIME'];
        $preset_fields_prt = ['prt_NAME', 'prt_TIME', 'prt_IS_OPEN'];
        $preset_fields_qly = ['qly_NAME', 'qly_TIME', 'qly_IS_OPEN'];
        $preset_fields_rce = ['rce_NAME', 'rce_LAPS', 'rce_WAIT_TIME', 'rce_IS_OPEN'];
        $preset_fields_wth = ['wth_GRAPHICS', 'wth_BASE_TEMPERATURE_AMBIENT', 'wth_VARIATION_AMBIENT', 'wth_BASE_TEMPERATURE_ROAD', 'wth_VARIATION_ROAD'];
        $this->preset_fields = array_merge($preset_fields_srv, $preset_fields_dyt, $preset_fields_bok, $preset_fields_prt, $preset_fields_qly, $preset_fields_rce, $preset_fields_wth);

        if (isset($_REQUEST['PRESET_ID'])) {
            $preset_id = $_REQUEST['PRESET_ID'];
        } elseif (isset($_SESSION['PRESET_ID'])) {
            $preset_id = $_SESSION['PRESET_ID'];
        } else {
            $preset_id = 0;
        }
        $_SESSION['PRESET_ID'] = $preset_id;

        // save preset
        if (isset($_POST['SAVEALL']) && $preset_id > 0 && $preset_id != "NEW_PRESET") $this->saveall($preset_id, false);
        if (isset($_POST['SAVEALL']) && $preset_id == "NEW_PRESET") $this->saveall(0, true);

        // set preset values
        $permitted = $acswuiUser->hasPermission($this->EditPermission);
        $this->preset_values = array();
        foreach ($this->preset_fields as $e) {
            $this->preset_values[$e] = "";
        }

        // get actual preset values
        if ($preset_id != 0 && $preset_id != "NEW_PRESET") {
            $ret = $acswuiDatabase->fetch_2d_array("ServerPresets", array_merge(['Name'], $this->preset_fields), ['Id' => $preset_id]);
            $this->preset_values['Name'] = $ret[0]['Name'];

            // add field values
            foreach ($this->preset_fields as $e) {
                if ($this->hasGlobalOverwrite($e)) {
                    $this->preset_values[$e] = $this->getGlobalOverwrite($e);
                } else {
                    $this->preset_values[$e] = $ret[0][$e];
                }
            }

        }


        // initialize the html output
        $html  = '';



        // -----------------
        //  Preset Selector
        // -----------------

        $html .= '<form>';
        $html .= '<select name="PRESET_ID" onchange="this.form.submit()">';

        // empty selection
        $selected = ($preset_id == 0) ? "selected" : "";
        $html .= '<option value="" ' . $selected . '></option>';

        // existing presets
        foreach ($acswuiDatabase->fetch_2d_array("ServerPresets", ['Id', "Name"], [], "Name") as $sp) {
            $selected = ($preset_id == $sp['Id']) ? "selected" : "";
            $html .= '<option value="' . $sp['Id'] . '"' . $selected . '>' . $sp['Name'] . '</option>';
        }

        // new preset
        if ($permitted) {
            $selected = ($preset_id === "NEW_PRESET") ? "selected" : "";
            $html .= '<option value="NEW_PRESET" ' . $selected . '>&lt;' . _("Create New Preset") . '&gt;</option>';
        }
        $html .= '</select>';
        $html .= '</form><br>';



        // ---------------
        //  Config Server
        // ---------------



        $html .= "<form style=\"max-width: 1100px;\" method=\"post\"><input type=\"hidden\" name=\"PRESET_ID\" value=\"$preset_id\"/>";

        # preset name
        $name = (isset($this->preset_values["Name"])) ? $this->preset_values['Name'] : "";
        $html .= "<fieldset>";
        $html .= "Name: <input type=\"text\" name=\"Name\" value=\"$name\" " . (($permitted) ? "" : "readonly") . "/>";
        $html .= "</fieldset>";

        # SERVER section
        $html .= "<fieldset style=\"display: block; float: left;\"><legend>SERVER</legend><table>";
        $html .= $this->getTrInputText("srv_NAME",         $permitted, _("Name which is shown in the assetto corsa server list."));
        $html .= $this->getTrInputText("srv_CARS",         $permitted, _("Leave empty to make the cars eligible."));
        $html .= $this->getTrInputText("srv_TRACK",        $permitted, _("Leave empty to make the track eligible."));
        $html .= $this->getTrInputText("srv_CONFIG_TRACK", $permitted, _("Leave empty to make the track eligible."));
        $html .= $this->getTrInputRange("srv_SUN_ANGLE",     -90, 90, "&deg;", $permitted, _("Angle of the sun."));
        $html .= $this->getTrInputNumber("srv_MAX_CLIENTS",    0, 99, "",      $permitted, _("Set to zero for automatic setting."));
        $html .= $this->getTrInputNumber("srv_RACE_OVER_TIME", 0, 99, "s",     $permitted, _("Remaining seconds after a driver won the race."));
        $html .= $this->getTrInputList("srv_ALLOWED_TYRES_OUT", ['No Penalties', '1 Tyre', '2 Tyres', '3 Tyres'], [-1, 1, 2, 3], $permitted, _("How many tyres can be abroad befoe penalty."));
        $html .= $this->getTrInputNumber("srv_UDP_PORT",       0, 99999, "",   $permitted, _("UDP port number."));
        $html .= $this->getTrInputNumber("srv_TCP_PORT",       0, 99999, "",   $permitted, _("TCP port number."));
        $html .= $this->getTrInputNumber("srv_HTTP_PORT",      0, 99999, "",   $permitted, _("HTTP port number."));
        if ($acswuiUser->hasPermission($this->EditPermission))
            $html .= $this->getTrInputText("srv_PASSWORD",     $permitted, _("The password needed by clients to join the server."));
        $html .= $this->getTrInputList("srv_LOOP_MODE", ['restart from first track', 'stop after last track'], [1, 0], $permitted, _("Automatic server restart."));
        $html .= $this->getTrInputNumber("srv_REGISTER_TO_LOBBY",1, 1,   "",   $permitted, _("Must be set to '1'."));
        $html .= $this->getTrInputList("srv_PICKUP_MODE_ENABLED", ['booking mode', 'pickup mode'], [0, 1], $permitted, _("Warning: in pickup mode you have to list only a circuit under TRACK and you need to list a least one car in the entry_list."));
        $html .= $this->getTrInputNumber("srv_SLEEP_TIME",       1, 1,   "",   $permitted, _("Must be set to '1'."));
        $html .= $this->getTrInputNumber("srv_VOTING_QUORUM",    1, 100, "&#37;", $permitted, _("Percentage of vote that is required for the SESSION vote to pass."));
        $html .= $this->getTrInputNumber("srv_VOTE_DURATION",   10, 120, "s", $permitted, _("Duration of a voting process."));
        $html .= $this->getTrInputList("srv_BLACKLIST_MODE", ['kick until rejoin', 'kick until server restart'], [0, 1], $permitted, _(""));
        $html .= $this->getTrInputList("srv_TC_ALLOWED", ['no cars', 'provided', 'every car'], [0, 1, 2], $permitted, _("Which car can use traction control."));
        $html .= $this->getTrInputList("srv_ABS_ALLOWED", ['no cars', 'provided', 'every car'], [0, 1, 2], $permitted, _("Which car can use ABS."));
        $html .= $this->getTrInputList("srv_STABILITY_ALLOWED", ['off', 'on'], [0, 1], $permitted, _("Enable stbility assistance."));
        $html .= $this->getTrInputList("srv_AUTOCLUTCH_ALLOWED", ['off', 'on'], [0, 1], $permitted, _("Enable automatic clutch."));
        $html .= $this->getTrInputRange("srv_DAMAGE_MULTIPLIER", 0, 200, "&#37;", $permitted, _("Amount of applied damage."));
        $html .= $this->getTrInputRange("srv_FUEL_RATE", 0, 200, "&#37;", $permitted, _("Amount of fuel consumption."));
        $html .= $this->getTrInputRange("srv_TYRE_WEAR_RATE", 0, 200, "&#37;", $permitted, _("Amount of tyre wearing."));
        $html .= $this->getTrInputNumber("srv_CLIENT_SEND_INTERVAL_HZ", 1, 100, "Hz", $permitted, _("Refresh rate of packet sending by the server."));
        $html .= $this->getTrInputList("srv_TYRE_BLANKETS_ALLOWED", ['off', 'on'], [0, 1], $permitted, _("If enabled tyres are warmed at the beginning of a session or after a pitstop."));
        if ($acswuiUser->hasPermission($this->EditPermission))
            $html .= $this->getTrInputText("srv_ADMIN_PASSWORD",     $permitted, _("Password for clients to be recognized as administrator."));
        $html .= $this->getTrInputNumber("srv_QUALIFY_MAX_WAIT_PERC", 1, 1000, "&#37;", $permitted, _("This is the factor to calculate the remaining time in a qualify session after the session is ended: 120 means that 120% of the session fastest lap remains to end the current lap."));
        $html .= $this->getTrInputTextarea("srv_WELCOME_MESSAGE",     $permitted, _("The content of the server welcome message."));
        $html .= $this->getTrInputList("srv_FORCE_VIRTUAL_MIRROR", ['optional', 'enforced'], [0, 1], $permitted, _("With this the virtual mirror can be forced to be shown."));
        $html .= $this->getTrInputText("srv_LEGAL_TYRES",     $permitted, _("List of the tyre's shortnames that will be allowed in the server."));
        $html .= $this->getTrInputNumber("srv_MAX_BALLAST_KG", 0, 1000, "Kg", $permitted, _("The max total of ballast that can be added through the admin command."));
        $html .= $this->getTrInputNumber("srv_UDP_PLUGIN_LOCAL_PORT", 0, 99999, "", $permitted, _(""));
        $html .= $this->getTrInputText("srv_UDP_PLUGIN_ADDRESS",      $permitted, _(""));
        $html .= $this->getTrInputText("srv_AUTH_PLUGIN_ADDRESS",     $permitted, _(""));
        $html .= "</table></fieldset>";

        # DYNAMIC_TRACK section
        $html .= "<fieldset style=\"display: block; float: none;\"><legend>DYNAMIC_TRACK</legend><table>";
        $html .= $this->getTrInputRange("dyt_SESSION_START", 0, 100, "&#37;", $permitted, _("Level of grip at the beginning of a session."));
        $html .= $this->getTrInputRange("dyt_RANDOMNESS", 0, 10, "", $permitted, _("Level of randomness added to the start grip."));
        $html .= $this->getTrInputRange("dyt_LAP_GAIN", 0, 10, "", $permitted, _("How many laps are needed to add 1&#37; grip."));
        $html .= $this->getTrInputRange("dyt_SESSION_TRANSFER", 0, 100, "&#37;", $permitted, _("How much of the gained grip is to be added to the next session."));
        $html .= "</table></fieldset>";

        # BOOKING section
        $html .= "<fieldset style=\"display: block; float: none;\"><legend>BOOKING</legend><table>";
        $html .= $this->getTrInputText("bok_NAME", $permitted, _("Name of the session (leave empty to skip this session)."));
        $html .= $this->getTrInputNumber("bok_TIME", 0, 999, "min", $permitted, _("Session length in minutes."));
        $html .= "</table></fieldset>";

        # PRACTICE section
        $html .= "<fieldset style=\"display: block; float: none;\"><legend>PRACTICE</legend><table>";
        $html .= $this->getTrInputText("prt_NAME", $permitted, _("Name of the session (leave empty to skip this session)."));
        $html .= $this->getTrInputNumber("prt_TIME", 0, 999, "min", $permitted, _("Session length in minutes."));
        $html .= $this->getTrInputList("prt_IS_OPEN", ['no join', 'free join'], [0, 1], $permitted, _(""));
        $html .= "</table></fieldset>";

        # QUALIFY section
        $html .= "<fieldset style=\"display: block; float: none;\"><legend>QUALIFY</legend><table>";
        $html .= $this->getTrInputText("qly_NAME", $permitted, _("Name of the session (leave empty to skip this session)."));
        $html .= $this->getTrInputNumber("qly_TIME", 0, 999, "min", $permitted, _("Session length in minutes."));
        $html .= $this->getTrInputList("qly_IS_OPEN", ['no join', 'free join'], [0, 1], $permitted, _(""));
        $html .= "</table></fieldset>";

        # RACE section
        $html .= "<fieldset style=\"display: block; float: none;\"><legend>RACE</legend><table>";
        $html .= $this->getTrInputText("rce_NAME", $permitted, _("Name of the session (leave empty to skip this session)."));
        $html .= $this->getTrInputNumber("rce_LAPS", 0, 999, "", $permitted, _(""));
        $html .= $this->getTrInputNumber("rce_WAIT_TIME", 0, 999, "s", $permitted, _("Seconds before the start of the session."));
        $html .= $this->getTrInputList("rce_IS_OPEN", ['no join', 'free join', 'until 20s to green'], [0, 1, 2], $permitted, _(""));
        $html .= "</table></fieldset>";

        # save button
        $html .= "<button type=\"submit\" name=\"SAVEALL\" value=\"TRUE\">" . _("Save") . "</button>";

        $html .= '</form>';


        return $html;
    }

    function hasGlobalOverwrite($preset_key) {
        global $acswuiConfig;
        $key_prefix = substr($preset_key, 0, 4);
        $key        = substr($preset_key, 4, strlen($preset_key) - 4);
        $has = false;
        if ($key_prefix == "srv_" && isset($acswuiConfig->SrvCfg_Server[$key]))   $has = true;
        if ($key_prefix == "dyt_" && isset($acswuiConfig->SrvCfg_DynTrack[$key])) $has = true;
        if ($key_prefix == "bok_" && isset($acswuiConfig->SrvCfg_Booking[$key]))  $has = true;
        if ($key_prefix == "prt_" && isset($acswuiConfig->SrvCfg_Practice[$key])) $has = true;
        if ($key_prefix == "qly_" && isset($acswuiConfig->SrvCfg_Qualify[$key]))  $has = true;
        if ($key_prefix == "rce_" && isset($acswuiConfig->SrvCfg_Race[$key]))     $has = true;
        if ($key_prefix == "wth_" && isset($acswuiConfig->SrvCfg_Weather[$key]))  $has = true;
        return $has;
    }

    function getGlobalOverwrite($preset_key) {
        global $acswuiConfig;
        $key_prefix = substr($preset_key, 0, 4);
        $key        = substr($preset_key, 4, strlen($preset_key) - 4);
        $ret = NULL;
        if ($key_prefix == "srv_" && isset($acswuiConfig->SrvCfg_Server[$key]))   $ret = $acswuiConfig->SrvCfg_Server[$key];
        if ($key_prefix == "dyt_" && isset($acswuiConfig->SrvCfg_DynTrack[$key])) $ret = $acswuiConfig->SrvCfg_DynTrack[$key];
        if ($key_prefix == "bok_" && isset($acswuiConfig->SrvCfg_Booking[$key]))  $ret = $acswuiConfig->SrvCfg_Booking[$key];
        if ($key_prefix == "prt_" && isset($acswuiConfig->SrvCfg_Practice[$key])) $ret = $acswuiConfig->SrvCfg_Practice[$key];
        if ($key_prefix == "qly_" && isset($acswuiConfig->SrvCfg_Qualify[$key]))  $ret = $acswuiConfig->SrvCfg_Qualify[$key];
        if ($key_prefix == "rce_" && isset($acswuiConfig->SrvCfg_Race[$key]))     $ret = $acswuiConfig->SrvCfg_Race[$key];
        if ($key_prefix == "wth_" && isset($acswuiConfig->SrvCfg_Weather[$key]))  $ret = $acswuiConfig->SrvCfg_Weather[$key];
        return $ret;
    }

    function getTrInputText($preset_key, $permitted, $comment) {
        $key        = substr($preset_key, 4, strlen($preset_key) - 4);
        $permitted &= !$this->hasGlobalOverwrite($preset_key);
        $readonly = ($permitted) ? "" : "readonly";
        if ($this->hasGlobalOverwrite($preset_key)) {
            $value = $this->getGlobalOverwrite($preset_key);
        } else {
            $value = $this->preset_values[$preset_key];
        }
        return "<tr><td>$key</td><td><input type=\"text\" name=\"$preset_key\" value=\"$value\" title=\"$comment\" $readonly></td></tr>";
    }

    function getTrInputTextarea($preset_key, $permitted, $comment) {
        $key        = substr($preset_key, 4, strlen($preset_key) - 4);
        $permitted &= !$this->hasGlobalOverwrite($preset_key);
        $readonly = ($permitted) ? "" : "readonly";
        if ($this->hasGlobalOverwrite($preset_key)) {
            $value = $this->getGlobalOverwrite($preset_key);
        } else {
            $value = $this->preset_values[$preset_key];
        }
        return "<tr><td>$key</td><td><textarea name=\"$preset_key\" title=\"$comment\" $readonly>$value</textarea></td></tr>";
    }

    function getTrInputRange($preset_key, $min, $max, $unit, $permitted, $comment, $default = 0) {
        $key        = substr($preset_key, 4, strlen($preset_key) - 4);
        $permitted &= !$this->hasGlobalOverwrite($preset_key);
        $readonly = ($permitted) ? "" : "readonly disabled";
        if ($this->hasGlobalOverwrite($preset_key)) {
            $value = $this->getGlobalOverwrite($preset_key);
        } elseif ($this->preset_values[$preset_key] == "") {
            $value = $default;
        } else {
            $value = $this->preset_values[$preset_key];
        }
        return "<tr><td>$key</td><td>$min$unit<input type=\"range\" min=\"$min\" max=\"$max\" step=\"1\" name=\"$preset_key\" value=\"$value\" title=\"$comment\" $readonly>$max$unit</td></tr>";
    }

    function getTrInputNumber($preset_key, $min, $max, $unit, $permitted, $comment, $default = 0) {
        $key        = substr($preset_key, 4, strlen($preset_key) - 4);
        $permitted &= !$this->hasGlobalOverwrite($preset_key);
        $readonly = ($permitted) ? "" : "readonly";
        if ($this->hasGlobalOverwrite($preset_key)) {
            $value = $this->getGlobalOverwrite($preset_key);
        } elseif ($this->preset_values[$preset_key] == "") {
            $value = $default;
        } else {
            $value = $this->preset_values[$preset_key];
        }
        return "<tr><td>$key</td><td><input type=\"number\" min=\"$min\" max=\"$max\" step=\"1\" name=\"$preset_key\" value=\"$value\" title=\"$comment\" $readonly>$unit</td></tr>";
    }

    function getTrInputList($preset_key, $names, $values, $permitted, $comment) {
        $key        = substr($preset_key, 4, strlen($preset_key) - 4);
        $permitted &= !$this->hasGlobalOverwrite($preset_key);
        $readonly = ($permitted) ? "" : "readonly disabled";
        $html  = "<tr><td>$key</td><td>";
        $html .= "<select name=\"$preset_key\" title=\"$comment\" $readonly>";
        for ($i=0; $i < count($names); $i++) {
            if ($this->hasGlobalOverwrite($preset_key)) {
                if ($values[$i] == $this->getGlobalOverwrite($preset_key)) {
                    $html .= "<option value=\"" . $values[$i] . "\" selected>" . $names[$i] . "</option>";
                }
            } else {
                $selected = ($this->preset_values[$preset_key] == $values[$i]) ? "selected" : "";
                $html .= "<option value=\"" . $values[$i] . "\" $selected>" . $names[$i] . "</option>";
            }
        }
        $html .= "</select>";
        $html .= "</td></tr>";
        return $html;
    }

    function saveall($id, $new=false) {
        global $acswuiDatabase;
        global $acswuiUser;

        // check permission
        if (!$acswuiUser->hasPermission($this->EditPermission)) return;

        // get form data
        $field_list = array();
        if (isset($_POST["Name"])) {
            $field_list["Name"] = $_POST["Name"];
        }
        foreach ($this->preset_fields as $p) {
            if (isset($_POST[$p])) {
                $field_list[$p] = $_POST[$p];
            }
        }

        // update database
        if ($new === true) {
            $_REQUEST['PRESET_ID'] = $acswuiDatabase->insert_row("ServerPresets", $field_list);
        } else {
            $acswuiDatabase->update_row("ServerPresets", $id, $field_list);
        }
    }
}

?>
