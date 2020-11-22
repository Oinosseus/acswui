<?php


class control extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Control");
        $this->PageTitle  = "Session Control";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["Session_Control"];

        // class local vars
        $this->CurrentServerSlot = Null;
        $this->CurrentPreset = Null;
        $this->CurrentCarClass = Null;
        $this->CurrentTrack = Null;
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
        global $acswuiLog;
        global $acswuiDatabase;
        global $acswuiUser;


        // --------------------------------------------------------------------
        //                        Process Post Data
        // --------------------------------------------------------------------

        if (isset($_POST['SERVERSLOT_ID'])) {
            $this->CurrentServerSlot = new ServerSlot((int) $_POST['SERVERSLOT_ID']);
            $_SESSION['SERVER_CONTROL_SERVERSLOT_ID'] = $this->CurrentServerSlot->id();
        } else if (isset($_SESSION['SERVER_CONTROL_SERVERSLOT_ID'])) {
            $this->CurrentServerSlot = new ServerSlot((int) $_SESSION['SERVER_CONTROL_SERVERSLOT_ID']);
        }

        if (isset($_POST['PRESET_ID'])) {
            $this->CurrentPreset = new ServerPreset((int) $_POST['PRESET_ID']);
            $_SESSION['SERVER_CONTROL_PRESET_ID'] = $this->CurrentPreset->id();
        } else if (isset($_SESSION['SERVER_CONTROL_PRESET_ID'])) {
            $this->CurrentPresetId = new ServerPreset((int) $_SESSION['SERVER_CONTROL_PRESET_ID']);
        }

        if (isset($_POST['CARCLASS_ID'])) {
            $this->CurrentCarClass = new CarClass((int) $_POST['CARCLASS_ID']);
            $_SESSION['SERVER_CONTROL_CARCLASS_ID'] = $this->CurrentCarClass->id();
        } else if (isset($_SESSION['SERVER_CONTROL_CARCLASS_ID'])) {
            $this->CurrentCarClassId = new CarClass((int) $_SESSION['SERVER_CONTROL_CARCLASS_ID']);
        }

        if (isset($_POST['TRACK_ID'])) {
            $this->CurrentTrack = new Track((int) $_POST['TRACK_ID']);
            $_SESSION['SERVER_CONTROL_TRACK_ID'] = $this->CurrentTrack->id();
        } else if (isset($_SESSION['SERVER_CONTROL_TRACK_ID'])) {
            $this->CurrentTrackId = new Track((int) $_SESSION['SERVER_CONTROL_TRACK_ID']);
        }



        // --------------------------------------------------------------------
        //                     Process Requested Action
        // --------------------------------------------------------------------

        if (isset($_POST['ACTION'])) {
            if ($_POST['ACTION'] == "START_SERVER") {
                if ($this->CurrentServerSlot->online() === FALSE) {
                    $this->CurrentServerSlot->start($this->CurrentPreset,
                                                    $this->CurrentCarClass,
                                                    $this->CurrentTrack);
                }
            } else if ($_POST['ACTION'] == "STOP_SERVER") {
                if ($this->CurrentServerSlot->online() === TRUE) {
                    $this->CurrentServerSlot->stop();
                }
            }
        }


        // initialize the html output
        $html  = "";



        // --------------------------------------------------------------------
        //                            Server Status
        // --------------------------------------------------------------------

        $html .= "<h1>Server Status</h1>";

        $html .= "<table>";
        $html .= "<tr><th>Server</th><th>Status</th></tr>";
        foreach (ServerSlot::listSlots() as $ss) {
            $html .= "<tr>";
            $html .= "<td>" . $ss->name() . "</td>";
        if ($ss->online()) {
            $html .= '<td style="color:#0d0; font-weight:bold;">online</td>';
        } else {
            $html .= '<td style="color:#d00; font-weight:bold;">offline</td>';
        }
            $html .= "</tr>";
        }
        $html .= "</table>";



        // --------------------------------------------------------------------
        //                            Server Control
        // --------------------------------------------------------------------


        foreach (ServerSlot::listSlots() as $ss) {

            if (!$acswuiUser->hasPermission("Session_Control_Slot" . $ss->id())) continue;

            $html .= "<h1>Cobntrol Server &quot;" . $ss->name() . "&quot;</h1>";
            $html .= '<form action="" method="post">';
            $html .= '<input type="hidden" name="SERVERSLOT_ID" value="' . $ss->id() . '">';

            if ($ss->online()) {
                $html .= '<span style="color:#0d0; font-weight:bold; font-size: 1.5em;">online</span><br>';
                $html .= '<button type="submit" name="ACTION" value="STOP_SERVER">' . _("Stop Server") . '</button>';

            } else {
                $html .= '<span style="color:#d00; font-weight:bold; font-size: 1.0em;">offline</span>';

                // preset
                $html .= "Server Preset";
                $html .= '<select name="PRESET_ID">';
                foreach (ServerPreset::listPresets() as $sp) {
                    if ($this->CurrentPreset === NULL) $this->CurrentPreset = $sp;
                    $selected = ($this->CurrentPreset->id() == $sp->id()) ? "selected" : "";
                    $html .= '<option value="' . $sp->id() . '"' . $selected . '>' . $sp->name() . '</option>';
                }
                $html .= '</select>';
                $html .= '<br>';

                # car class
                $html .= "Car Class";
                $html .= '<select name="CARCLASS_ID">';
                foreach (CarClass::listClasses() as $cc) {
                    if ($this->CurrentCarClass === NULL) $this->CurrentCarClass = $cc;
                    $selected = ($this->CurrentCarClass->id() == $cc->id()) ? "selected" : "";
                    $html .= '<option value="' . $cc->id() . '"' . $selected . '>' . $cc->name() . '</option>';
                }
                $html .= '</select>';
                $html .= '<br>';

                # track
                $html .= "Track";
                $html .= '<select name="TRACK_ID">';
                foreach (Track::listTracks() as $t) {
                    if ($this->CurrentTrack === NULL) $this->CurrentTrack = $t;
                    $selected = ($this->CurrentTrack->id() == $t->id()) ? "selected" : "";
                    $name_str = $t->name();
                    $name_str .= " (" . sprintf("%0.1f", $t->length()/1000) . "km";
                    $name_str .= ", " . $t->pitboxes() . "pits)";
                    $html .= '<option value="' . $t->id() . '"' . $selected . ">" . $name_str . "</option>";
                }
                $html .= '</select>';
                $html .= '<br>';

                # start
                $html .= '<button type="submit" name="ACTION" value="START_SERVER">' . _("Start Server") . '</button>';
            }

            $html .= '</form>';
        }


        return $html;
    }

    public function getPresetData() {
        global $acswuiConfig;
        global $acswuiLog;
        global $acswuiDatabase;

        // parse server_cfg json
        $json_string = file_get_contents($acswuiConfig->AcsContent . "/server_cfg.json");
        $ServerCfgJson = json_decode($json_string, true);

        // prepare return data
        $preset_data = array();
        foreach ($ServerCfgJson as $group_name => $fieldsets) {
            // initialize groups as empty arrays
            $preset_data[$group_name] = array();
        }
        foreach ($ServerCfgJson as $group_name => $fieldsets) {
            $grp_data = $preset_data[$group_name];
            foreach ($fieldsets as $fieldset) {
                foreach ($fieldset['FIELDS'] as $field) {
                    $grp_data[$field['TAG']] = "";
                }
            }
            $preset_data[$group_name] = $grp_data;
        }

        // get preset data from db
        $db_columns = array(); //$acswuiDatabase->fetch_column_names("ServerPresets");
        foreach ($ServerCfgJson as $group_name => $fieldsets) {
            foreach ($fieldsets as $fieldset) {
                foreach ($fieldset['FIELDS'] as $field) {
                    if ($field['TYPE'] == "hidden") continue;
                    $db_columns[] = $group_name . "_" . $field['TAG'];
                }
            }
        }
        $res = $acswuiDatabase->fetch_2d_array("ServerPresets", $db_columns, ['Id' => $this->CurrentPresetId]);
        if (count($res) !== 1) {
            $acswuiLog->logWarning("Ignore server start with not existing preset Id " . $this->CurrentPresetId);
            return;
        }
        foreach ($ServerCfgJson as $group_name => $fieldsets) {
            foreach ($fieldsets as $fieldset) {
                foreach ($fieldset['FIELDS'] as $field) {
                    if ($field['TYPE'] == "hidden") continue;
                    $db_column = $group_name . "_" . $field['TAG'];
                    $val = $res[0][$db_column];
                    $preset_data[$group_name][$field['TAG']] = $val;
                }
            }
        }

        // overwrite fixed values
        foreach ($ServerCfgJson as $group_name => $fieldsets) {
            foreach ($fieldsets as $fieldset) {
                foreach ($fieldset['FIELDS'] as $field) {
                    $fixed_val = $this->get_fixed($group_name, $field);
                    if ($fixed_val !== FALSE) {
                        $preset_data[$group_name][$field['TAG']] = $fixed_val;
                    }
                }
            }
        }

        return $preset_data;
    }
}

?>
