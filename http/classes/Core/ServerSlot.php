<?php

namespace Core;

class ServerSlot {

    private static $SlotObjectCache = array();  // key=slot-id, value=ServerSlot-object

    private $Id = NULL;
    private $ParameterCollection = NULL;

    /**
     * Construct a new object
     * @param $id Database table id
     */
    private function __construct() {
    }


    //! @return An array of ServerPreset objects that are children of this preset
    public function children() {
        if ($this->ChildSlots === NULL) {
            $this->ChildSlots = array();

            $res = \Core\Database::fetch("ServerSlots", ['Id'], ['Parent'=>$this->id()], 'Name');
            foreach ($res as $row) {
                $this->ChildSlots[] = ServerSlot::fromId($row['Id']);
            }
        }
        return $this->ChildSlots;
    }


    //! @return An array of User objects that are currently online
    public function driversOnline() {
        return array();

        //! @todo Implement me when Session class is available

//         $drivers = array();
//
//         // get current session of this slot
//         $session = $this->currentSession();
//         if ($session === NULL) return $drivers;
//
//         $res = $acswuiDatabase->fetch_2d_array("Users", ['Id'], ['CurrentSession'=>$session->id()]);
//         foreach ($res as $row) {
//             $drivers[] = new User($row['Id']);
//         }
//
//         return $drivers;
    }


    //! @return A ServerSlot object, retreived by Slot-ID ($id=0 will return a base preset)
    public static function fromId(int $slot_id) {
        $ss = NULL;

        if ($slot_id > \Core\Config::ServerSlotAmount) {
            \Core\Log::error("Deny requesting slot-id '$slot_id' at maximum slot amount of '" . \Core\Config::ServerSlotAmount . "'!");
            return NULL;
        } else if ($slot_id < 0) {
            \Core\Log::error("Deny requesting negative slot-id '$slot_id'!");
            return NULL;
        } else if (array_key_exists($slot_id, ServerSlot::$SlotObjectCache)) {
            $ss = ServerSlot::$SlotObjectCache[$slot_id];
        } else {
            $ss = new ServerSlot();
            $ss->Id = $slot_id;
            ServerSlot::$SlotObjectCache[$ss->id()] = $ss;
        }

        return $ss;
    }


    //! @return The ID of the slot (number)
    public function id() {
        return $this->Id;
    }


    //! @return The name of the preset
    public function name() {
        if ($this->id() === 0) return _("Base Settings");
        else return "Slot " . $this->id();
    }


    //! @return The Collection object, that stores all parameters
    public function parameterCollection() {
        if ($this->ParameterCollection === NULL) {

            // create parameter collection
            if ($this->id() !== 0) {
                $base_collection = ServerSlot::fromId(0)->parameterCollection();
                $this->ParameterCollection = new \Parameter\Collection($base_collection, NULL);

            } else {
                $root_collection = new \Parameter\Collection(NULL, NULL, "ServerSlot", _("Server Slot"), _("Collection of server slot settings"));


                ////////////
                // acServer

                $pc = new \Parameter\Collection(NULL, $root_collection, "AcServer", _("acServer"), _("Settings for the actual acServer"));

                // genral
                $coll = new \Parameter\Collection(NULL, $pc, "General", _("General Settings"), _("General settings for Real Penalty"));
                $p = new \Parameter\ParamString(NULL, $coll, "Name", _("Name"), _("An arbitrary name for the server (shown in lobby)"), "", "");

                // ports
                $coll = new \Parameter\Collection(NULL, $pc, "PortsInet", _("Internet Ports"), _("Internet protocol port numbers for the AC server"));
                $p = new \Parameter\ParamInt(NULL, $coll, "UDP", "UDP", _("UDP port number: open this port on your server's firewall"), "", 9101);
                $p->setMin(1024);
                $p->setMax(65535);
                $p = new \Parameter\ParamInt(NULL, $coll, "TCP", "TCP", _("TCP port number: open this port on your server's firewall"), "", 9101);
                $p->setMin(1024);
                $p->setMax(65535);
                $p = new \Parameter\ParamInt(NULL, $coll, "HTTP", "HTTP", _("Lobby port number: open these ports (both UDP and TCP) on your server's firewall"), "", 9100);
                $p->setMin(1024);
                $p->setMax(65535);

                // plugin ports
                $coll = new \Parameter\Collection(NULL, $pc, "PortsPlugin", _("Plugin Ports"), _("UDP plugin port settings"));

                $p = new \Parameter\ParamInt(NULL, $coll, "UDP_R", "UDP_R", _("Remote UDP port for external plugins"), "", 9102);
                $p->setMin(1024);
                $p->setMax(65535);

                // performance
                $coll = new \Parameter\Collection(NULL, $pc, "Performance", _("Performance"), _("Settings that affect the transfer performance / quality"));
                $p = new \Parameter\ParamInt(NULL, $coll, "ClntIntvl", _("Client Interval"), _("Refresh rate of packet sending by the server. 10Hz = ~100ms. Higher number = higher MP quality = higher bandwidth resources needed. Really high values can create connection issues"), "Hz", 15);
                $p->setMin(1);
                $p->setMax(100);
                $p = new \Parameter\ParamInt(NULL, $coll, "Threads", _("Number of Threads"), _("Number of threads to run on"), "", 2);
                $p->setMin(1);
                $p->setMax(64);
                $p = new \Parameter\ParamInt(NULL, $coll, "MaxClients", _("Max Clients"), _("Max number of clients"), "", 25);
                $p->setMin(1);
                $p->setMax(999);


                ////////////////
                // Real Penalty

                $pc = new \Parameter\Collection(NULL, $root_collection, "RP", _("Real Penalty Plugin"), _("Settings for the Real Penalty plugin"));

                // genral
                $coll = new \Parameter\Collection(NULL, $pc, "General", _("General Settings"), _("General settings for Real Penalty"));

                $p = new \Parameter\ParamBool(NULL, $coll, "Enable", _("Enable RP"), _("Wheather to use Real Penalty for this slot or not"), "", FALSE);

                $p = new \Parameter\ParamString(NULL, $coll, "ProductKey", _("Product Key"), _("Personal key, received after the Patreon subscription: https://www.patreon.com/DavideBolognesi"), "", "");

                $p = new \Parameter\ParamString(NULL, $coll, "AdminPwd", _("Admin Passwort"), _("Password only for app 'Real Penalty - admin'"), "", "");

                // plugin ports
                $coll = new \Parameter\Collection(NULL, $pc, "PortsPlugin", _("Plugin Ports"), _("UDP plugin port settings"));

                $p = new \Parameter\ParamInt(NULL, $coll, "UDP_L", "UDP_L", _("Local UDP port to communicate with the acServer"), "", 9103);
                $p->setMin(1024);
                $p->setMax(65535);

                $p = new \Parameter\ParamInt(NULL, $coll, "UDP_R", "UDP_R", _("Remote UDP port for additional plugins"), "", 9104);
                $p->setMin(1024);
                $p->setMax(65535);

                // internet ports
                $coll = new \Parameter\Collection(NULL, $pc, "PortsInet", _("Internet Ports"), _("Port settings open to the internet"));

                $p = new \Parameter\ParamInt(NULL, $coll, "UDP", "UDP", _("UDP to communicate with RP client app"), "", 9105);
                $p->setMin(1024);
                $p->setMax(65535);


                /////////////////
                // ACswui Plugin

                $pc = new \Parameter\Collection(NULL, $root_collection, "ACswui", _("ACswui Plugin"), _("Settings for the ACswui plugin"));

                // plugin ports
                $coll = new \Parameter\Collection(NULL, $pc, "PortsPlugin", _("Plugin Ports"), _("UDP plugin port settings"));

                $p = new \Parameter\ParamInt(NULL, $coll, "UDP_L", "UDP_L", _("Local UDP port to communicate with the acServer"), "", 9106);
                $p->setMin(1024);
                $p->setMax(65535);



                // set all deriveable and visible
                function __adjust_derived_collection($collection) {
                    $collection->derivedAccessability(2);
                    foreach ($collection->children() as $child) {
                        __adjust_derived_collection($child);
                    }
                }
                __adjust_derived_collection($root_collection);

                // derive base collection from (invisible) root collection
                $this->ParameterCollection = new \Parameter\Collection($root_collection, NULL);
            }

            // load data from disk
            $file_path = \Core\Config::AbsPathData . "/server_slots/" . $this->id() . ".json";
            if (file_exists($file_path)) {
                $ret = file_get_contents($file_path);
                if ($ret === FALSE) {
                    \Core\Log::error("Cannot read from file '$file_path'!");
                } else {
                    $data_array = json_decode($ret, TRUE);
                    if ($data_array == NULL) {
                        \Core\Log::warning("Decoding NULL from json file '$file_path'.");
                    } else {
                        $this->parameterCollection()->dataArrayImport($data_array);
                    }
                }
            }
        }

        return $this->ParameterCollection;
    }



    //! @return True if server is online
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
        $id = $this->id();
        $pidfile = \Core\Config::AbsPathData . "/acserver/acServer$id.pid";
        if (!file_exists($pidfile)) return NULL;
        $pid = (int) file_get_contents($pidfile);
        return $pid;
    }



    //! Store settings to database
    public function save() {

        // prepare data
        $data_array = $this->parameterCollection()->dataArrayExport();
        $data_json = json_encode($data_array);

        // write to file
        $file_path = \Core\Config::AbsPathData . "/server_slots/" . $this->id() . ".json";
        $f = fopen($file_path, 'w');
        if ($f === FALSE) {
            \Core\Log::error("Cannot write to file '$file_path'!");
            return;
        }
        fwrite($f, $data_json);
        fclose($f);
    }


    //! start the server within this slot
    public function start() {
        $id = $this->id();

        // configure real penalty
        $this->writeRpAcSettings();
        $this->writeRpPenaltySettings();
        $this->writeRpSettings();

        // configure ACswui plugin
        $this->writeACswuiUdpPluginIni();

        // configure ac server
        $this->writeAcServerEntryList();
        $this->writeAcServerCfg();

        // lunch server with plugins
        $ac_server = \Core\Config::AbsPathData . "/acserver/acServer$id";
        $server_cfg = \Core\Config::AbsPathData . "/acserver/cfg/server_cfg_$id.ini";
        $entry_list = \Core\Config::AbsPathData . "/acserver/cfg/entry_list_$id.ini";
        $log_output = \Core\Config::AbsPathData . "/logs_acserver/acServer$id.log";
        $ac_server_command = "$ac_server -c $server_cfg -e $entry_list > $log_output 2>&1";


        // start server
        $cmd_ret = 0;
        $cmd = "nohup ". \Core\Config::AbsPathAcswui . "/acswui.py srvrun -vv";
        $cmd .= " \"" . \Core\Config::AbsPathData . "/acswui_udp_plugin/acswui_udp_plugin_$id.ini\" ";
        $cmd .= " --slot $id";
        if ($this->parameterCollection()->child("RP", "General", "Enable")->value()) {
            $cmd .= " --real-penalty";
        }
        $cmd .= " >" . \Core\Config::AbsPathData . "/logs_srvrun//slot_$id.srvrun.log 2>&1 &";
        $cmd_retstr = array();
        exec($cmd, $cmd_retstr, $cmd_ret);
        foreach ($cmd_retstr as $line) echo "$line<br>";
//         echo "Server started: $cmd_ret<br>";
//         echo htmlentities($cmd) ."<br>";

        sleep(1);

        if ($cmd_ret !== 0) {
            $msg = "Could not start server!\n";
            $msg .= "CMD: $cmd\n";
            \Core\Log::error($msg);
        }
    }


    /**
     * Stop the server
     * When drivers are currently online, this will not work (except forced)
     * When current session is race, this will not work (except forced)
     * @param $force If TRUE, online drivers or race sessions are ignored (default=FALSE)
     */
    public function stop(bool $force = FALSE) {

        // check if server is running
        $pid = $this->pid();
        if (!$force && $pid === NULL) {
            \Core\Log::warning("Ignore stopping of not running server slot");
            return;
        }

        // check if drivers are online
        if (!$force && count($this->driversOnline()) > 0) {
            \Core\Log::warning("Ignore stopping of server slot with online drivers");
            return;
        }

        //! @todo implement me when Sessions are available
        // check if current session is race
//         $session = $this->currentSession();
//         if (!$force && $session !== NULL && $session->type() == 3) {
//             $acswuiLog->logWarning("Ignore stopping of server slot with race session");
//             return;
//         }


        exec("kill $pid");
        sleep(1);
    }


    //! create acswui_udp_plugin.ini
    private function writeACswuiUdpPluginIni() {
        $pc = $this->parameterCollection();
        $file_path = \Core\Config::AbsPathData . "/acswui_udp_plugin/acswui_udp_plugin_" . $this->id() . ".ini";
        $f = fopen($file_path, 'w');
        if ($f === FALSE) {
            \Core\Log::error("Cannot write to file '$file_path'!");
            return;
        }

        fwrite($f, "[GENERAL]\n");
        fwrite($f, "db-host     = " . \Core\Config::DbHost . "\n");
        fwrite($f, "db-database = " . \Core\Config::DbDatabase . "\n");
        fwrite($f, "db-port     = " . \Core\Config::DbPort . "\n");
        fwrite($f, "db-user     = " . \Core\Config::DbUser . "\n");
        fwrite($f, "db-password = " . \Core\Config::DbPasswd . "\n");
        fwrite($f, "path-data = " . \Core\Config::AbsPathData . "\n");
        fwrite($f, "path-htdata = " . \Core\Config::AbsPathHtdata . "\n");

        fwrite($f, "\n[PLUGIN]\n");
        fwrite($f, "slot = " . $this->id() . "\n");
        fwrite($f, "preset = 0\n");
        fwrite($f, "carclass = 0\n");
        fwrite($f, "udp_plugin = " . $pc->child("ACswui", "PortsPlugin", "UDP_L")->value() . "\n");
        if ($pc->child("RP", "General", "Enable")->value()) {
            fwrite($f, "udp_acserver = " . $pc->child("RP", "PortsPlugin", "UDP_R")->value() . "\n");
        } else {
            fwrite($f, "udp_acserver = " . $pc->child("AcServer", "PortsPlugin", "UDP_R")->value() . "\n");
        }


        fclose($f);
    }


    //! create server_cfg.ini for real penalty
    private function writeAcServerCfg() {
        $pc = $this->parameterCollection();
        $file_path = \Core\Config::AbsPathData . "/acserver/cfg/server_cfg_" . $this->id() . ".ini";
        $f = fopen($file_path, 'w');
        if ($f === FALSE) {
            \Core\Log::error("Cannot write to file '$file_path'!");
            return;
        }

        fwrite($f, "[SERVER]\n");
        fwrite($f, "NAME=" . $pc->child("AcServer", "General", "Name")->value() . "\n");
        fwrite($f, "PASSWORD=\n");
        fwrite($f, "ADMIN_PASSWORD=\n");
        fwrite($f, "UDP_PORT=" . $pc->child("AcServer", "PortsInet", "UDP")->value() . "\n");
        fwrite($f, "TCP_PORT=" . $pc->child("AcServer", "PortsInet", "TCP")->value() . "\n");
        fwrite($f, "HTTP_PORT=" . $pc->child("AcServer", "PortsInet", "HTTP")->value() . "\n");
        fwrite($f, "SEND_BUFFER_SIZE=0\n");
        fwrite($f, "RECV_BUFFER_SIZE=0\n");
        fwrite($f, "CLIENT_SEND_INTERVAL_HZ=" . $pc->child("AcServer", "Performance", "ClntIntvl")->value() . "\n");
        fwrite($f, "NUM_THREADS=" . $pc->child("AcServer", "Performance", "Threads")->value() . "\n");
        fwrite($f, "SLEEP_TIME=1\n");
        fwrite($f, "REGISTER_TO_LOBBY=1\n");
        fwrite($f, "MAX_CLIENTS=" . $pc->child("AcServer", "Performance", "MaxClients")->value() . "\n");
        fwrite($f, "WELCOME_MESSAGE=\n");
        fwrite($f, "PICKUP_MODE_ENABLED=1\n");
        fwrite($f, "LOOP_MODE=0\n");
        fwrite($f, "SUN_ANGLE=-45\n");
        fwrite($f, "QUALIFY_MAX_WAIT_PERC=100\n");
        fwrite($f, "LEGAL_TYRES=\n");
        fwrite($f, "MAX_BALLAST_KG=300\n");
        fwrite($f, "RACE_OVER_TIME=600\n");
        fwrite($f, "RACE_PIT_WINDOW_START=0\n");
        fwrite($f, "RACE_PIT_WINDOW_END=999\n");
        fwrite($f, "REVERSED_GRID_RACE_POSITIONS=0\n");
        fwrite($f, "LOCKED_ENTRY_LIST=0\n");
        fwrite($f, "START_RULE=2\n");
        fwrite($f, "RACE_GAS_PENALTY_DISABLED=0\n");
        fwrite($f, "TIME_OF_DAY_MULT=2\n");
        fwrite($f, "RESULT_SCREEN_TIME=60\n");
        fwrite($f, "MAX_CONTACTS_PER_KM=99\n");
        fwrite($f, "RACE_EXTRA_LAP=0\n");
        fwrite($f, "UDP_PLUGIN_LOCAL_PORT=" . $pc->child("AcServer", "PortsPlugin", "UDP_R")->value() . "\n");
        if ($pc->child("RP", "General", "Enable")->value()) {
            fwrite($f, "UDP_PLUGIN_ADDRESS=127.0.0.1:" . $pc->child("RP", "PortsPlugin", "UDP_L")->value() . "\n");
        } else {
            fwrite($f, "UDP_PLUGIN_ADDRESS=127.0.0.1:" . $pc->child("ACswui", "PortsPlugin", "UDP_L")->value() . "\n");
        }
        fwrite($f, "AUTH_PLUGIN_ADDRESS=\n");
        fwrite($f, "KICK_QUORUM=70\n");
        fwrite($f, "BLACKLIST_MODE=1\n");
        fwrite($f, "VOTING_QUORUM=70\n");
        fwrite($f, "VOTE_DURATION=30\n");
        fwrite($f, "FUEL_RATE=100\n");
        fwrite($f, "DAMAGE_MULTIPLIER=100\n");
        fwrite($f, "TYRE_WEAR_RATE=100\n");
        fwrite($f, "ALLOWED_TYRES_OUT=2\n");
        fwrite($f, "ABS_ALLOWED=1\n");
        fwrite($f, "TC_ALLOWED=1\n");
        fwrite($f, "STABILITY_ALLOWED=0\n");
        fwrite($f, "AUTOCLUTCH_ALLOWED=0\n");
        fwrite($f, "TYRE_BLANKETS_ALLOWED=0\n");
        fwrite($f, "FORCE_VIRTUAL_MIRROR=0\n");
        fwrite($f, "\n");
        fwrite($f, "CARS=wec_lmp2_cadillac_dpi;wec_lmp2_dallara_p217;wec_lmp2_ligierp217;wec_lmp2_onroak_nissan_dpi;wec_lmp2_oreca07\n");
        fwrite($f, "TRACK=imola\n");
        fwrite($f, "CONFIG_TRACK=\n");
        fwrite($f, "\n");
        fwrite($f, "\n");
        fwrite($f, "[FTP]\n");
        fwrite($f, "HOST=\n");
        fwrite($f, "LOGIN=\n");
        fwrite($f, "PASSWORD=\n");
        fwrite($f, "FOLDER=\n");
        fwrite($f, "LINUX=1\n");
        fwrite($f, "\n");
        fwrite($f, "[PRACTICE]\n");
        fwrite($f, "NAME=Practice\n");
        fwrite($f, "TIME=240\n");
        fwrite($f, "IS_OPEN=1\n");
        fwrite($f, "\n");
        fwrite($f, "[DYNAMIC_TRACK]\n");
        fwrite($f, "SESSION_START=96\n");
        fwrite($f, "RANDOMNESS=1\n");
        fwrite($f, "LAP_GAIN=50\n");
        fwrite($f, "SESSION_TRANSFER=80\n");
        fwrite($f, "\n");
        fwrite($f, "[WEATHER_0]\n");
        fwrite($f, "GRAPHICS=5\n");
        fwrite($f, "BASE_TEMPERATURE_AMBIENT=24\n");
        fwrite($f, "VARIATION_AMBIENT=4\n");
        fwrite($f, "BASE_TEMPERATURE_ROAD=10\n");
        fwrite($f, "VARIATION_ROAD=4\n");
        fwrite($f, "WIND_BASE_SPEED_MIN=0\n");
        fwrite($f, "WIND_BASE_SPEED_MAX=30\n");
        fwrite($f, "WIND_BASE_DIRECTION=0\n");
        fwrite($f, "WIND_VARIATION_DIRECTION=360\n");
        fwrite($f, "\n");
        fwrite($f, "[ACSWUI]\n");
        fwrite($f, "SERVER_SLOT=" . $this->id() . "\n");
        fwrite($f, "SERVER_PRESET=10\n");
        fwrite($f, "CAR_CLASS=70\n");


        fclose($f);
    }


    //! create entry_list.ini for real penalty
    private function writeAcServerEntryList() {
        $pc = $this->parameterCollection();
        $file_path = \Core\Config::AbsPathData . "/acserver/cfg/entry_list_" . $this->id() . ".ini";
        $f = fopen($file_path, 'w');
        if ($f === FALSE) {
            \Core\Log::error("Cannot write to file '$file_path'!");
            return;
        }

        fwrite($f, "[CAR_0]\n");
        fwrite($f, "MODEL=wec_lmp2_dallara_p217\n");
        fwrite($f, "SKIN=HighClassRacing\n");
        fwrite($f, "SPECTATOR_MODE=0\n");
        fwrite($f, "DRIVERNAME=\n");
        fwrite($f, "TEAM=\n");
        fwrite($f, "GUID=\n");
        fwrite($f, "BALLAST=0\n");
        fwrite($f, "RESTRICTOR=0\n");

        fwrite($f, "\n[CAR_1]\n");
        fwrite($f, "MODEL=wec_lmp2_cadillac_dpi\n");
        fwrite($f, "SKIN=ActionExpressRacing1\n");
        fwrite($f, "SPECTATOR_MODE=0\n");
        fwrite($f, "DRIVERNAME=\n");
        fwrite($f, "TEAM=\n");
        fwrite($f, "GUID=\n");
        fwrite($f, "BALLAST=0\n");
        fwrite($f, "RESTRICTOR=0\n");

        fwrite($f, "\n[CAR_2]\n");
        fwrite($f, "MODEL=wec_lmp2_oreca07\n");
        fwrite($f, "SKIN=26\n");
        fwrite($f, "SPECTATOR_MODE=0\n");
        fwrite($f, "DRIVERNAME=\n");
        fwrite($f, "TEAM=\n");
        fwrite($f, "GUID=\n");
        fwrite($f, "BALLAST=0\n");
        fwrite($f, "RESTRICTOR=0\n");

        fwrite($f, "\n[CAR_3]\n");
        fwrite($f, "MODEL=wec_lmp2_ligierp217\n");
        fwrite($f, "SKIN=MathiasenMotorsport\n");
        fwrite($f, "SPECTATOR_MODE=0\n");
        fwrite($f, "DRIVERNAME=\n");
        fwrite($f, "TEAM=\n");
        fwrite($f, "GUID=\n");
        fwrite($f, "BALLAST=0\n");
        fwrite($f, "RESTRICTOR=0\n");

        fwrite($f, "\n[CAR_4]\n");
        fwrite($f, "MODEL=wec_lmp2_onroak_nissan_dpi\n");
        fwrite($f, "SKIN=ESMRacing2\n");
        fwrite($f, "SPECTATOR_MODE=0\n");
        fwrite($f, "DRIVERNAME=\n");
        fwrite($f, "TEAM=\n");
        fwrite($f, "GUID=\n");
        fwrite($f, "BALLAST=0\n");
        fwrite($f, "RESTRICTOR=0\n");

        fclose($f);
    }


    //! create ac_settings.ini for real penalty
    private function writeRpAcSettings() {
        $pc = $this->parameterCollection();
        $file_path = \Core\Config::AbsPathData . "/real_penalty/" . $this->id() . "/ac_settings.ini";
        $f = fopen($file_path, 'w');
        if ($f === FALSE) {
            \Core\Log::error("Cannot write to file '$file_path'!");
            return;
        }

        // section General
        fwrite($f, "[General]\n");
        fwrite($f, "FIRST_CHECK_TIME = 5\n");
        fwrite($f, "COCKPIT_CAMERA = false\n");
        fwrite($f, "TRACK_CHECKSUM = false\n");
        fwrite($f, "WEATHER_CHECKSUM = false\n");
        fwrite($f, "CAR_CHECKSUM = false\n");
        fwrite($f, "qualify_time = _\n");

        // section App
        fwrite($f, "\n[App]\n");
        fwrite($f, "MANDATORY = false\n");
        fwrite($f, "CHECK_FREQUENCY = 60\n");

        // section Sol
        fwrite($f, "\n[Sol]\n");
        fwrite($f, "MANDATORY = false\n");
        fwrite($f, "CHECK_FREQUENCY = 60\n");

        // section Custom_Shaders_Patch
        fwrite($f, "\n[Custom_Shaders_Patch]\n");
        fwrite($f, "MANDATORY = false\n");
        fwrite($f, "CHECK_FREQUENCY = 60\n");

        // section Safety_Car
        fwrite($f, "\n[Safety_Car]\n");
        fwrite($f, "CAR_MODEL = XXXXX\n");
        fwrite($f, "RACE_START_BEHIND_SC = false\n");
        fwrite($f, "NORMALIZED_LIGHT_OFF_POSITION = 0.5\n");
        fwrite($f, "NORMALIZED_START_POSITION = 0.95\n");
        fwrite($f, "GREEN_LIGHT_DELAY = 2.5\n");

        // section No_Penalty
        fwrite($f, "\n[No_Penalty]\n");
        fwrite($f, "GUIDs =\n");
        fwrite($f, "Cars =\n");

        // section Admin
        fwrite($f, "\n[Admin]\n");
        fwrite($f, "GUIDs =\n");

        // section Helicorsa
        fwrite($f, "\n[Helicorsa]\n");
        fwrite($f, "MANDATORY = false\n");
        fwrite($f, "DISTANCE_THRESHOLD = 30.0\n");
        fwrite($f, "WORLD_ZOOM = 5.0\n");
        fwrite($f, "OPACITY_THRESHOLD = 8.0\n");
        fwrite($f, "FRONT_FADE_OUT_ARC = 90.0\n");
        fwrite($f, "FRONT_FADE_ANGLE = 10.0\n");
        fwrite($f, "CAR_LENGHT = 4.3\n");
        fwrite($f, "CAR_WIDTH = 1.8\n");

        // section Swap
        fwrite($f, "\n[Swap]\n");
        fwrite($f, "min_time = _\n");
        fwrite($f, "min_count = _\n");
        fwrite($f, "enable_penalty = false\n");
        fwrite($f, "penalty_time = 5\n");
        fwrite($f, "penalty_during_race = false\n");
        fwrite($f, "convert_time_penalty = false\n");
        fwrite($f, "convert_race_penalty = false\n");
        fwrite($f, "disqualify_time = _\n");
        fwrite($f, "count_only_driver_change = false\n");

        // section Teleport
        fwrite($f, "\n[Teleport]\n");
        fwrite($f, "max_distance = 10\n");
        fwrite($f, "practice_enable = true\n");
        fwrite($f, "qualify_enable = true\n");
        fwrite($f, "race_enable = true\n");

        fclose($f);
    }


    //! create penalty_settings.ini for real penalty
    private function writeRpPenaltySettings() {
        $pc = $this->parameterCollection();
        $file_path = \Core\Config::AbsPathData . "/real_penalty/" . $this->id() . "/penalty_settings.ini";
        $f = fopen($file_path, 'w');
        if ($f === FALSE) {
            \Core\Log::error("Cannot write to file '$file_path'!");
            return;
        }

        // section General
        fwrite($f, "[General]\n");
        fwrite($f, "ENABLE_CUTTING_PENALTIES = false\n");
        fwrite($f, "ENABLE_SPEEDING_PENALTIES = false\n");
        fwrite($f, "ENABLE_CROSSING_PENALTIES = false\n");
        fwrite($f, "ENABLE_DRS_PENALTIES = false\n");
        fwrite($f, "LAPS_TO_TAKE_PENALTY = 2\n");
        fwrite($f, "PENALTY_SECONDS = 0\n");
        fwrite($f, "LAST_TIME_WITHOUT_PENALTY = 360\n");
        fwrite($f, "LAST_LAPS_WITHOUT_PENALTY = 1\n");

        // section Cutting
        fwrite($f, "\n[Cutting]\n");
        fwrite($f, "ENABLED_DURING_SAFETY_CAR = false\n");
        fwrite($f, "TOTAL_CUT_WARNINGS = 3\n");
        fwrite($f, "ENABLE_TYRES_DIRT_LEVEL = false\n");
        fwrite($f, "WHEELS_OUT = 2\n");
        fwrite($f, "MIN_SPEED = 40\n");
        fwrite($f, "SECONDS_BETWEEN_CUTS = 5\n");
        fwrite($f, "MAX_CUT_TIME = 3\n");
        fwrite($f, "MIN_SLOW_DOWN_RATIO = 0.9\n");
        fwrite($f, "QUAL_SLOW_DOWN_SPEED = 40\n");
        fwrite($f, "QUALIFY_MAX_SECTOR_OUT_SPEED = 150\n");
        fwrite($f, "QUALIFY_SLOW_DOWN_SPEED_RATIO = 0.99\n");
        fwrite($f, "POST_CUTTING_TIME = 1\n");
        fwrite($f, "PENALTY_TYPE = dt\n");

        // section Speeding
        fwrite($f, "\n[Speeding]\n");
        fwrite($f, "PIT_LANE_SPEED = 82\n");
        fwrite($f, "PENALTY_TYPE_0 = dt\n");
        fwrite($f, "SPEED_LIMIT_PENALTY_0 = 100\n");
        fwrite($f, "PENALTY_TYPE_1 = sg10\n");
        fwrite($f, "SPEED_LIMIT_PENALTY_1 = 120\n");
        fwrite($f, "PENALTY_TYPE_2 = sg20\n");
        fwrite($f, "SPEED_LIMIT_PENALTY_2 = 140\n");

        // section Crossing
        fwrite($f, "\n[Crossing]\n");
        fwrite($f, "PENALTY_TYPE = dt\n");

        // section Jump_Start
        fwrite($f, "\n[Jump_Start]\n");
        fwrite($f, "PENALTY_TYPE_0 = dt\n");
        fwrite($f, "SPEED_LIMIT_PENALTY_0 = 50\n");
        fwrite($f, "PENALTY_TYPE_1 = sg10\n");
        fwrite($f, "SPEED_LIMIT_PENALTY_1 = 200\n");
        fwrite($f, "PENALTY_TYPE_2 = dsq\n");
        fwrite($f, "SPEED_LIMIT_PENALTY_2 = 9999\n");

        // section Drs
        fwrite($f, "\n[Drs]\n");
        fwrite($f, "PENALTY_TYPE = dt\n");
        fwrite($f, "GAP = 1.0\n");
        fwrite($f, "ENABLED_AFTER_LAPS = 0\n");
        fwrite($f, "MIN_SPEED = 50\n");
        fwrite($f, "BONUS_TIME = 0.8\n");
        fwrite($f, "MAX_ILLEGAL_USES = 2\n");
        fwrite($f, "ENABLED_DURING_SAFETY_CAR = true\n");
        fwrite($f, "OMIT_CARS =\n");

        // section Blue_Flag
        fwrite($f, "\n[Blue_Flag]\n");
        fwrite($f, "QUALIFY_TIME_THRESHOLD = 2.5\n");
        fwrite($f, "RACE_TIME_THRESHOLD = 2.5\n");


        fclose($f);
    }


    //! create settings.ini for real penalty
    private function writeRpSettings() {
        $pc = $this->parameterCollection();
        $file_path = \Core\Config::AbsPathData . "/real_penalty/" . $this->id() . "/settings.ini";
        $f = fopen($file_path, 'w');
        if ($f === FALSE) {
            \Core\Log::error("Cannot write to file '$file_path'!");
            return;
        }

        // section General
        fwrite($f, "[General]\n");
        fwrite($f, "product_key = " . $pc->child("RP", "General", "ProductKey")->value() . "\n");
        fwrite($f, "AC_SERVER_PATH = " . \Core\Config::AbsPathData . "/acserver\n");
        fwrite($f, "AC_CFG_FILE = " . \Core\Config::AbsPathData . "/acserver/cfg/server_cfg_0.ini\n");
        fwrite($f, "AC_TRACKS_FOLDER = " . \Core\Config::AbsPathData . "/acserver/content/tracks\n");
        fwrite($f, "AC_WEATHER_FOLDER = " . \Core\Config::AbsPathData . "/acserver/content/weather\n");
        fwrite($f, "UDP_PORT = " . $pc->child("RP", "PortsPlugin", "UDP_L")->value() . "\n");
        fwrite($f, "UDP_RESPONSE = 127.0.0.1:" . $pc->child("AcServer", "PortsPlugin", "UDP_R")->value() . "\n");
        fwrite($f, "APP_TCP_PORT = " . (27 + $pc->child("AcServer", "PortsInet", "HTTP")->value()) . "\n");
        fwrite($f, "APP_UDP_PORT = " . $pc->child("RP", "PortsInet", "UDP")->value() . "\n");
        fwrite($f, "APP_FILE = " . \Core\Config::AbsPathData . "/real_penalty/" . $this->id() . "/files/app\n");
        fwrite($f, "IMAGES_FILE = " . \Core\Config::AbsPathData . "/real_penalty/" . $this->id() . "/files/images\n");
        fwrite($f, "SOUNDS_FILE = " . \Core\Config::AbsPathData . "/real_penalty/" . $this->id() . "/files/sounds\n");
        fwrite($f, "TRACKS_FOLDER = " . \Core\Config::AbsPathData . "/real_penalty/" . $this->id() . "/tracks\n");
        fwrite($f, "ADMIN_PSW = " . $pc->child("RP", "General", "AdminPwd")->value() . "\n");
        fwrite($f, "AC_SERVER_MANAGER = false\n");

        fwrite($f, "\n[Plugins_Relay]\n");
        fwrite($f, "UDP_PORT = " . $pc->child("RP", "PortsPlugin", "UDP_R")->value() . "\n");
        fwrite($f, "OTHER_UDP_PLUGIN = " . $pc->child("ACswui", "PortsPlugin", "UDP_L")->value() . "\n");

        fclose($f);
    }
}
