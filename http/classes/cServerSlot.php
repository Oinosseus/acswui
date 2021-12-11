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



    public function __toString() {
        return "ServerSlot(Id=" . $this->Id . ")";
    }



    //! @return The according Session object that currently runs on the slot (can be NULL)
    public function currentSession() {
        global $acswuiDatabase;

        $session = NULL;

        if ($this->online()) {

            // find last session on this slot
            $query = "SELECT `Id` FROM `Sessions` WHERE `ServerSlot` = " . $this->Id . " ORDER BY `Id` DESC LIMIT 1;";
            $res = $acswuiDatabase->fetch_raw_select($query);

            if (count($res) == 1) {
                $session = new Session($res[0]['Id']);
            }
        }

        return $session;
    }



    //! @return An array of User objects that are currently online
    public function driversOnline() {
        global $acswuiDatabase;

        $drivers = array();

        // get current session of this slot
        $session = $this->currentSession();
        if ($session === NULL) return $drivers;

        $res = $acswuiDatabase->fetch_2d_array("Users", ['Id'], ['CurrentSession'=>$session->id()]);
        foreach ($res as $row) {
            $drivers[] = new User($row['Id']);
        }

        return $drivers;
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
        $pidfile = $acswuiConfig->AbsPathData . "/acserver/acServer$id.pid";
        if (!file_exists($pidfile)) return NULL;
        $pid = (int) file_get_contents($pidfile);
        return $pid;
    }


    /**
     * Stop the server
     * When drivers are currently online, this will not work (except forced)
     * When current session is race, this will not work (except forced)
     * @param $force If TRUE, online drivers or race sessions are ignored (default=FALSE)
     */
    public function stop(bool $force = FALSE) {
        global $acswuiLog;

        // check if server is running
        $pid = $this->pid();
        if (!$force && $pid === NULL) {
            $acswuiLog->logWarning("Ignore stopping of not running server slot");
            return;
        }

        // check if drivers are online
        if (!$force && count($this->driversOnline()) > 0) {
            $acswuiLog->logWarning("Ignore stopping of server slot with online drivers");
            return;
        }

        // check if current session is race
        $session = $this->currentSession();
        if (!$force && $session !== NULL && $session->type() == 3) {
            $acswuiLog->logWarning("Ignore stopping of server slot with race session");
            return;
        }


        exec("kill $pid");
        sleep(3);
    }


    /**
     * Starts the server
     * @param $seat_occupations When set to FALSE, seat occupations are ignored (default = TRUE)
     */
    public function start(ServerPreset $preset, CarClass $carclass, Track $track, bool $seat_occupations = TRUE) {
        global $acswuiConfig;
        $id = $this->Id;

        // entry_list.ini
        $entry_list_path = $acswuiConfig->AbsPathData . "/acserver/cfg/entry_list_$id.ini";
        $entry_list = new EntryList($carclass, $track, $seat_occupations);
        $entry_list->writeToFile($entry_list_path);

        // server_cfg.ini
        $server_cfg_path = $acswuiConfig->AbsPathData . "/acserver/cfg/server_cfg_$id.ini";
        $this->writeServerCfg($server_cfg_path, $preset, $carclass, $track);

        // path for storing realtime data
        $realtime_json_path = $acswuiConfig->AbsPathData . "/htcache/realtime_$id.json";

        // start server
        $cmd_str = array();
        $cmd_ret = 0;
        $cmd = "nohup ". $acswuiConfig->AbsPathAcswui . "/acswui.py -vvvvvv srvrun ";
        $cmd .= " \"" . $acswuiConfig->AbsPathData . "/acswui.ini\" ";
        $cmd .= " --slot $id";
        $cmd .= " >" . $acswuiConfig->AbsPathData . "/logs_acserver/slot_$id.log 2>&1 &";
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
        $this->writeServerCfgSection($fd, $section, $carclass);
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
        $this->writeServerCfgSection($fd, $section, $carclass);

        // section BOOKING
        $section = $preset->getSection("BOOKING");
        if ($section->currentValue("TIME") != 0)
            $this->writeServerCfgSection($fd, $section, $carclass);

        // section PRACTICE
        $section = $preset->getSection("PRACTICE");
        if ($section->currentValue("TIME") != 0)
            $this->writeServerCfgSection($fd, $section, $carclass);

        // section QUALIFY
        $section = $preset->getSection("QUALIFY");
        if ($section->currentValue("TIME") != 0)
            $this->writeServerCfgSection($fd, $section, $carclass);

        // section RACE
        $section = $preset->getSection("RACE");
        if ($section->currentValue("TIME") != 0 || $section->currentValue("LAPS") != 0)
            $this->writeServerCfgSection($fd, $section, $carclass);

        // section DYNAMIC_TRACK
        $section = $preset->getSection("DYNAMIC_TRACK");
        $this->writeServerCfgSection($fd, $section, $carclass);

        // section WEATHER_0
        $section = $preset->getSection("WEATHER_0");
        $this->writeServerCfgSection($fd, $section, $carclass);

        // section ACSWUI
        fwrite($fd, "[ACSWUI]\n");
        fwrite($fd, "SERVER_SLOT=" . $this->Id . "\n");
        fwrite($fd, "SERVER_PRESET=" . $preset->id() . "\n");
        fwrite($fd, "CAR_CLASS=" . $carclass->id() . "\n");
        fwrite($fd, "\n");


        // close file
        fclose($fd);
    }


    private function writeServerCfgSection($fd, $section, $carclass) {
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

                // catch handling for WELCOME_MESSAGE
                if ($sectag == "SERVER" && $key == "WELCOME_MESSAGE") {
                    $id = $this->Id;
                    $welcome_path = $acswuiConfig->AbsPathData . "/acserver/cfg/welcome_$id.txt";
                    $welcome_fd = fopen($welcome_path, 'w');
                    fwrite($welcome_fd, $value);
                    fclose($welcome_fd);
                    $value = $welcome_path;
                }

                // catch allowed tyres from car class
                if ($sectag == "SERVER" && $key == "LEGAL_TYRES" && $carclass->allowedTyres() != "") {
                    $value = $carclass->allowedTyres();
                }

                fwrite($fd, "$key=$value\n");
            }
        }

        fwrite($fd, "\n");
    }
}

?>
