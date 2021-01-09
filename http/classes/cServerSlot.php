<?php

class ServerSlot {
    private $Id = NULL;
    private $ServerSlot = NULL;
    private static $CommonFixedSettingTags = NULL;

    public function __construct(int $server_slot_id) {
        global $acswuiConfig;
        global $acswuiLog;

        // check valid server slot id
        $server_slot_id = (int) $server_slot_id;
        if (count($acswuiConfig->ServerSlots) <= $server_slot_id) {
            $acswuiLog->logError("Invalid server slot id " . $server_slot_id);
            $this->ServerSlot = NULL;
            return;
        }

        $this->Id = $server_slot_id;
        $this->ServerSlot = $acswuiConfig->ServerSlots[$this->Id];
    }

    //! @return A list of available ServerSlot objects
    public static function listSlots() {
        global $acswuiConfig;

        $ssl = array();
        for ($i = 0; $i < count($acswuiConfig->ServerSlots); ++$i) {
            $ssl[] = new ServerSlot($i);
        }
        return $ssl;
    }


    //! @return A string list with all db column names of the ServerPresets table which present in all server slots
    public static function listCommonFixedSettingTags() {
        global $acswuiConfig;

        if (ServerSlot::$CommonFixedSettingTags !== NULL) return ServerSlot::$CommonFixedSettingTags;

        ServerSlot::$CommonFixedSettingTags = array();

        // list is empty when no slots are defined
        if (count($acswuiConfig->ServerSlots) == 0)
            return ServerSlot::$CommonFixedSettingTags;

        // iterate over all tags from the first server slot
        // when this is present in all other slots, put it to the list
        foreach (array_keys($acswuiConfig->ServerSlots[0]) as $tag) {
            $in_all_presets = TRUE;
            for ($slot = 1; $slot < count($acswuiConfig->ServerSlots); ++$slot) {
                if (!array_key_exists($tag, $acswuiConfig->ServerSlots[$slot])) {
                    $in_all_presets = FALSE;
                    break;
                }
            }
            if ($in_all_presets === TRUE) {
                ServerSlot::$CommonFixedSettingTags[] = $tag;
            }
        }

        return ServerSlot::$CommonFixedSettingTags;
    }

    public function id() {
        return $this->Id;
    }

    public function name() {
        if (array_key_exists("SERVER_NAME", $this->ServerSlot)) return $this->ServerSlot['SERVER_NAME'];
        else return "Slot-" . $this->Id;
    }


    /**
     * @return True if server is online
     */
    public function online() {
        $pid = $this->pid();
        if ($pid === NULL) return FALSE;
        exec("ps $pid > /dev/null", $cmd_str, $cmd_ret);
        if ($cmd_ret === 0) return TRUE;
        return FALSE;
    }


    //! @return The process ID of the server or NULL
    private function pid() {
        global $acswuiConfig;
        $id = $this->Id;
        $pidfile = $acswuiConfig->AcServerPath . "/acServer$id.pid";
        if (!file_exists($pidfile)) return NULL;
        $pid = (int) file_get_contents($pidfile);
        return $pid;
    }


    /**
     * Stop the server
     */
    public function stop() {
        $pid = $this->pid();
        if ($pid === NULL) return FALSE;
        exec("kill $pid");
        sleep(2);
    }


    /**
     * Starts the server
     */
    public function start(ServerPreset $preset, CarClass $carclass, Track $track) {
        global $acswuiConfig;
        $id = $this->Id;

        // entry_list.ini
        $entry_list_path = $acswuiConfig->AcServerPath . "/cfg/entry_list_$id.ini";
        $entry_list = new EntryList($carclass, $track);
        $entry_list->writeToFile($entry_list_path);

        // server_cfg.ini
        $server_cfg_path = $acswuiConfig->AcServerPath . "/cfg/server_cfg_$id.ini";
        $this->writeServerCfg($server_cfg_path, $preset, $carclass, $track);

        // path for storing realtime data
        $realtime_json_path = $acswuiConfig->AcsContentAbsolute . "/realtime_$id.json";

        // start server
        $cmd_str = array();
        $cmd_ret = 0;
        $cmd = "nohup ". $acswuiConfig->AcswuiCmd . " -vvv srvrun";
        $cmd .= " --name-acs \"acServer$id\"";
        $cmd .= " --db-host \"" . $acswuiConfig->DbHost . "\"";
        $cmd .= " --db-port \"" . $acswuiConfig->DbPort . "\"";
        $cmd .= " --db-database \"" . $acswuiConfig->DbDatabase . "\"";
        $cmd .= " --db-user \"" . $acswuiConfig->DbUser . "\"";
        $cmd .= " --db-password \"" . $acswuiConfig->DbPasswd . "\"";
        $cmd .= " --path-acs-target \"" . $acswuiConfig->AcServerPath . "\"";
        $cmd .= " --acs-log \"" . $acswuiConfig->LogPath . "/acserver_$id.log\"";
        $cmd .= " --path-server-cfg \"$server_cfg_path\"";
        $cmd .= " --path-entry-list \"$entry_list_path\"";
        $cmd .= " --path-realtime-json \"$realtime_json_path\"";
//         $cmd .= " </dev/null >" . $acswuiConfig->LogPath . "/acswui_srvrun.log 2>&1 &";
//         $cmd .= " >/dev/null 2>&1 &";
        $cmd .= " >" . $acswuiConfig->LogPath . "/acswui_srvrun_$id.log 2>&1 &";
        exec($cmd, $cmd_str, $cmd_ret);
        foreach ($cmd_str as $line) echo "$line<br>";
//         echo "Server started: $cmd_ret<br>";
//         echo htmlentities($cmd) ."<br>";

        sleep(2);

        if ($cmd_ret !== 0) {
            $msg = "Could not start server!\n";
            $msg .= "CMD: $cmd\n";
            $acswuiLog->logError($msg);
        }
    }


    private function writeServerCfg($filepath, ServerPreset $preset, CarClass $carclass, Track $track) {
        global $acswuiConfig;
        global $acswuiLog;

        // open file
        $fd = fopen($filepath, 'w');
        if ($fd === False) {
            $acswuiLog->logError("Cannot open '$filepath' for writing!");
            return;
        }

        // section SERVER
        $section = $preset->getSection("SERVER");
        $this->writeServerCfgSection($fd, $section);
        $car_models = array();
        foreach ($carclass->cars() as $car) {
            $car_models[] = $car->model();
        };
        fwrite($fd, "CARS=" . join(";", $car_models) . "\n");
        fwrite($fd, "TRACK=" . $track->track() . "\n");
        fwrite($fd, "CONFIG_TRACK=" . $track->config() . "\n");
        fwrite($fd, "\n\n");

        // section FTP
        $section = $preset->getSection("FTP");
        $this->writeServerCfgSection($fd, $section);

        // section BOOKING
        $section = $preset->getSection("BOOKING");
        $this->writeServerCfgSection($fd, $section);

        // section PRACTICE
        $section = $preset->getSection("PRACTICE");
        $this->writeServerCfgSection($fd, $section);

        // section QUALIFY
        $section = $preset->getSection("QUALIFY");
        $this->writeServerCfgSection($fd, $section);

        // section RACE
        $section = $preset->getSection("RACE");
        $this->writeServerCfgSection($fd, $section);

        // section DYNAMIC_TRACK
        $section = $preset->getSection("DYNAMIC_TRACK");
        $this->writeServerCfgSection($fd, $section);

        // section WEATHER_0
        $section = $preset->getSection("WEATHER_0");
        $this->writeServerCfgSection($fd, $section);

        // close file
        fclose($fd);
    }


    private function writeServerCfgSection($fd, $section) {
        global $acswuiConfig;

        $sectag = $section->tag();
        fwrite($fd, "[$sectag]\n");

        foreach ($section->fieldsets() as $fieldset) {
            foreach ($fieldset->fields() as $field) {
                $key = $field->tag();
                $value = $field->current();

                // check overwrite by server slot
                if (array_key_exists($field->dbColumn(), $acswuiConfig->ServerSlots[$this->Id])) {
                    $value = $acswuiConfig->ServerSlots[$this->Id][$field->dbColumn()];
                }
                fwrite($fd, "$key=$value\n");
            }
        }

        fwrite($fd, "\n");
    }
}

?>
