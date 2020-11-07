<?php

class presets extends cContentPage {

    public function __construct() {
        global $acswuiConfig;
        global $acswuiUser;
        global $acswuiLog;

        $this->MenuName   = _("Presets");
        $this->PageTitle  = "Server Presets";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_ServerContent"];

        // class variables used for html processing
        $this->CanEdit = false;
        $this->CanViewFixed = false;
        $this->CurrentPreset = NULL;
        $this->CurrentData = array();
        $this->ServerCfgJson = array();

    }


    private function get_fixed($group_name, $server_cfg_field) {
        // returns the fixed value of a server preset element
        // return False, when element is not fixed
        global $acswuiConfig;

        $key = $group_name . "_" . $server_cfg_field['TAG'];
        if (array_key_exists($key, $acswuiConfig->FixedServerConfig) === TRUE) {
            return $acswuiConfig->FixedServerConfig[$key];
        } else {
            return FALSE;
        }
    }


    public function getHtml() {

        // access global data
        global $acswuiConfig;
        global $acswuiDatabase;
        global $acswuiUser;

        // check permissions
        if ($acswuiUser->hasPermission('Server_Presets_Edit')) $this->CanEdit = true;
        if ($acswuiUser->hasPermission('Server_Presets_ViewFixed')) $this->CanViewFixed = true;

        // determine PresetId
        if (isset($_REQUEST['PRESET_ID'])) {
            $this->CurrentPreset = (int) $_REQUEST['PRESET_ID'];
        } elseif (isset($_SESSION['PRESET_ID'])) {
            $this->CurrentPreset = (int) $_SESSION['PRESET_ID'];
        } else {
            $this->CurrentPreset = 0;
        }
        $_SESSION['PRESET_ID'] = $this->CurrentPreset;

        // parse server_cfg json
        $json_string = file_get_contents($acswuiConfig->AcsContent . "/server_cfg.json");
        $this->ServerCfgJson = json_decode($json_string, true);

        // save preset
        if (isset($_POST['SAVE'])) {
            if ($_POST['SAVE'] == "CURRENT") $this->saveCurrent();
            if ($_POST['SAVE'] == "NEW") $this->saveNew();
        }

        // request database
        $this->updateCurrentData();

        // initialize the html output
        $html  = '';



        // -----------------
        //  Preset Selector
        // -----------------

        $html .= '<form>';
        $html .= '<select name="PRESET_ID" onchange="this.form.submit()">';

        // existing presets
        $current_preset_found = false;
        foreach ($acswuiDatabase->fetch_2d_array("ServerPresets", ['Id', "Name"], [], "Name") as $sp) {
            if ($this->CurrentPreset == $sp['Id']) {
                $selected = "selected";
                $current_preset_found = true;
            } else {
                $selected = "";
            }
            $html .= '<option value="' . $sp['Id'] . '"' . $selected . '>' . $sp['Name'] . '</option>';
        }
        if (!$current_preset_found) {
            $html .= '<option value="0" selected>???</option>';
        }

        $html .= '</select>';
        $html .= '</form><br>';



        // ---------------
        //  Config Server
        // ---------------

        $html .= "<form action=\"\" method=\"post\"><input type=\"hidden\" name=\"PRESET_ID\" value=\"" . $this->CurrentPreset . "\"/>";

        # preset name
        $html .= "<fieldset>";
        $html .= "Preset Name: <input type=\"text\" name=\"Name\" value=\"" . $this->CurrentData['Name'] . "\" " . (($this->CanEdit) ? "" : "readonly") . "/>";
        $html .= "</fieldset>";

        # save buttons
        if ($this->CanEdit) {
            $html .= "<button type=\"submit\" name=\"SAVE\" value=\"CURRENT\">" . _("Save Preset") . "</button>";
            $html .= " ";
            $html .= "<button type=\"submit\" name=\"SAVE\" value=\"NEW\">" . _("Save As New Preset") . "</button>";
        }

        # generate form for server preset options
        foreach ($this->ServerCfgJson as $group_name => $fieldsets) {
            foreach ($fieldsets as $fieldset) {
                $html .= $this->getFieldsetInputs($group_name, $fieldset);
            }
        }

        $html .= '</form>';


        return $html;
    }

    function getFieldsetInputs($group, $ServerCfgFieldset) {
        global $acswuiConfig;

        $html = "";
        foreach ($ServerCfgFieldset['FIELDS'] as $ServerCfgField) {

            // ignore fixed fields
            if ($this->get_fixed($group, $ServerCfgField) !== FALSE && !$this->CanViewFixed) continue;

            // create html input
            if ($ServerCfgField['TYPE'] == "string") {
                $html .= $this->getTableInputRowString($group, $ServerCfgField);

            } else if ($ServerCfgField['TYPE'] == "int") {
                $html .= $this->getTableInputRowInt($group, $ServerCfgField);

            } else if ($ServerCfgField['TYPE'] == "enum") {
                $html .= $this->getTableInputRowEnum($group, $ServerCfgField);

            } else if ($ServerCfgField['TYPE'] == "text") {
                $html .= $this->getTableInputRowText($group, $ServerCfgField);

            } else if ($ServerCfgField['TYPE'] == "hidden") {
                // not show hidden elements

            } else {
                // TODO log error
                $html .= $ServerCfgField['TYPE'] .  "<br>";
            }
        }

        // return html
        if ($html == "") {
            return "";
        } else {
            $fieldset_html = "<fieldset style=\"display: block; float: left;\"><legend>" . $ServerCfgFieldset['FIELDSET'] . "</legend><table>";
            $fieldset_html .= $html;
            $fieldset_html .= "</table></fieldset>";
            return $fieldset_html;
        }


    }


    function getTableInputRowString($group, $ServerCfgField) {
        $tag = $group . "_" . $ServerCfgField['TAG'];
        $name = $ServerCfgField['NAME'];
        $help = $ServerCfgField['HELP'];
        $unit = $ServerCfgField['UNIT'];
        $fixed_val = $this->get_fixed($group, $ServerCfgField);
        $value = $this->CurrentData[$tag];

        if ($this->CanEdit && $fixed_val === FALSE) {
            return "<tr><td>$name</td><td><input type=\"text\" name=\"$tag\" value=\"$value\" title=\"$help\"/> $unit</td></tr>";
        } else {
            return "<tr><td>$name</td><td><span class=\"disabled_input\">$fixed_val $unit</span></td></tr>";
        }

    }


    function getTableInputRowText($group, $ServerCfgField) {
        $tag = $group . "_" . $ServerCfgField['TAG'];
        $name = $ServerCfgField['NAME'];
        $help = $ServerCfgField['HELP'];
        $value = $this->CurrentData[$tag];
        $fixed_val = $this->get_fixed($group, $ServerCfgField);
        if ($fixed_val !== FALSE) $value = $fixed_val;

        if ($this->CanEdit && $fixed_val === FALSE) {
            return "<tr><td>$name</td><td><textarea name=\"$tag\" title=\"$help\">$value</textarea></td></tr>";
        } else {
            return "<tr><td>$name</td><td><span class=\"disabled_input\">$value</span></td></tr>";
        }

    }


    function getTableInputRowInt($group, $ServerCfgField) {
        $tag = $group . "_" . $ServerCfgField['TAG'];
        $name = $ServerCfgField['NAME'];
        $help = $ServerCfgField['HELP'];
        $unit = $ServerCfgField['UNIT'];
        $min = $ServerCfgField['MIN'];
        $max = $ServerCfgField['MAX'];
        $value = $this->CurrentData[$tag];
        $fixed_val = $this->get_fixed($group, $ServerCfgField);
        if ($fixed_val !== FALSE) $value = $fixed_val;

        if ($this->CanEdit && $fixed_val === FALSE) {
            return "<tr><td>$name</td><td><input type=\"number\" min=\"$min\" max=\"$max\" step=\"1\" name=\"$tag\" value=\"$value\" title=\"$help\"> $unit</td></tr>";
        } else {
            return "<tr><td>$name</td><td><span class=\"disabled_input\">$value $unit</span></td></tr>";
        }

    }


    function getTableInputRowEnum($group, $ServerCfgField) {
        $tag = $group . "_" . $ServerCfgField['TAG'];
        $name = $ServerCfgField['NAME'];
        $help = $ServerCfgField['HELP'];
        $unit = $ServerCfgField['UNIT'];
        $value = $this->CurrentData[$tag];
        $fixed_val = $this->get_fixed($group, $ServerCfgField);
        if ($fixed_val !== FALSE) $value = $fixed_val;

        if ($this->CanEdit && $fixed_val === FALSE) {
            $html = "<tr><td>$name</td><td>";
            $html .= "<select name=\"$tag\" title=\"$help\">";
            foreach ($ServerCfgField['ENUMS'] as $enum) {
                $opt_val = $enum['VALUE'];
                $opt_text = $enum['TEXT'];
                $opt_checked = ($opt_val == $value) ? "selected" : "";
                $html .= "<option value=\"$opt_val\" $opt_checked>$opt_text</option>";
            }
            $html .= "</select>";
            $html .= "</td></tr>";
            return $html;

        } else {
            foreach ($ServerCfgField['ENUMS'] as $enum) {
                if ($enum['VALUE'] == $value) {
                    $value = $enum['TEXT'];
                    break;
                }
            }
            return "<tr><td>$name</td><td><span class=\"disabled_input\">$value $unit</span></td></tr>";
        }
    }

    function updateCurrentData() {
        global $acswuiDatabase;

        // fetch columns
        $columns = array();
        $columns[] = "Id";
        $columns[] = "Name";
        foreach ($this->ServerCfgJson as $group_name => $fieldsets) {
            foreach ($fieldsets as $fieldset) {
                foreach ($fieldset['FIELDS'] as $field) {
                    if ($field['TYPE'] == "hidden") continue;
                    $columns[] = $group_name . "_" . $field['TAG'];
                }
            }
        }

        // prepare data array
        $data = array();
        foreach ($columns as $col) {
            $data[$col] = "";
        }

        // overwrite data array with db values
        $res = $acswuiDatabase->fetch_2d_array("ServerPresets", $columns, ['Id' => $this->CurrentPreset]);
        if (count($res) == 1) {
            foreach ($columns as $col) {
                $data[$col] = $res[0][$col];
            }
        }

        // scan for fixed values
        foreach ($this->ServerCfgJson as $group_name => $fieldsets) {
            foreach ($fieldsets as $fieldset) {
                foreach ($fieldset['FIELDS'] as $field) {
                    $col = $group_name . "_" . $field['TAG'];
                    $fixed_val = $this->get_fixed($group_name, $field);
                    if ($fixed_val !== FALSE) {
                        $data[$col] = $fixed_val;
                    }
                }
            }
        }

        $this->CurrentData = $data;
    }

    function getPostData() {
        // check permission
        if (!$this->CanEdit) return;

        // gather data columns
        $data = array();
        if (isset($_POST['Name'])) {
            $data['Name'] = $_POST['Name'];
        }
        foreach ($this->ServerCfgJson as $group_name => $fieldsets) {
            foreach ($fieldsets as $fieldset) {
                foreach ($fieldset['FIELDS'] as $field) {
                    if ($field['TYPE'] == "hidden") continue;

                    $col = $group_name . "_" . $field['TAG'];

                    $fixed_val = $this->get_fixed($group_name, $field);
                    if ($fixed_val !== FALSE || !isset($_POST[$col])) {
                        $val = $fixed_val;
                    } else {
                        $val = $_POST[$col];
                    }

                    $data[$col] = $val;
                }
            }
        }

        return $data;
    }

    function saveCurrent() {
        global $acswuiDatabase;

        // check permission
        if (!$this->CanEdit) return;

        // save data
        $data = $this->getPostData();
        $acswuiDatabase->update_row("ServerPresets", $this->CurrentPreset, $data);
    }

    function saveNew() {
        global $acswuiDatabase;

        // check permission
        if (!$this->CanEdit) return;

        // save data
        $data = $this->getPostData();
        $this->CurrentPreset = $acswuiDatabase->insert_row("ServerPresets", $data);
    }
}

?>
