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
                $coll = new \Parameter\Collection(NULL, $pc, "AcServerGeneral", _("General Settings"), _("General settings for Real Penalty"));
                $p = new \Parameter\ParamString(NULL, $coll, "AcServerGeneralName", _("Name"), _("An arbitrary name for the server (shown in lobby)"), "", "");

                // ports
                $coll = new \Parameter\Collection(NULL, $pc, "AcServerPortsInet", _("Internet Ports"), _("Internet protocol port numbers for the AC server"));
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerPortsInetUdp", "UDP", _("UDP port number: open this port on your server's firewall"), "", 9101);
                $p->setMin(1024);
                $p->setMax(65535);
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerPortsInetTcp", "TCP", _("TCP port number: open this port on your server's firewall"), "", 9101);
                $p->setMin(1024);
                $p->setMax(65535);
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerPortsInetHttp", "HTTP", _("Lobby port number: open these ports (both UDP and TCP) on your server's firewall"), "", 9100);
                $p->setMin(1024);
                $p->setMax(65535);

                // plugin ports
                $coll = new \Parameter\Collection(NULL, $pc, "AcServerPortsPlugin", _("Plugin Ports"), _("UDP plugin port settings"));

                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerPortsPluginUdpR", "UDP_R", _("Remote UDP port for external plugins"), "", 9102);
                $p->setMin(1024);
                $p->setMax(65535);

                // performance
                $coll = new \Parameter\Collection(NULL, $pc, "AcServerPerformance", _("Performance"), _("Settings that affect the transfer performance / quality"));
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerPerformanceClntIntvl", _("Client Interval"), _("Refresh rate of packet sending by the server. 10Hz = ~100ms. Higher number = higher MP quality = higher bandwidth resources needed. Really high values can create connection issues"), "Hz", 15);
                $p->setMin(1);
                $p->setMax(100);
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerPerformanceThreads", _("Number of Threads"), _("Number of threads to run on"), "", 2);
                $p->setMin(1);
                $p->setMax(64);
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerPerformanceMaxClients", _("Max Clients"), _("Max number of clients"), "", 25);
                $p->setMin(1);
                $p->setMax(999);


                ////////////////
                // Real Penalty

                $pc = new \Parameter\Collection(NULL, $root_collection, "RP", _("Real Penalty Plugin"), _("Settings for the Real Penalty plugin"));

                // genral
                $coll = new \Parameter\Collection(NULL, $pc, "RPGeneral", _("General Settings"), _("General settings for Real Penalty"));

                $p = new \Parameter\ParamBool(NULL, $coll, "RPGeneralEnable", _("Enable RP"), _("Wheather to use Real Penalty for this slot or not"), "", FALSE);

                $p = new \Parameter\ParamString(NULL, $coll, "RPGeneralProductKey", _("Product Key"), _("Personal key, received after the Patreon subscription: https://www.patreon.com/DavideBolognesi"), "", "");

                $p = new \Parameter\ParamString(NULL, $coll, "RPGeneralAdminPwd", _("Admin Passwort"), _("Password only for app 'Real Penalty - admin'"), "", "");

                $p = new \Parameter\ParamInt(NULL, $coll, "RPGeneralFCT", _("First Check"), _("Delay (seconds) after connection of new driver for the first check (app + sol). Default 5"), "s", 5);
                $p->setMin(1);
                $p->setMax(60);

                $p = new \Parameter\ParamInt(NULL, $coll, "RPGeneralCF", _("Check Frequency"), _("Frequency (second) for APP, SOL and CSP check. Default 60"), "s", 60);
                $p->setMin(1);
                $p->setMax(3600);

                // plugin ports
                $coll = new \Parameter\Collection(NULL, $pc, "RPPortsPlugin", _("Plugin Ports"), _("UDP plugin port settings"));

                $p = new \Parameter\ParamInt(NULL, $coll, "RPPortsPluginUdpL", "UDP_L", _("Local UDP port to communicate with the acServer"), "", 9103);
                $p->setMin(1024);
                $p->setMax(65535);

                $p = new \Parameter\ParamInt(NULL, $coll, "RPPortsPluginUdpR", "UDP_R", _("Remote UDP port for additional plugins"), "", 9104);
                $p->setMin(1024);
                $p->setMax(65535);

                // internet ports
                $coll = new \Parameter\Collection(NULL, $pc, "RPPortsInet", _("Internet Ports"), _("Port settings open to the internet"));

                $p = new \Parameter\ParamInt(NULL, $coll, "RPPortsInetUdp", "UDP", _("UDP to communicate with RP client app"), "", 9105);
                $p->setMin(1024);
                $p->setMax(65535);


                /////////////////
                // ACswui Plugin

                $pc = new \Parameter\Collection(NULL, $root_collection, "ACswui", _("ACswui Plugin"), _("Settings for the ACswui plugin"));

                // plugin ports
                $coll = new \Parameter\Collection(NULL, $pc, "ACswuiPortsPlugin", _("Plugin Ports"), _("UDP plugin port settings"));

                $p = new \Parameter\ParamInt(NULL, $coll, "ACswuiPortsPluginUdpL", "UDP_L", _("Local UDP port to communicate with the acServer"), "", 9106);
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
    public function start(\DbEntry\Track $track,
                          \DbEntry\CarClass $car_class,
                          \DbEntry\ServerPreset $preset) {

        $id = $this->id();

        // configure real penalty
        $this->writeRpAcSettings();
        $this->writeRpPenaltySettings();
        $this->writeRpSettings();

        // configure ACswui plugin
        $this->writeACswuiUdpPluginIni($car_class, $preset);

        // configure ac server
        $this->writeAcServerEntryList($track, $car_class);
        $this->writeAcServerCfg($track, $car_class, $preset);

        // lunch server with plugins
        $ac_server = \Core\Config::AbsPathData . "/acserver/acServer$id";
        $server_cfg = \Core\Config::AbsPathData . "/acserver/cfg/server_cfg_$id.ini";
        $entry_list = \Core\Config::AbsPathData . "/acserver/cfg/entry_list_$id.ini";
        $log_output = \Core\Config::AbsPathData . "/logs_acserver/acServer$id.log";
        $ac_server_command = "$ac_server -c $server_cfg -e $entry_list > $log_output 2>&1";


        // start server
        $cmd_ret = 0;
        $cmd = "nohup ". \Core\Config::AbsPathAcswui . "/acswui.py srvrun -vvvvv";
        $cmd .= " \"" . \Core\Config::AbsPathData . "/acswui_udp_plugin/acswui_udp_plugin_$id.ini\" ";
        $cmd .= " --slot $id";
        if ($this->parameterCollection()->child("RPGeneralEnable")->value()) {
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
    private function writeACswuiUdpPluginIni(\DbEntry\CarClass $car_class, \DbEntry\ServerPreset $preset) {
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
        fwrite($f, "preset = " . $preset->id() . "\n");
        fwrite($f, "carclass = " . $car_class->id() . "\n");
        fwrite($f, "udp_plugin = " . $pc->child("ACswuiPortsPluginUdpL")->value() . "\n");
        if ($pc->child("RPGeneralEnable")->value()) {
            fwrite($f, "udp_acserver = " . $pc->child("RPPortsPluginUdpR")->value() . "\n");
        } else {
            fwrite($f, "udp_acserver = " . $pc->child("AcServerPortsPluginUdpR")->value() . "\n");
        }


        fclose($f);
    }


    //! create server_cfg.ini for real penalty
    private function writeAcServerCfg(\DbEntry\Track $track,
                                      \DbEntry\CarClass $car_class,
                                      \DbEntry\ServerPreset $preset) {

        $pc = $this->parameterCollection();
        $ppc = $preset->parameterCollection();
        $file_path = \Core\Config::AbsPathData . "/acserver/cfg/server_cfg_" . $this->id() . ".ini";
        $f = fopen($file_path, 'w');
        if ($f === FALSE) {
            \Core\Log::error("Cannot write to file '$file_path'!");
            return;
        }

        fwrite($f, "[SERVER]\n");
        fwrite($f, "NAME=" . $pc->child("AcServerGeneralName")->value() . "\n");
        fwrite($f, "PASSWORD=\n");
        fwrite($f, "ADMIN_PASSWORD=\n");
        fwrite($f, "UDP_PORT=" . $pc->child("AcServerPortsInetUdp")->value() . "\n");
        fwrite($f, "TCP_PORT=" . $pc->child("AcServerPortsInetTcp")->value() . "\n");
        fwrite($f, "HTTP_PORT=" . $pc->child("AcServerPortsInetHttp")->value() . "\n");
        fwrite($f, "SEND_BUFFER_SIZE=0\n");
        fwrite($f, "RECV_BUFFER_SIZE=0\n");
        fwrite($f, "CLIENT_SEND_INTERVAL_HZ=" . $pc->child("AcServerPerformanceClntIntvl")->value() . "\n");
        fwrite($f, "NUM_THREADS=" . $pc->child("AcServerPerformanceThreads")->value() . "\n");
        fwrite($f, "SLEEP_TIME=1\n");
        fwrite($f, "REGISTER_TO_LOBBY=1\n");
        fwrite($f, "MAX_CLIENTS=" . $pc->child("AcServerPerformanceMaxClients")->value() . "\n");
        fwrite($f, "WELCOME_MESSAGE=\n");  //! @todo needs to be implemented as parameter
        fwrite($f, "PICKUP_MODE_ENABLED=" . (($ppc->child("AcServerPickupMode")->value()) ? 1:0) . "\n");
        fwrite($f, "LOOP_MODE=0\n");  // ACswui system does require LOOP_MODE=0
        fwrite($f, "SUN_ANGLE=" . $ppc->child("AcServerSunAngle")->value() . "\n");
        fwrite($f, "QUALIFY_MAX_WAIT_PERC=" . $ppc->child("AcServerQualifyingWaitPerc")->value() . "\n");
        fwrite($f, "LEGAL_TYRES=" . $ppc->child("AcServerLegalTyres")->value() . "\n");
        fwrite($f, "RACE_OVER_TIME=" . $ppc->child("AcServerRaceOverTime")->value() . "\n");
        fwrite($f, "RACE_PIT_WINDOW_START=" . $ppc->child("AcServerPitWinOpen")->value() . "\n");
        fwrite($f, "RACE_PIT_WINDOW_END=" . $ppc->child("AcServerPitWinClose")->value() . "\n");
        fwrite($f, "REVERSED_GRID_RACE_POSITIONS=" . $ppc->child("AcServerReversedGrid")->value() . "\n");
        fwrite($f, "LOCKED_ENTRY_LIST=" . (($ppc->child("AcServerLockedEntryList")->value()) ? 1:0) . "\n");
        fwrite($f, "START_RULE=" . $ppc->child("AcServerStartRule")->value() . "\n");
        fwrite($f, "RACE_GAS_PENALTY_DISABLED=" . (($ppc->child("AcServerRaceGasPenalty")->value()) ? 1:0) . "\n");
        fwrite($f, "TIME_OF_DAY_MULT=" . $ppc->child("AcServerTimeMultiplier")->value() . "\n");
        fwrite($f, "RESULT_SCREEN_TIME=" . $ppc->child("AcServerResultScreenTime")->value() . "\n");
        fwrite($f, "MAX_CONTACTS_PER_KM=" . $ppc->child("AcServerMaxContactsPerKm")->value() . "\n");
        fwrite($f, "RACE_EXTRA_LAP=" . (($ppc->child("AcServerExtraLap")->value()) ? 1:0) . "\n");
        fwrite($f, "UDP_PLUGIN_LOCAL_PORT=" . $pc->child("AcServerPortsPluginUdpR")->value() . "\n");
        if ($pc->child("RPGeneralEnable")->value()) {
            fwrite($f, "UDP_PLUGIN_ADDRESS=127.0.0.1:" . $pc->child("RPPortsPluginUdpL")->value() . "\n");
        } else {
            fwrite($f, "UDP_PLUGIN_ADDRESS=127.0.0.1:" . $pc->child("ACswuiPortsPluginUdpL")->value() . "\n");
        }
        fwrite($f, "AUTH_PLUGIN_ADDRESS=\n");
        fwrite($f, "KICK_QUORUM=" . $ppc->child("AcServerKickQuorum")->value() . "\n");
        fwrite($f, "BLACKLIST_MODE=" . $ppc->child("AcServerKickQuorum")->value() . "\n");
        fwrite($f, "VOTING_QUORUM=" . $ppc->child("AcServerVotingQuorum")->value() . "\n");
        fwrite($f, "VOTE_DURATION=" . $ppc->child("AcServerVoteDuration")->value() . "\n");
        fwrite($f, "FUEL_RATE=" . $ppc->child("AcServerFuelRate")->value() . "\n");
        fwrite($f, "DAMAGE_MULTIPLIER=" . $ppc->child("AcServerDamageMultiplier")->value() . "\n");
        fwrite($f, "TYRE_WEAR_RATE=" . $ppc->child("AcServerTyreWearRate")->value() . "\n");
        fwrite($f, "ALLOWED_TYRES_OUT=" . $ppc->child("AcServerTyresOut")->value() . "\n");
        fwrite($f, "ABS_ALLOWED=" . $ppc->child("AcServerAbsAllowed")->value() . "\n");
        fwrite($f, "TC_ALLOWED=" . $ppc->child("AcServerTcAllowed")->value() . "\n");
        fwrite($f, "STABILITY_ALLOWED=" . $ppc->child("AcServerEscAllowed")->value() . "\n");
        fwrite($f, "AUTOCLUTCH_ALLOWED=" . $ppc->child("AcServerAutoClutchAllowed")->value() . "\n");
        fwrite($f, "TYRE_BLANKETS_ALLOWED=" . $ppc->child("AcServerTyreBlankets")->value() . "\n");
        fwrite($f, "FORCE_VIRTUAL_MIRROR=" . $ppc->child("AcServerForceVirtualMirror")->value() . "\n");
        fwrite($f, "\n");
        //! @todo Extract from CarClass and Track
        $cars = array();
        foreach ($car_class->cars() as $car) $cars[] = $car->model();
        fwrite($f, "CARS=" . implode(";", $cars) . "\n");
        fwrite($f, "MAX_BALLAST_KG=" . $car_class->ballastMax() . "\n");
        fwrite($f, "TRACK=" . $track->location()->track() . "\n");
        fwrite($f, "CONFIG_TRACK=" . $track->config() . "\n");

//         fwrite($f, "\n[FTP]\n");
//         fwrite($f, "HOST=\n");
//         fwrite($f, "LOGIN=\n");
//         fwrite($f, "PASSWORD=\n");
//         fwrite($f, "FOLDER=\n");
//         fwrite($f, "LINUX=1\n");

        if ($ppc->child("AcServerBookingTime")->value() > 0) {
            fwrite($f, "\n[BOOKING]\n");
            fwrite($f, "NAME=" . $ppc->child("AcServerBookingName")->value() . "\n");
            fwrite($f, "TIME=" . $ppc->child("AcServerBookingTime")->value() . "\n");
        }

        if ($ppc->child("AcServerPracticeTime")->value() > 0) {
            fwrite($f, "\n[PRACTICE]\n");
            fwrite($f, "NAME=" . $ppc->child("AcServerPracticeName")->value() . "\n");
            fwrite($f, "TIME=" . $ppc->child("AcServerPracticeTime")->value() . "\n");
            fwrite($f, "IS_OPEN=" . $ppc->child("AcServerPracticeIsOpen")->value() . "\n");
        }

        if ($ppc->child("AcServerQualifyingTime")->value() > 0) {
            fwrite($f, "\n[QUALIFY]\n");
            fwrite($f, "NAME=" . $ppc->child("AcServerQualifyingName")->value() . "\n");
            fwrite($f, "TIME=" . $ppc->child("AcServerQualifyingTime")->value() . "\n");
            fwrite($f, "IS_OPEN=" . $ppc->child("AcServerQualifyingIsOpen")->value() . "\n");
        }

        if ($ppc->child("AcServerQualifyingTime")->value() > 0) {
            fwrite($f, "\n[RACE]\n");
            fwrite($f, "NAME=" . $ppc->child("AcServerRaceName")->value() . "\n");
            fwrite($f, "LAPS=" . $ppc->child("AcServerRaceLaps")->value() . "\n");
            fwrite($f, "TIME=" . $ppc->child("AcServerRaceTime")->value() . "\n");
            fwrite($f, "WAIT_TIME=" . $ppc->child("AcServerRaceWaitTime")->value() . "\n");
            fwrite($f, "IS_OPEN=" . $ppc->child("AcServerRaceIsOpen")->value() . "\n");
        }

        fwrite($f, "\n[DYNAMIC_TRACK]\n");
        fwrite($f, "SESSION_START=" . $ppc->child("AcServerDynamicTrackSessionStart")->value() . "\n");
        fwrite($f, "RANDOMNESS=" . $ppc->child("AcServerDynamicTrackRandomness")->value() . "\n");
        fwrite($f, "LAP_GAIN=" . $ppc->child("AcServerDynamicTrackLapGain")->value() . "\n");
        fwrite($f, "SESSION_TRANSFER=" . $ppc->child("AcServerDynamicTrackSessionTransfer")->value() . "\n");

        // weather
        $weathers = array();
        if (count($ppc->child("AcServerWeather")->valueList()) > 0) {
            foreach ($ppc->child("AcServerWeather")->valueList() as $w_id) {
                $weathers[] = \DbEntry\Weather::fromId($w_id);
            }
        } else {
            $weathers[] = \DbEntry\Weather::fromId(0);
        }

        for ($i=0; $i<count($weathers); ++$i) {
            $wpc = $weathers[$i]->parameterCollection();
            fwrite($f, "\n[WEATHER_$i]\n");
            fwrite($f, "GRAPHICS=" . $wpc->child("Graphic")->value() . "\n");
            fwrite($f, "BASE_TEMPERATURE_AMBIENT=" . $wpc->child("AmbientBase")->value() . "\n");
            fwrite($f, "VARIATION_AMBIENT=" . $wpc->child("AmbientVar")->value() . "\n");
            fwrite($f, "BASE_TEMPERATURE_ROAD=" . $wpc->child("RoadBase")->value() . "\n");
            fwrite($f, "VARIATION_ROAD=" . $wpc->child("RoadVar")->value() . "\n");
            fwrite($f, "WIND_BASE_SPEED_MIN=" . $wpc->child("WindBaseMin")->value() . "\n");
            fwrite($f, "WIND_BASE_SPEED_MAX=" . $wpc->child("WindBaseMax")->value() . "\n");
            fwrite($f, "WIND_BASE_DIRECTION=" . $wpc->child("WindDirection")->value() . "\n");
            fwrite($f, "WIND_VARIATION_DIRECTION=" . $wpc->child("WindDirectionVar")->value() . "\n");
        }


        fclose($f);
    }


    //! create entry_list.ini for real penalty
    private function writeAcServerEntryList(\DbEntry\Track $track, \DbEntry\CarClass $car_class) {
        $pc = $this->parameterCollection();
        $file_path = \Core\Config::AbsPathData . "/acserver/cfg/entry_list_" . $this->id() . ".ini";
        $f = fopen($file_path, 'w');
        if ($f === FALSE) {
            \Core\Log::error("Cannot write to file '$file_path'!");
            return;
        }

        $entry_id = 0;
        foreach ($car_class->cars() as $c) {
            foreach ($c->skins() as $s) {
                if ($entry_id >= $track->pitboxes()) break;

                fwrite($f, "\n[CAR_$entry_id]\n");
                fwrite($f, "MODEL=" . $s->car()->model() . "\n");
                fwrite($f, "SKIN=" . $s->skin() . "\n");
                fwrite($f, "SPECTATOR_MODE=0\n");
                fwrite($f, "DRIVERNAME=\n");
                fwrite($f, "TEAM=\n");
                fwrite($f, "GUID=\n");
                fwrite($f, "BALLAST=" . $car_class->ballast($s->car()) . "\n");
                fwrite($f, "RESTRICTOR=" . $car_class->restrictor($s->car()) . "\n");

                ++$entry_id;
            }
            if ($entry_id >= $track->pitboxes()) break;
        }

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
        fwrite($f, "FIRST_CHECK_TIME = " . $pc->child("RPGeneralFCT")->value() . "\n");
        fwrite($f, "COCKPIT_CAMERA = false\n");
        fwrite($f, "TRACK_CHECKSUM = false\n");  //! @todo Copy track models and KN5 files to path-srvpkg to use this feature
        fwrite($f, "WEATHER_CHECKSUM = true\n");
        fwrite($f, "CAR_CHECKSUM = false\n");  //! @todo copy data.acd and collider.kn5 to path-srvpkg to use this feature
        fwrite($f, "qualify_time = _\n");

        // section App
        fwrite($f, "\n[App]\n");
        fwrite($f, "CHECK_FREQUENCY = " . $pc->child("RPGeneralCF")->value() . "\n");

        // section Sol
        fwrite($f, "\n[Sol]\n");
        fwrite($f, "PERFORMACE_MODE_ALLOWED = true\n");  // intentionally not needed but current revision 4.01.07 throws an error when this is not present
        fwrite($f, "MANDATORY = false\n");
        fwrite($f, "CHECK_FREQUENCY = " . $pc->child("RPGeneralCF")->value() . "\n");

        // section Custom_Shaders_Patch
        fwrite($f, "\n[Custom_Shaders_Patch]\n");
        fwrite($f, "MANDATORY = false\n");
        fwrite($f, "CHECK_FREQUENCY = " . $pc->child("RPGeneralCF")->value() . "\n");

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
        fwrite($f, "ENABLE_CUTTING_PENALTIES = true\n");
        fwrite($f, "ENABLE_SPEEDING_PENALTIES = true\n");
        fwrite($f, "ENABLE_CROSSING_PENALTIES = true\n");
        fwrite($f, "ENABLE_DRS_PENALTIES = true\n");
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
        $id = $this->id();
        $pc = $this->parameterCollection();
        $file_path = \Core\Config::AbsPathData . "/real_penalty/" . $this->id() . "/settings.ini";
        $f = fopen($file_path, 'w');
        if ($f === FALSE) {
            \Core\Log::error("Cannot write to file '$file_path'!");
            return;
        }

        // section General
        fwrite($f, "[General]\n");
        fwrite($f, "product_key = " . $pc->child("RPGeneralProductKey")->value() . "\n");
        fwrite($f, "AC_SERVER_PATH = " . \Core\Config::AbsPathData . "/acserver\n");
        fwrite($f, "AC_CFG_FILE = " . \Core\Config::AbsPathData . "/acserver/cfg/server_cfg_$id.ini\n");
        fwrite($f, "AC_TRACKS_FOLDER = " . \Core\Config::AbsPathData . "/acserver/content/tracks\n");
        fwrite($f, "AC_WEATHER_FOLDER = " . \Core\Config::AbsPathData . "/acserver/content/weather\n");
        fwrite($f, "UDP_PORT = " . $pc->child("RPPortsPluginUdpL")->value() . "\n");
        fwrite($f, "UDP_RESPONSE = 127.0.0.1:" . $pc->child("AcServerPortsPluginUdpR")->value() . "\n");
        fwrite($f, "APP_TCP_PORT = " . (27 + $pc->child("AcServerPortsInetHttp")->value()) . "\n");
        fwrite($f, "APP_UDP_PORT = " . $pc->child("RPPortsInetUdp")->value() . "\n");
        fwrite($f, "APP_FILE = " . \Core\Config::AbsPathData . "/real_penalty/$id/files/app\n");
        fwrite($f, "IMAGES_FILE = " . \Core\Config::AbsPathData . "/real_penalty/$id/files/images\n");
        fwrite($f, "SOUNDS_FILE = " . \Core\Config::AbsPathData . "/real_penalty/$id/files/sounds\n");
        fwrite($f, "TRACKS_FOLDER = " . \Core\Config::AbsPathData . "/real_penalty/$id/tracks\n");
        fwrite($f, "ADMIN_PSW = " . $pc->child("RPGeneralAdminPwd")->value() . "\n");
        fwrite($f, "AC_SERVER_MANAGER = false\n");

        fwrite($f, "\n[Plugins_Relay]\n");
        fwrite($f, "UDP_PORT = " . $pc->child("RPPortsPluginUdpR")->value() . "\n");
        fwrite($f, "OTHER_UDP_PLUGIN = 127.0.0.1:" . $pc->child("ACswuiPortsPluginUdpL")->value() . "\n");

        fclose($f);
    }
}
