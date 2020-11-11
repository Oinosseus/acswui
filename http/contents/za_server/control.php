<?php


class CarEntry {

    private $Car = NULL;
    private $SkinIndex = 0;

    //! @param $car A Car object
    public function __construct($car) {
        $this->Car = $car;
    }

    public function model() {
        return $this->Car->model();
    }

    public function skin() {
        $skins = $this->Car->skins();
        if ($this->SkinIndex >= count($skins)) return "";
        $skin = $skins[$this->SkinIndex];
        $this->SkinIndex += 1;
        return $skin->skin();
    }
}



class CarEntries {
    private $Entries = array();
    private $Index = 0;

    //! @param $cars list of Car objects
    public function __construct($cars) {
        foreach($cars as $car) {
            $this->Entries[] = new CarEntry($car);
        }
    }

    public function getNextEntry() {

        // wrap index
        if ($this->Index >= count($this->Entries)) $this->Index = 0;

        $entry = $this->Entries[$this->Index];

        // increment index
        ++$this->Index;

        return $entry;
    }
}



class control extends cContentPage {

    public function __construct() {
        $this->MenuName   = _("Control");
        $this->PageTitle  = "Session Control";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["Session_Control"];

        // class local vars
        $this->CurrentPresetId = Null;
        $this->CurrentCarClassId = Null;
        $this->CurrentTrackId = Null;
    }

    private function server_online() {
        global $acswuiConfig;
        global $acswuiLog;

        exec("pgrep acServer", $cmd_str, $cmd_ret);
        if ($cmd_ret === 0) return TRUE;
        return FALSE;
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

        if (isset($_POST['PRESET_ID'])) {
            $this->CurrentPresetId = (int) $_POST['PRESET_ID'];
            $_SESSION['SERVER_CONTROL_PRESET_ID'] = $this->CurrentPresetId;
        } else if (isset($_SESSION['SERVER_CONTROL_PRESET_ID'])) {
            $this->CurrentPresetId = (int) $_SESSION['SERVER_CONTROL_PRESET_ID'];
        }

        if (isset($_POST['CARCLASS_ID'])) {
            $this->CurrentCarClassId = (int) $_POST['CARCLASS_ID'];
            $_SESSION['SERVER_CONTROL_CARCLASS_ID'] = $this->CurrentCarClassId;
        } else if (isset($_SESSION['SERVER_CONTROL_CARCLASS_ID'])) {
            $this->CurrentCarClassId = (int) $_SESSION['SERVER_CONTROL_CARCLASS_ID'];
        }

        if (isset($_POST['TRACK_ID'])) {
            $this->CurrentTrackId = (int) $_POST['TRACK_ID'];
            $_SESSION['SERVER_CONTROL_TRACK_ID'] = $this->CurrentTrackId;
        } else if (isset($_SESSION['SERVER_CONTROL_TRACK_ID'])) {
            $this->CurrentTrackId = (int) $_SESSION['SERVER_CONTROL_TRACK_ID'];
        }



        // --------------------------------------------------------------------
        //                     Process Requested Action
        // --------------------------------------------------------------------

        if (isset($_POST['ACTION'])) {
            if ($_POST['ACTION'] == "START_SERVER") {
                $this->start_server();
            } else if ($_POST['ACTION'] == "STOP_SERVER") {
                $this->stop_server();
            }
        }


        // initialize the html output
        $html  = "";



        // --------------------------------------------------------------------
        //                            Server Status
        // --------------------------------------------------------------------

        $html .= "<h1>Server Status</h1>";
        if ($this->server_online()) {
            $html .= '<span style="color:#0d0; font-weight:bold; font-size:1.5em;">ONLINE</span><br>';
        } else {
            $html .= '<span style="color:#d00; font-weight:bold; font-size:1.5em;">OFFLINE</span><br>';
        }



        // --------------------------------------------------------------------
        //                            Server Control
        // --------------------------------------------------------------------

        $html .= '<form action="" method="post">';

        if (!$this->server_online()) {

            $html .= "<h1>Start Server</h1>";

            // preset
            $html .= "Server Preset";
            $html .= '<select name="PRESET_ID">';
            foreach ($acswuiDatabase->fetch_2d_array("ServerPresets", ['Id', "Name"], [], "Name") as $sp) {
                $selected = ($this->CurrentPresetId == $sp['Id']) ? "selected" : "";
                $html .= '<option value="' . $sp['Id'] . '"' . $selected . '>' . $sp['Name'] . '</option>';
            }
            $html .= '</select>';
            $html .= '<br>';

            # car class
            $html .= "Car Class";
            $html .= '<select name="CARCLASS_ID">';
            foreach (CarClass::listClasses() as $carclass) {
                $selected = ($this->CurrentCarClassId == $carclass->id()) ? "selected" : "";
                $html .= '<option value="' . $carclass->id() . '"' . $selected . '>' . $carclass->name() . '</option>';
            }
            $html .= '</select>';
            $html .= '<br>';

            # track
            $html .= "Track";
            $html .= '<select name="TRACK_ID">';
            foreach (Track::listTracks() as $t) {
                $selected = ($this->CurrentTrackId == $t->id()) ? "selected" : "";
                $name_str = $t->name();
                $name_str .= " (" . sprintf("%0.1f", $t->length()/1000) . "km";
                $name_str .= ", " . $t->pitboxes() . "pits)";
                $html .= '<option value="' . $t->id() . '"' . $selected . ">" . $name_str . "</option>";
            }
            $html .= '</select>';
            $html .= '<br>';

            # start
            $html .= '<button type="submit" name="ACTION" value="START_SERVER">' . _("Start Server") . '</button>';



        } else {
            $html .= '<button type="submit" name="ACTION" value="STOP_SERVER">' . _("Stop Server") . '</button>';
        }
        $html .= '</form>';


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


    public function stop_server() {
        if (!$this->server_online()) return;

        $old_path = getcwd();
        $cmd_str = array();
        $cmd_ret = 0;
        $cmd = "pgrep acServer";
        exec($cmd, $cmd_str, $cmd_ret);
        if ($cmd_ret == 0) {
            $cmd = "kill " . $cmd_str[0];
            exec($cmd, $cmd_str, $cmd_ret);
        }
        sleep(2);
    }


    public function start_server() {
        global $acswuiConfig;
        global $acswuiLog;
        global $acswuiDatabase;

        if ($this->server_online()) return;


        // --------------------------------------------------------------------
        //                        server_cfg.ini
        // --------------------------------------------------------------------

        // open server_cfg.ini for writing
        $server_cfg_path = $acswuiConfig->AcServerPath . "/cfg/server_cfg.ini";
        $server_cfg_fd = fopen($server_cfg_path, 'w');
        if ($server_cfg_fd === False) {
            $acswuiLog->logError("Cannot open '$server_cfg_path' for writing!");
            return;
        }

        // get server_cfg data
        $server_cfg_data = $this->getPresetData();

        // determine track
        $track = new Track($this->CurrentTrackId);
        $server_cfg_data['SERVER']['TRACK'] = $track->track();
        $server_cfg_data['SERVER']['CONFIG_TRACK'] = $track->Config();

        // determine cars
        $carclass = new CarClass($this->CurrentCarClassId);
        $car_names_list = [];
        foreach ($carclass->cars() as $car) {
            $car_names_list[] = $car->model();
        }
        $server_cfg_data['SERVER']['CARS'] = implode(";", $car_names_list);


        // write server_cfg
        foreach ($server_cfg_data as $section_name => $section_data) {
            if ($section_name == "WEATHER") $section_name .= "_0";
            fwrite($server_cfg_fd, "[$section_name]\n");

            foreach ($section_data as $tag_name => $tag_value) {
                fwrite($server_cfg_fd, "$tag_name=$tag_value\n");
            }

            fwrite($server_cfg_fd, "\n");
        }

        // finish creating server_cfg.ini
        fclose($server_cfg_fd);



        // --------------------------------------------------------------------
        //                        entry_list.ini
        // --------------------------------------------------------------------

        // open entry_list.ini for writing
        $entry_list_path = $acswuiConfig->AcServerPath . "/cfg/entry_list.ini";
        $entry_list_fd = fopen($entry_list_path, 'w');
        if ($entry_list_fd === False) {
            $acswuiLog->logError("Cannot open '$entry_list_path' for writing!");
            return;
        }

        $entries = new CarEntries($carclass->cars());
        for ($entry_idx = 0; $entry_idx < $track->pitboxes(); ++$entry_idx) {

            $car_name = $car_names_list[$entry_idx % count($car_names_list)];

            $entry = $entries->getNextEntry();

            fwrite($entry_list_fd, "[CAR_$entry_idx]\n");
            fwrite($entry_list_fd, "MODEL=" . $entry->model() . "\n");
            fwrite($entry_list_fd, "SKIN=" . $entry->skin() . "\n");
            fwrite($entry_list_fd, "SPECTATOR_MODE=0\n");
            fwrite($entry_list_fd, "DRIVERNAME=\n");
            fwrite($entry_list_fd, "TEAM=\n");
            fwrite($entry_list_fd, "GUID=\n");
            fwrite($entry_list_fd, "BALLAST=0\n");
            fwrite($entry_list_fd, "RESTRICTOR=0\n");
            fwrite($entry_list_fd, "\n");
        }


        // finish creating entry_list.ini
        fclose($entry_list_fd);



        // --------------------------------------------------------------------
        //                        Start Server
        // --------------------------------------------------------------------

        $old_path = getcwd();
        $cmd_str = array();
        $cmd_ret = 0;
        $cmd = "nohup ". $acswuiConfig->AcswuiCmd . " -vvvvvv srvrun";
        $cmd .= " --db-host \"" . $acswuiConfig->DbHost . "\"";
        $cmd .= " --db-port \"" . $acswuiConfig->DbPort . "\"";
        $cmd .= " --db-database \"" . $acswuiConfig->DbDatabase . "\"";
        $cmd .= " --db-user \"" . $acswuiConfig->DbUser . "\"";
        $cmd .= " --db-password \"" . $acswuiConfig->DbPasswd . "\"";
        $cmd .= " --path-acs \"" . $acswuiConfig->AcServerPath . "\"";
        $cmd .= " --acs-log \"" . $acswuiConfig->LogPath . "/acserver.log\"";
        $cmd .= " --path-server-cfg \"" . $acswuiConfig->AcServerPath . "/cfg/server_cfg.ini\"";
        $cmd .= " --path-entry-list \"" . $acswuiConfig->AcServerPath . "/cfg/entry_list.ini\"";
//         $cmd .= " </dev/null >" . $acswuiConfig->LogPath . "/acswui_srvrun.log 2>&1 &";
        $cmd .= " >/dev/null 2>&1 &";
        exec($cmd, $cmd_str, $cmd_ret);
//         foreach ($cmd_str as $line) echo "$line<br>";
//         echo "Server started: $cmd_ret<br>";
//         echo htmlentities($cmd) ."<br>";

        sleep(2);

        if ($cmd_ret !== 0) {
            $msg = "Could not start server!\n";
            $msg .= "CMD: $cmd\n";
            $acswuiLog->logError($msg);
        }

    }
}

?>
