<?php

class presets extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Presets");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_ServerContent"];
        $this->EditPermission = 'Server_EditPresets';
    }

    public function getHtml() {

        // access global data
        global $acswuiConfig;
        global $acswuiDatabase;
        global $acswuiUser;

        // edit permission
        $permitted = $acswuiUser->hasPermission($this->EditPermission);

        // server_cfg json
        $json_string = file_get_contents($acswuiConfig->SrvCfgJsonPath);
        $server_cfg_json = json_decode($json_string, true);

        // requested preset Id
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

//         // set preset values
//         $permitted = $acswuiUser->hasPermission($this->EditPermission);
//         $this->preset_values = array();
//         foreach ($this->preset_fields as $e) {
//             $this->preset_values[$e] = "";
//         }

        // get actual preset values
        if ($preset_id != 0 && $preset_id != "NEW_PRESET") {
            $ret = $acswuiDatabase->fetch_2d_array("ServerPresets", ['Name'], ['Id' => $preset_id]);
            $this->preset_values['Name'] = $ret[0]['Name'];

//             // add field values
//             foreach ($this->preset_fields as $e) {
//                 if ($this->hasGlobalOverwrite($e)) {
//                     $this->preset_values[$e] = $this->getGlobalOverwrite($e);
//                 } else {
//                     $this->preset_values[$e] = $ret[0][$e];
//                 }
//             }

        }


        // initialize the html output
        $html  = '';



        // -----------------
        //  Preset Selector
        // -----------------

        $html .= '<form>';
        $html .= '<select name="PRESET_ID" onchange="this.form.submit()">';

        // existing presets
        $amount_existing_presets = 0;
        foreach ($acswuiDatabase->fetch_2d_array("ServerPresets", ['Id', "Name"], [], "Name") as $sp) {
            $amount_existing_presets += 1;
            $selected = ($preset_id == $sp['Id']) ? "selected" : "";
            $html .= '<option value="' . $sp['Id'] . '"' . $selected . '>' . $sp['Name'] . '</option>';
        }

        // new preset
        if ($permitted) {
            if ($amount_existing_presets == 0)
                $html .= '<option value=""></option>';
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

        # save button
        $html .= "<button type=\"submit\" name=\"SAVEALL\" value=\"TRUE\">" . _("Save") . "</button>";

        foreach ($server_cfg_json as $group_name => $fieldsets) {
            foreach ($fieldsets as $fieldset) {
                $html .= "<fieldset style=\"display: block; float: left;\"><legend>" . $fieldset['FIELDSET'] . "</legend><table>";

                foreach ($fieldset['FIELDS'] as $field) {

                    if ($field['TYPE'] == "string") {
                        $name = $field['NAME'];
                        $comment = $field['HELP'];
                        $html .= "<tr><td>$name</td><td><input type=\"text\" name=\"\" value=\"\" title=\"$comment\" /></td></tr>";

                    } else if ($field['TYPE'] == "int") {
                        $name = $field['NAME'];
                        $comment = $field['HELP'];
                        $min = $field['MIN'];
                        $max = $field['MAX'];
                        $unit = $field['UNIT'];
                        $html .= "<tr><td>$name</td><td><input type=\"number\" min=\"$min\" max=\"$max\" step=\"1\" name=\"\" value=\"\" title=\"$comment\">$unit</td></tr>";

                    } else if ($field['TYPE'] == "enum") {
                        $name = $field['NAME'];
                        $comment = $field['HELP'];

                        $html .= "<tr><td>$name</td><td>";
                        $html .= "<select name=\"\" title=\"$comment\">";
                        foreach ($field['ENUMS'] as $enum) {
                            $opt_val = $enum['VALUE'];
                            $opt_text = $enum['TEXT'];
                            $html .= "<option value=\"$opt_val\">$opt_text</option>";
                        }
                        $html .= "</select>";
                        $html .= "</td></tr>";

                    } else if ($field['TYPE'] == "text") {
                        $name = $field['NAME'];
                        $comment = $field['HELP'];
                        $html .= "<tr><td>$name</td><td><textarea name=\"\" title=\"$comment\"></textarea></td></tr>";

                    } else if ($field['TYPE'] == "hidden") {
                        // not show hidden elements

                    } else {
                        // TODO log error
                        echo $field['TYPE'] .  "<br>";
                    }


                }
                $html .= "</table></fieldset>";
            }
        }


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
