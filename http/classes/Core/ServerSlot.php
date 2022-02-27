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


    //! @return The string representation
    public function __toString() {
        return "ServerSlot[Id=" . $this->Id . " | " . $this->name() . "]";
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


    //! @return The according Session object that currently runs on the slot (can be NULL)
    public function currentSession() {
        $session = NULL;

        if ($this->online()) {

            // find last session on this slot
            $query = "SELECT Id FROM Sessions WHERE ServerSlot = " . $this->id() . " ORDER BY Id DESC LIMIT 1;";
            $res = \Core\Database::fetchRaw($query);

            if (count($res) == 1) {
                $session = \DbEntry\Session::fromId($res[0]['Id']);
            }
        }

        return $session;
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


    //! @return A list of all available ServerSlot objects
    public static function listSlots() {
        $list = array();
        for ($i=1; $i <= \Core\Config::ServerSlotAmount; ++$i)
            $list[] = ServerSlot::fromId($i);
        return $list;
    }


    //! @return The name of the preset
    public function name() {
        if ($this->id() === 0) return _("Base Settings");
        else return $this->parameterCollection()->child("AcServerGeneralName")->valueLabel();
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
                $root_collection->setAllAccessible();

                // derive base collection from (invisible) root collection
                $this->ParameterCollection = new \Parameter\Collection($root_collection, NULL);
            }

            // load data from disk
            $file_path = \Core\Config::AbsPathData . "/acswui_config/server_slot_" . $this->id() . ".json";
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
            } else {
                \Core\Log::warning("Server Slot Config file '$file_path' does not exist.");
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
        $file_path = \Core\Config::AbsPathData . "/acswui_config/server_slot_" . $this->id() . ".json";
        $f = fopen($file_path, 'w');
        if ($f === FALSE) {
            \Core\Log::error("Cannot write to file '$file_path'!");
            return;
        }
        fwrite($f, $data_json);
        fclose($f);
    }


    /**
     * start the server within this slot
     * @param $track TheTrack to use
     * @param $car_class The CarClass of the server run
     * @param $preset The ServerPreset for the server run
     * @param $el The EntryList which shall be used
     * @param $map_ballasts An associative array with Steam64GUID->Ballast and one key with 'OTHER'->Ballst
     * @param $map_restrictors An associative array with Steam64GUID->Restrictor and one key with 'OTHER'->Restrictor
     */
    public function start(\DbEntry\Track $track,
                          \DbEntry\CarClass $car_class,
                          \DbEntry\ServerPreset $preset,
                          \Core\EntryList $el = NULL,
                          array $map_ballast = [],
                          array $map_restrictor = []) {

        $id = $this->id();

        // configure real penalty
        $this->writeRpAcSettings($preset);
        $this->writeRpPenaltySettings($preset);
        $this->writeRpSettings();

        // configure ACswui plugin
        $this->writeACswuiUdpPluginIni($car_class, $preset, $map_ballast, $map_restrictor);

        // configure ac server
        if ($el === NULL) {
            $el = new \Core\EntryList();
            $el->fillSkins($car_class, $track);
        }
        $el->writeToFile(\Core\Config::AbsPathData . "/acserver/cfg/entry_list_" . $this->id() . ".ini");
        $this->writeAcServerCfg($track, $car_class, $preset, $map_ballast);

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

        sleep(0.1);

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
        sleep(0.1);
    }


    /**
     * create acswui_udp_plugin.ini
     * @param $car_class The CarClass of the server run
     * @param $preset The ServerPreset for the server run
     * @param $map_ballasts An associative array with Steam64GUID->Ballast and one key with 'OTHER'->Ballst
     * @param $map_restrictors An associative array with Steam64GUID->Restrictor and one key with 'OTHER'->Restrictor
     */
    private function writeACswuiUdpPluginIni(\DbEntry\CarClass $car_class,
                                             \DbEntry\ServerPreset $preset,
                                             array $map_ballasts = [],
                                             array $map_restrictors = []) {
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
        fwrite($f, "preserved_kick = " . $preset->getParam("ACswuiPreservedKick") . "\n");

        fwrite($f, "\n[BALLAST]\n");
        foreach ($map_ballasts as $guid=>$ballast) {
            fwrite($f, "$guid = $ballast\n");
        }

        fwrite($f, "\n[RESTRICTOR]\n");
        foreach ($map_restrictors as $guid=>$restrictor) {
            fwrite($f, "$guid = $restrictor\n");
        }

        fclose($f);
    }


    /**
     * create server_cfg.ini for acServer
     * @param $track The Track object for the server run
     * @param $car_class The CarClass of the server run
     * @param $preset The ServerPreset for the server run
     * @param $map_ballasts An associative array with Steam64GUID->Ballast and one key with 'OTHER'->Ballst
     */
    private function writeAcServerCfg(\DbEntry\Track $track,
                                      \DbEntry\CarClass $car_class,
                                      \DbEntry\ServerPreset $preset,
                                      array $map_ballasts = []) {


        // determine maximum ballast
        $max_ballast = $car_class->ballastMax();
        foreach ($map_ballasts as $guid=>$ballast) {
            if ($ballast > $max_ballast) $max_ballast = $ballast;
        }

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

        if ($preset->anyWeatherUsesCsp()) {
            $time_minutes = $ppc->child("SessionStartTime")->value();
            fwrite($f, "SUN_ANGLE=-80\n");
            fwrite($f, "TIME_OF_DAY_MULT=0.1\n");
        } else {
            fwrite($f, "SUN_ANGLE=" . $ppc->child("SessionStartTime")->valueSunAngle() . "\n");
            fwrite($f, "TIME_OF_DAY_MULT=" . $ppc->child("AcServerTimeMultiplier")->value() . "\n");
        }

        fwrite($f, "QUALIFY_MAX_WAIT_PERC=" . $ppc->child("AcServerQualifyingWaitPerc")->value() . "\n");
        fwrite($f, "LEGAL_TYRES=" . $ppc->child("AcServerLegalTyres")->value() . "\n");
        fwrite($f, "RACE_OVER_TIME=" . $ppc->child("AcServerRaceOverTime")->value() . "\n");
        fwrite($f, "RACE_PIT_WINDOW_START=" . $ppc->child("AcServerPitWinOpen")->value() . "\n");
        fwrite($f, "RACE_PIT_WINDOW_END=" . $ppc->child("AcServerPitWinClose")->value() . "\n");
        fwrite($f, "REVERSED_GRID_RACE_POSITIONS=" . $ppc->child("AcServerReversedGrid")->value() . "\n");
        fwrite($f, "LOCKED_ENTRY_LIST=" . (($ppc->child("AcServerLockedEntryList")->value()) ? 1:0) . "\n");
        fwrite($f, "START_RULE=" . $ppc->child("AcServerStartRule")->value() . "\n");
        fwrite($f, "RACE_GAS_PENALTY_DISABLED=" . (($ppc->child("AcServerRaceGasPenalty")->value()) ? 1:0) . "\n");
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
        fwrite($f, "MAX_BALLAST_KG=$max_ballast\n");
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

        if ($ppc->child("AcServerRaceTime")->value() > 0 || $ppc->child("AcServerRaceLaps")->value() > 0) {
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
        $weathers = $preset->weathers();
        for ($i=0; $i<count($weathers); ++$i) {
            $wpc = $weathers[$i]->parameterCollection();
            fwrite($f, "\n[WEATHER_$i]\n");

            $g = $wpc->child("Graphic");
            $g_str = $g->getGraphic();
            if ($g->csp()) {
                $g_str .= "_time=" . $ppc->child("SessionStartTime")->valueSeconds();
                $g_str .= "_mult=" . 10 * $ppc->child("AcServerTimeMultiplier")->value();
            }
            fwrite($f, "GRAPHICS=$g_str\n");

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


    //! create ac_settings.ini for real penalty
    private function writeRpAcSettings(\DbEntry\ServerPreset $preset) {
        $pc = $this->parameterCollection();
        $ppc = $preset->parameterCollection();
        $file_path = \Core\Config::AbsPathData . "/real_penalty/" . $this->id() . "/ac_settings.ini";
        $f = fopen($file_path, 'w');
        if ($f === FALSE) {
            \Core\Log::error("Cannot write to file '$file_path'!");
            return;
        }

        // section General
        fwrite($f, "[General]\n");
        fwrite($f, "FIRST_CHECK_TIME = " . $pc->child("RPGeneralFCT")->value() . "\n");
        fwrite($f, "COCKPIT_CAMERA = " . (($ppc->child("RpAcsGeneralCockpitCam")->value()) ? 1:0) . "\n");
        fwrite($f, "TRACK_CHECKSUM = false\n");  //! @todo Copy track models and KN5 files to path-srvpkg to use this feature
        fwrite($f, "WEATHER_CHECKSUM = " . (($ppc->child("RpAcsGeneralWeatherChecksum")->value()) ? 1:0) . "\n");
        fwrite($f, "CAR_CHECKSUM = false\n");  //! @todo copy data.acd and collider.kn5 to path-srvpkg to use this feature
        fwrite($f, "qualify_time = _\n");  //! @todo I do not understand this setting, yet. This need to be understood berfore implemented.

        // section App
        fwrite($f, "\n[App]\n");
        fwrite($f, "CHECK_FREQUENCY = " . $pc->child("RPGeneralCF")->value() . "\n");

        // section Sol
        fwrite($f, "\n[Sol]\n");
        fwrite($f, "PERFORMACE_MODE_ALLOWED = true\n");  // intentionally not needed but current revision 4.01.07 throws an error when this is not present
        fwrite($f, "MANDATORY = " . (($ppc->child("RpAcsSolMandatory")->value()) ? 1:0) . "\n");
        fwrite($f, "CHECK_FREQUENCY = " . $pc->child("RPGeneralCF")->value() . "\n");

        // section Custom_Shaders_Patch
        fwrite($f, "\n[Custom_Shaders_Patch]\n");
        fwrite($f, "MANDATORY = " . (($ppc->child("RpAcsCspMandatory")->value()) ? 1:0) . "\n");
        fwrite($f, "CHECK_FREQUENCY = " . $pc->child("RPGeneralCF")->value() . "\n");

        // section Safety_Car
        fwrite($f, "\n[Safety_Car]\n");
        fwrite($f, "CAR_MODEL = " . $ppc->child("RpAcsScCarModel")->value() . "\n");
        fwrite($f, "RACE_START_BEHIND_SC = " . (($ppc->child("RpAcsScStartBehind")->value()) ? 1:0) . "\n");
        fwrite($f, "NORMALIZED_LIGHT_OFF_POSITION = " . $ppc->child("RpAcsScNormLightOff")->value() . "\n");
        fwrite($f, "NORMALIZED_START_POSITION = " . $ppc->child("RpAcsScNormStart")->value() . "\n");
        fwrite($f, "GREEN_LIGHT_DELAY = " . $ppc->child("RpAcsScGreenDelay")->value() . "\n");

        // section No_Penalty
        fwrite($f, "\n[No_Penalty]\n");
        fwrite($f, "GUIDs = " . $ppc->child("RpAcsNpGuids")->value() . "\n");
        fwrite($f, "Cars = " . $ppc->child("RpAcsNpCars")->value() . "\n");

        // section Admin
        fwrite($f, "\n[Admin]\n");
        fwrite($f, "GUIDs = " . $ppc->child("RPAcsAdminGuids")->value() . "\n");

        // section Helicorsa
        fwrite($f, "\n[Helicorsa]\n");
        fwrite($f, "MANDATORY = " . (($ppc->child("RPAcsHcMandatory")->value()) ? 1:0) . "\n");
        fwrite($f, "DISTANCE_THRESHOLD = " . $ppc->child("RPAcsHcDistThld")->value() . "\n");
        fwrite($f, "WORLD_ZOOM = " . $ppc->child("RPAcsHcWorldZoom")->value() . "\n");
        fwrite($f, "OPACITY_THRESHOLD = " . $ppc->child("RPAcsHcOpaThld")->value() . "\n");
        fwrite($f, "FRONT_FADE_OUT_ARC = " . $ppc->child("RPAcsHcFrontFadeoutArc")->value() . "\n");
        fwrite($f, "FRONT_FADE_ANGLE = " . $ppc->child("RPAcsHcFrontFadeAngle")->value() . "\n");
        fwrite($f, "CAR_LENGHT = " . $ppc->child("RPAcsHcCarLength")->value() . "\n");
        fwrite($f, "CAR_WIDTH = " . $ppc->child("RPAcsHcCarWidth")->value() . "\n");

        // section Swap
        fwrite($f, "\n[Swap]\n");
        fwrite($f, "enable = " . (($ppc->child("RPAcsSwapEna")->value()) ? 1:0) . "\n");
        fwrite($f, "min_time = " . $ppc->child("RpAcsSwapMinTime")->value() . "\n");
        fwrite($f, "min_count = " . $ppc->child("RpAcsSwapMinCount")->value() . "\n");
        fwrite($f, "enable_penalty = " . (($ppc->child("RPAcsSwapPenEna")->value()) ? 1:0) . "\n");
        fwrite($f, "penalty_time = " . $ppc->child("RpAcsSwapPenTime")->value() . "\n");
        fwrite($f, "penalty_during_race = " . (($ppc->child("RPAcsSwapPenDurRace")->value()) ? 1:0) . "\n");
        fwrite($f, "convert_time_penalty = " . (($ppc->child("RPAcsSwapConvertTimePen")->value()) ? 1:0) . "\n");
        fwrite($f, "convert_race_penalty = " . (($ppc->child("RPAcsSwapConvertRacePen")->value()) ? 1:0) . "\n");
        fwrite($f, "disqualify_time = " . $ppc->child("RpAcsSwapDsqTime")->value() . "\n");
        fwrite($f, "count_only_driver_change = " . (($ppc->child("RPAcsSwapCntOnlyDrvChnge")->value()) ? 1:0) . "\n");

        // section Teleport
        fwrite($f, "\n[Teleport]\n");
        fwrite($f, "max_distance = " . $ppc->child("RPAcsTeleportMaxDist")->value() . "\n");
        fwrite($f, "practice_enable = " . (($ppc->child("RPAcsTeleportEnaPractice")->value()) ? 1:0) . "\n");
        fwrite($f, "qualify_enable = " . (($ppc->child("RPAcsTeleportEnaQualify")->value()) ? 1:0) . "\n");
        fwrite($f, "race_enable = " . (($ppc->child("RPAcsTeleportEnaRace")->value()) ? 1:0) . "\n");

        fclose($f);
    }


    //! create penalty_settings.ini for real penalty
     private function writeRpPenaltySettings(\DbEntry\ServerPreset $preset) {
        $ppc = $this->parameterCollection();
        $ppc = $preset->parameterCollection();
        $file_path = \Core\Config::AbsPathData . "/real_penalty/" . $this->id() . "/penalty_settings.ini";
        $f = fopen($file_path, 'w');
        if ($f === FALSE) {
            \Core\Log::error("Cannot write to file '$file_path'!");
            return;
        }

        // section General
        fwrite($f, "[General]\n");
        fwrite($f, "ENABLE_CUTTING_PENALTIES = " . (($ppc->child("RpPsGeneralCutting")->value()) ? 1:0) . "\n");
        fwrite($f, "ENABLE_SPEEDING_PENALTIES = " . (($ppc->child("RpPsGeneralSpeeding")->value()) ? 1:0) . "\n");
        fwrite($f, "ENABLE_CROSSING_PENALTIES = " . (($ppc->child("RpPsGeneralCrossing")->value()) ? 1:0) . "\n");
        fwrite($f, "ENABLE_DRS_PENALTIES = " . (($ppc->child("RpPsGeneralDrs")->value()) ? 1:0) . "\n");
        fwrite($f, "LAPS_TO_TAKE_PENALTY = " . (($ppc->child("RpPsGeneralLapsToTake")->value()) ? 1:0) . "\n");
        fwrite($f, "PENALTY_SECONDS = " . (($ppc->child("RpPsGeneralPenSecs")->value()) ? 1:0) . "\n");
        fwrite($f, "LAST_TIME_WITHOUT_PENALTY = " . (($ppc->child("RpPsGenerallastTimeNPen")->value()) ? 1:0) . "\n");
        fwrite($f, "LAST_LAPS_WITHOUT_PENALTY = " . (($ppc->child("RpPsGenerallastLapsNPen")->value()) ? 1:0) . "\n");

        // section Cutting
        fwrite($f, "\n[Cutting]\n");
        fwrite($f, "ENABLED_DURING_SAFETY_CAR = " . (($ppc->child("RpPsCuttingEnaDurSc")->value()) ? 1:0) . "\n");
        fwrite($f, "TOTAL_CUT_WARNINGS = " . $ppc->child("RpPsCuttingTotCtWarn")->value() . "\n");
        fwrite($f, "ENABLE_TYRES_DIRT_LEVEL = " . (($ppc->child("RpPsCuttingTyreDirt")->value()) ? 1:0) . "\n");
        fwrite($f, "WHEELS_OUT = " . $ppc->child("AcServerTyresOut")->value() . "\n");
        fwrite($f, "MIN_SPEED = " . $ppc->child("RpPsCuttingMinSpeed")->value() . "\n");
        fwrite($f, "SECONDS_BETWEEN_CUTS = " . $ppc->child("RpPsCuttingSecsBetween")->value() . "\n");
        fwrite($f, "MAX_CUT_TIME = " . $ppc->child("RpPsCuttingMaxTime")->value() . "\n");
        fwrite($f, "MIN_SLOW_DOWN_RATIO = " . $ppc->child("RpPsCuttingMinSlowdownRatio")->value() . "\n");
        fwrite($f, "QUAL_SLOW_DOWN_SPEED = " . $ppc->child("RpPsCuttingQualSlowdownSpeed")->value() . "\n");
        fwrite($f, "QUALIFY_MAX_SECTOR_OUT_SPEED = " . $ppc->child("RpPsCuttingQualMaxSecOutSpeed")->value() . "\n");
        fwrite($f, "QUALIFY_SLOW_DOWN_SPEED_RATIO = " . $ppc->child("RpPsCuttingQualSlowdownRatio")->value() . "\n");
        fwrite($f, "POST_CUTTING_TIME = " . $ppc->child("RpPsCuttingPostCtTime")->value() . "\n");
        fwrite($f, "PENALTY_TYPE = " . $ppc->child("RpPsCuttingPenType")->value() . "\n");

        // section Speeding
        fwrite($f, "\n[Speeding]\n");
        fwrite($f, "PIT_LANE_SPEED = " . $ppc->child("RpPsCuttingPenType")->value() . "\n");
        fwrite($f, "PENALTY_TYPE_0 = " . $ppc->child("RpPsCuttingPenType")->value() . "\n");
        fwrite($f, "SPEED_LIMIT_PENALTY_0 = " . $ppc->child("RpPsCuttingPenType")->value() . "\n");
        fwrite($f, "PENALTY_TYPE_1 = " . $ppc->child("RpPsCuttingPenType")->value() . "\n");
        fwrite($f, "SPEED_LIMIT_PENALTY_1 = " . $ppc->child("RpPsCuttingPenType")->value() . "\n");
        fwrite($f, "PENALTY_TYPE_2 = " . $ppc->child("RpPsCuttingPenType")->value() . "\n");
        fwrite($f, "SPEED_LIMIT_PENALTY_2 = " . $ppc->child("RpPsCuttingPenType")->value() . "\n");

        // section Crossing
        fwrite($f, "\n[Crossing]\n");
        fwrite($f, "PENALTY_TYPE = " . $ppc->child("RpPsCrossingPenType")->value() . "\n");

        // section Jump_Start
        fwrite($f, "\n[Jump_Start]\n");
        fwrite($f, "PENALTY_TYPE_0 = " . $ppc->child("RpPsJumpStartPenType0")->value() . "\n");
        fwrite($f, "SPEED_LIMIT_PENALTY_0 = " . $ppc->child("RpPsJumpStartSpeedLimit0")->value() . "\n");
        fwrite($f, "PENALTY_TYPE_1 = " . $ppc->child("RpPsJumpStartPenType1")->value() . "\n");
        fwrite($f, "SPEED_LIMIT_PENALTY_1 = " . $ppc->child("RpPsJumpStartSpeedLimit1")->value() . "\n");
        fwrite($f, "PENALTY_TYPE_2 = " . $ppc->child("RpPsJumpStartPenType2")->value() . "\n");
        fwrite($f, "SPEED_LIMIT_PENALTY_2 = " . $ppc->child("RpPsJumpStartSpeedLimit2")->value() . "\n");

        // section Drs
        fwrite($f, "\n[Drs]\n");
        fwrite($f, "PENALTY_TYPE = " . $ppc->child("RpPsDRSPenType")->value() . "\n");
        fwrite($f, "GAP = " . $ppc->child("RpPsDRSGap")->value() . "\n");
        fwrite($f, "ENABLED_AFTER_LAPS = " . $ppc->child("RpPsDRSEnaAfterLaps")->value() . "\n");
        fwrite($f, "MIN_SPEED = " . $ppc->child("RpPsDRSMinSpeed")->value() . "\n");
        fwrite($f, "BONUS_TIME = " . $ppc->child("RpPsDRSBonusTime")->value() . "\n");
        fwrite($f, "MAX_ILLEGAL_USES = " . $ppc->child("RpPsJumpStartSpeedLimit2")->value() . "\n");
        fwrite($f, "ENABLED_DURING_SAFETY_CAR = " . (($ppc->child("RpPsDRSEnaDurSc")->value()) ? 1:0) . "\n");
        fwrite($f, "OMIT_CARS = " . $ppc->child("RpPsDRSOmitCars")->value() . "\n");

        // section Blue_Flag
        fwrite($f, "\n[Blue_Flag]\n");
        fwrite($f, "QUALIFY_TIME_THRESHOLD = " . $ppc->child("RpPsBfQual")->value() . "\n");
        fwrite($f, "RACE_TIME_THRESHOLD = " . $ppc->child("RpPsBfRace")->value() . "\n");


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
