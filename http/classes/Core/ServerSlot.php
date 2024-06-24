<?php

namespace Core;

class ServerSlot {

    private static $SlotObjectCache = array();  // key=slot-id, value=ServerSlot-object

    private $InvalidDummy = FALSE;
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


    //! @return An array of \Compound\SessionEntry objects that are currently online
    public function driversOnline() : array {

        $entries = array();

        // get current session of this slot
        $session = $this->currentSession();
        if ($session === NULL) return $entries;

        $res = \Core\Database::fetch("Users", ['Id'], ['CurrentSession'=>$session->id()]);
        foreach ($res as $row) {
            $user = \DbEntry\User::fromId($row['Id']);
            $teamcar = NULL;
            $carskin = NULL;

            // find last lap
            $query = "SELECT CarSkin, TeamCar FROM Laps WHERE Session={$session->id()} AND User={$user->id()} ORDER BY Id DESC LIMIT 1;";
            $res2 = \Core\Database::fetchRaw($query);
            if (count($res2) > 0) {
                $teamcar = \DbEntry\TeamCar::fromId((int) $res2[0]['TeamCar']);
                $carskin = \DbEntry\CarSkin::fromId((int) $res2[0]['CarSkin']);
            }

            // create new entry
            $entries[] = new \Compound\SessionEntry($session, $teamcar, $user, $carskin);
        }

        return $entries;
    }


    //! @return A ServerSlot object, retreived by Slot-ID ($id=0 will return a base preset)
    public static function fromId(int $slot_id) : ServerSlot {
        $ss = NULL;

        if (array_key_exists($slot_id, ServerSlot::$SlotObjectCache)) {
            $ss = ServerSlot::$SlotObjectCache[$slot_id];
        } else if ($slot_id > \Core\Config::ServerSlotAmount) {
            \Core\Log::debug("Deny requesting slot-id '$slot_id' at maximum slot amount of '" . \Core\Config::ServerSlotAmount . "'!");
            $ss = new ServerSlot();
            $ss->Id = -1;
            $ss->InvalidDummy = TRUE;
            ServerSlot::$SlotObjectCache[$ss->id()] = $ss;
        } else if ($slot_id < 0) {
            \Core\Log::warning("Deny requesting negative slot-id '$slot_id'!");
            $ss = new ServerSlot();
            $ss->Id = -1;
            $ss->InvalidDummy = TRUE;
            ServerSlot::$SlotObjectCache[$ss->id()] = $ss;
        } else {
            $ss = new ServerSlot();
            $ss->Id = $slot_id;
            ServerSlot::$SlotObjectCache[$ss->id()] = $ss;
        }

        return $ss;
    }


    private function getCspExtraOptions(\DbEntry\ServerPreset $preset) : String {
        $ppc = $preset->parameterCollection();
        if (!$ppc->child("CspActivate")->value()) return "";
        $ret = "";

        # EXTRA_RULES
        $ret .= "[EXTRA_RULES]\n";
        $ret .= "ALLOW_WRONG_WAY = " . (($ppc->child("CspExtraRulesAllowWrongWay")->value()) ? 1:0) . "\n";
        $ret .= "ENFORCE_BACK_TO_PITS_PENALTY = " . (($ppc->child("CspExtraRulesEnforceBackToPitsPenalty")->value()) ? 1:0) . "\n";
        $ret .= "ENFORCE_BACK_TO_PITS_STOP = " . (($ppc->child("CspExtraRulesEnforceBackToPitsStop")->value()) ? 1:0) . "\n";
        $ret .= "LIMIT_LOCK_CONTROLS_TIME = " . $ppc->child("CspExtraRulesLimitLockControlTime")->value() . "\n";
        $ret .= "LIMIT_LOCK_CONTROLS_TOTAL_TIME = " . $ppc->child("CspExtraRulesLimitLockControlTotalTime")->value() . "\n";
        $ret .= "NO_BACK_TO_PITS = " . (($ppc->child("CspExtraRulesNoBackToPits")->value()) ? 1:0) . "\n";
        $ret .= "NO_BACK_TO_PITS_OUTSIDE_OF_PITS = " . (($ppc->child("CspExtraRulesNoBackToPitsOutside")->value()) ? 1:0) . "\n";
        $ret .= "INVALIDATE_LAP_TIME_IN_PITS = " . (($ppc->child("CspExtraRulesInvalidateLaptimeInPits")->value()) ? 1:0) . "\n";
        $required_modules_list = $ppc->child("CspExtraRulesRequiredModules")->valueList();
        if (count($required_modules_list) > 0) $ret .= "REQUIRED_MODULES = " . implode(", ", $required_modules_list) . "\n";
        $ret .= "REQUIRE_NEW_LAP_FOR_DRIVETHROUGH_PENALTY = " . (($ppc->child("CspExtraRulesNewLapForDTPen")->value()) ? 1:0) . "\n";
        $ret .= "SLIPSTREAM_MULT = " . $ppc->child("CspExtraRulesSlipStreamMult")->value() . "\n";
        $ret .= "DISABLE_RAIN_PHYSICS = " . (($ppc->child("CspExtraRulesDisableRainPhysics")->value()) ? 1:0) . "\n";
        $ret .= "\n";

        # PITS_SPEED_LIMITER
        $ret .= "[PITS_SPEED_LIMITER]\n";
        $ret .= "DISABLE_FORCED = " . (($ppc->child("CspPitSpeedLimiterDisableForced")->value()) ? 1:0) . "\n";
        $ret .= "KEEP_COLLISIONS = " . (($ppc->child("CspPitSpeedLimiterKeepCollisions")->value()) ? 1:0) . "\n";
        $ret .= "SPEED_KMH = " . $ppc->child("CspPitSpeedLimiterSpeedKmH")->value() . "\n";
        $ret .= "SPEEDING_PENALTY = " . $ppc->child("CspPitSpeedLimiterSpeedingPenalty")->value() . "\n";
        $ret .= "SPEEDING_PENALTY_LAPS = " . $ppc->child("CspPitSpeedLimiterSpeedingPenaltyLaps")->value() . "\n";
        $ret .= "SPEEDING_SUBSEQUENT_PENALTY = " . $ppc->child("CspPitSpeedLimiterSpeedingSubsequentPenalty")->value() . "\n";
        $ret .= "SPEEDING_SUBSEQUENT_PENALTY_TIME = " . $ppc->child("CspPitSpeedLimiterSpeedingSubsequentPenaltyTime")->value() . "\n";
        $ret .= "\n";

        # EXTRA_TWEAKS
        $ret .= "[EXTRA_TWEAKS]\n";
        $ret .= "CUSTOM_MOTION = " . (($ppc->child("CspExtraTweeksCustomMotion")->value()) ? "1, SMOOTH":0) . "\n";
        $ret .= "JUMP_LIMIT = " . $ppc->child("CspExtraTweeksJumpLimit")->value() . "\n";
        $ret .= "JUMP_PAUSE_COLLISIONS_FOR = " . $ppc->child("CspExtraTweeksJumpPauseCollisions")->value() . "\n";
        $ret .= "\n";

        # EMERGENCY_RESET
        $ret .= "[EMERGENCY_RESET]\n";
        $ret .= "FALL = " . $ppc->child("CspEmergencyResetFall")->value() . "\n";
        $ret .= "COLLISION = " . $ppc->child("CspEmergencyResetCollision")->value() . "\n";
        $ret .= "PENALTY = " . (($ppc->child("CspEmergencyResetPenalty")->value()) ? 1:0) . "\n";
        $ret .= "\n";

        # CHAT
        $ret .= "[CHAT]\n";
        $ret .= "MESSAGES_FILTER = '^(/rp<|/rp>|RP<|RP>|/RPADMIN)'\n";
        $ret .= "SERVER_MESSAGES_FILTER = '^(/rp<|/rp>|RP<|RP>|/RPADMIN)'\n";
        $ret .= "COMMANDS_NONADMIN_FILTER = '^(/rp<|/rp>|RP<|RP>|/RPADMIN)'\n";
        $ret .= "\n";

        # encode
        $ret = gzcompress($ret);
        if ($ret === FALSE) {
            \Core\Log::warning("Failed to call gzcompress");
            return "";
        }
        $ret_base64 = base64_encode($ret);
        $ret = "";
        for ($i=0; $i<32; $i++) $ret .= "\t";
        $ret .= "\$CSP0:" . $ret_base64;

        return $ret;
    }


    //! @return A html string with a link to join via CM
    public function htmlJoin() {
        $html = "";
        $cm_port = $this->parameterCollection()->child("AcServerPortsInetHttp")->value();
        $ip = \Core\Helper::ip();
        $cm_link = "https://acstuff.ru/s/q:race/online/join?ip={$ip}&httpPort=$cm_port\n";
        $html .= "<a class=\"CoreServerSlot\" href=\"$cm_link\" target=\"_blank\">" . _("Join") . " {$this->name()}</a>";
        return $html;
    }


    //! @return The ID of the slot (number)
    public function id() : int {
        return $this->Id;
    }


    //! @return TRUE when this object is invalid
    public function invalid() : bool {
        return $this->InvalidDummy;
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
        if ($this->InvalidDummy) return "<div class=\"InvalidConfiguration\">Invalid-Server-Slot</div>";
        else if ($this->id() === 0) return _("Base Settings");
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
                $p = new \Parameter\ParamString(NULL, $coll, "AcServerGeneralAdminPwd", _("Admin Passwort"), _("acServer admin passwort"), "", "");
                $p = new \Parameter\ParamBool(NULL, $coll, "AcServerRegisterToLobby", _("Register To Lobby"), _("Makes the server listable by AC clients"), "", TRUE);

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

                /////////////////////
                // ac-server-wrapper

                $pc = new \Parameter\Collection(NULL, $root_collection, "AcServerWrapper", _("AC Server Wrapper"), _("Settings for the actual acServer"));

                $p = new \Parameter\ParamBool(NULL, $pc, "AcServerWrapperEnable", _("Enable"), _("Use ac-server-wrapper to start AC sevrer (enables enhanced information when clients use content manager)"), "", TRUE);

                $p = new \Parameter\ParamInt(NULL, $pc, "AcServerWrapperHttpPort", "TCP", _("HTTP port for AC Server Wrapper"), "", 9102);
                $p->setMin(1024);
                $p->setMax(65535);

                $p = new \Parameter\ParamInt(NULL, $pc, "AcServerWrapperDwnldSpdLim", _("Download Limit"), _("Limit download speed to keep online smooth. Set to 0 to avoid limiting. Just in case."), "kB", 512);
                $p->setMin(1);
                $p->setMax(10000);

                $p = new \Parameter\ParamBool(NULL, $pc, "AcServerWrapperDwnldPassOnly", _("Download Password"), _("Do not allow to download content without a password (if set)."), "", TRUE);

                $p = new \Parameter\ParamText(NULL, $pc, "AcServerWrapperWelcomeMsg", _("Welcome Message"), _("The introduction message that shall be shown at first (BBCode supported)"), "", "[center][b]Welcome[/b][/center]");


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

                $p = new \Parameter\ParamInt(NULL, $coll, "RPPortsPluginUdpL", "UDP_L1", _("Local UDP port to communicate with the acServer"), "", 9103);
                $p->setMin(1024);
                $p->setMax(65535);

                $p = new \Parameter\ParamInt(NULL, $coll, "RPPortsPluginUdpR", "UDP_R1", _("Remote UDP port for additional plugins"), "", 9104);
                $p->setMin(1024);
                $p->setMax(65535);

                $p = new \Parameter\ParamInt(NULL, $coll, "RPPortsPluginUdpR2", "UDP_R2", _("UDP port for the ACswui plugin to recieve RP evenrs"), "", 9108);
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

                $p = new \Parameter\ParamInt(NULL, $coll, "ACswuiPortsPluginUdpL", "UDP_L1", _("Local UDP port to communicate with the acServer"), "", 9106);
                $p->setMin(1024);
                $p->setMax(65535);

                $p = new \Parameter\ParamInt(NULL, $coll, "ACswuiPortsPluginUdpL2", "UDP_L2", _("Local UDP port of RP where it receives event-listen-start requests"), "", 9107);
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
                \Core\Log::debug("Server Slot Config file '$file_path' does not exist.");
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
        $pidfile = \Core\Config::AbsPathData . "/acserver/slot{$id}/acServer.pid";
        if (!file_exists($pidfile)) return NULL;
        $pid = (int) file_get_contents($pidfile);
        return $pid;
    }



    //! Store settings
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
        @chmod($file_path, 0660);
    }


    /**
     * start the server within this slot
     * @param $track TheTrack to use
     * @param $preset The ServerPreset for the server run
     * @param $el The EntryList which shall be used
     * @param $bm A BopMap object to apply BOP
     * @param $referenced_session_schedule_id The ID of the SessionSchedule object, that shall be linked to the session
     * @param $referenced_rser_split_id The ID of the RSerSplit object, that shall be linked to the session
     */
    public function start(\DbEntry\Track $track,
                          \DbEntry\ServerPreset $preset,
                          \Core\EntryList $el,
                          \Core\BopMap $bm,
                          int $referenced_session_schedule_id = NULL,
                          int $referenced_rser_split_id = NULL) {

        $id = $this->id();

        // configure real penalty
        $this->writeRpAcSettings($preset);
        $this->writeRpPenaltySettings($preset);
        $this->writeRpSettings();

        // configure ACswui plugin
        $this->writeACswuiUdpPluginIni($preset, $bm,
                                       $referenced_session_schedule_id,
                                       $referenced_rser_split_id);

        // configure ac server
        $el->writeToFile(\Core\Config::AbsPathData . "/acserver/slot{$this->id()}/cfg/entry_list.ini");
        $this->writeAcServerCfg($track, $preset, $el, $bm);

        // configure ac-server-wrapper
        $this->writeAcServerWrapperParams();
        $this->writeAcServerWrapperCmContent($el, $track);

        // lunch server with plugins
        $ac_server = \Core\Config::AbsPathData . "/acserver/acServer$id";
        $server_cfg = \Core\Config::AbsPathData . "/acserver/cfg/server_cfg_$id.ini";
        $entry_list = \Core\Config::AbsPathData . "/acserver/cfg/entry_list_$id.ini";
        $log_output = \Core\Config::AbsPathData . "/logs_acserver/acServer$id.log";
        $ac_server_command = "$ac_server -c $server_cfg -e $entry_list > $log_output 2>&1";

        // start server
        $datetime_str = (new \DateTime("now", new \DateTimezone("UTC")))->format(\DateTimeInterface::ATOM);
        $cmd_ret = 0;
        $cmd = "nohup ". \Core\Config::AbsPathAcswui . "/acswui.py srvrun";
        $cmd .= " \"" . \Core\Config::AbsPathData . "/acswui_udp_plugin/acswui_udp_plugin_$id.ini\" ";
        $cmd .= " --slot $id";
        if ($this->parameterCollection()->child("RPGeneralEnable")->value()) {
            $cmd .= " --real-penalty";
        }
        if ($this->parameterCollection()->child("AcServerWrapperEnable")->value()) {
            $cmd .= " --ac-server-wrapper";
        }
        $cmd .= " >" . \Core\Config::AbsPathData . "/logs_srvrun/slot$id.srvrun.{$datetime_str}.log 2>&1 &";
        $cmd_retstr = array();
        exec($cmd, $cmd_retstr, $cmd_ret);
        // foreach ($cmd_retstr as $line) echo "$line<br>";
        // echo "Server started: $cmd_ret<br>";
        // echo htmlentities($cmd) ."<br>";

        usleep(100e3);

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
        usleep(100e3);
    }


    /**
     * create acswui_udp_plugin.ini
     * @param $preset The ServerPreset for the server run
     * @param $bm A BopMap object to apply BOP
     * @param $referenced_session_schedule_id The ID of the SessionSchedule object, that shall be linked to the session
     * @param $referenced_rser_split_id The ID of the RSerSplit object, that shall be linked to the session
     */
    private function writeACswuiUdpPluginIni(\DbEntry\ServerPreset $preset,
                                             \Core\BopMap $bm,
                                             int $referenced_session_schedule_id = NULL,
                                             int $referenced_rser_split_id = NULL) {
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
        fwrite($f, "udp_plugin = " . $pc->child("ACswuiPortsPluginUdpL")->value() . "\n");
        if ($pc->child("RPGeneralEnable")->value()) {
            fwrite($f, "udp_acserver = " . $pc->child("RPPortsPluginUdpR")->value() . "\n");
            fwrite($f, "udp_rp_events_tx = " . $pc->child("RPPortsPluginUdpR2")->value() . "\n");
            fwrite($f, "udp_rp_events_rx = " . $pc->child("ACswuiPortsPluginUdpL2")->value() . "\n");
            fwrite($f, "rp_admin_password = " . $pc->child("RPGeneralAdminPwd")->value() . "\n");
        } else {
            fwrite($f, "udp_acserver = " . $pc->child("AcServerPortsPluginUdpR")->value() . "\n");
            fwrite($f, "udp_rp_events_tx = 0\n");
            fwrite($f, "udp_rp_events_rx = 0\n");
            fwrite($f, "rp_admin_password = \n");
        }
        fwrite($f, "preserved_kick = " . $preset->getParam("ACswuiPreservedKick") . "\n");
        fwrite($f, "referenced_session_schedule_id = $referenced_session_schedule_id\n");
        fwrite($f, "referenced_rser_split_id = $referenced_rser_split_id\n");
        fwrite($f, "auto-dnf-level = " . $preset->getParam("AccswuiAutoDnfLevel") . "\n");

        $bm->writeACswuiUdpPluginIni($f, $preset);

        fclose($f);
        @chmod($file_path, 0660);
    }


    /**
     * create server_cfg.ini for acServer
     * @param $track The Track object for the server run
     * @param $preset The ServerPreset for the server run
     * @param $el An EntryList object to extract cars from
     * @param $bm A BopMap object to retrieve maximum ballast
     */
    private function writeAcServerCfg(\DbEntry\Track $track,
                                      \DbEntry\ServerPreset $preset,
                                      \Core\EntryList $el,
                                      \Core\BopMap $bm) {

        $pc = $this->parameterCollection();
        $ppc = $preset->parameterCollection();
        $file_path = \Core\Config::AbsPathData . "/acserver/slot{$this->id()}/cfg/server_cfg.ini";
        $f = fopen($file_path, 'w');
        if ($f === FALSE) {
            \Core\Log::error("Cannot write to file '$file_path'!");
            return;
        }

        fwrite($f, "[SERVER]\n");
        fwrite($f, "NAME=" . $pc->child("AcServerGeneralName")->value() . "\n");
        fwrite($f, "PASSWORD=\n");
        fwrite($f, "ADMIN_PASSWORD=" . $pc->child("AcServerGeneralAdminPwd")->value() . "\n");
        fwrite($f, "UDP_PORT=" . $pc->child("AcServerPortsInetUdp")->value() . "\n");
        fwrite($f, "TCP_PORT=" . $pc->child("AcServerPortsInetTcp")->value() . "\n");
        fwrite($f, "HTTP_PORT=" . $pc->child("AcServerPortsInetHttp")->value() . "\n");
        fwrite($f, "SEND_BUFFER_SIZE=0\n");
        fwrite($f, "RECV_BUFFER_SIZE=0\n");
        fwrite($f, "CLIENT_SEND_INTERVAL_HZ=" . $pc->child("AcServerPerformanceClntIntvl")->value() . "\n");
        fwrite($f, "NUM_THREADS=" . $pc->child("AcServerPerformanceThreads")->value() . "\n");
        fwrite($f, "SLEEP_TIME=1\n");
        if ($this->parameterCollection()->child("AcServerRegisterToLobby")->value()) fwrite($f, "REGISTER_TO_LOBBY=1\n");
        else  fwrite($f, "REGISTER_TO_LOBBY=0\n");
        fwrite($f, "MAX_CLIENTS=" . count($el->entries()) . "\n");
        fwrite($f, "PICKUP_MODE_ENABLED=" . (($ppc->child("AcServerPickupMode")->value()) ? 1:0) . "\n");
        fwrite($f, "LOOP_MODE=0\n");  // ACswui system does require LOOP_MODE=0

        if ($preset->anyWeatherUsesCsp()) {
            $time_minutes = $ppc->child("SessionStartTime")->value();
            fwrite($f, "SUN_ANGLE=-80\n");
            fwrite($f, "TIME_OF_DAY_MULT=1\n");
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
        fwrite($f, "RACE_GAS_PENALTY_DISABLED=" . (($ppc->child("AcServerRaceGasPenalty")->value()) ? 0:1) . "\n");
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
        foreach ($el->entries() as $e) {
            $model = $e->carSkin()->car()->model();
            if (!in_array($model, $cars)) $cars[] = $model;
        }
        fwrite($f, "CARS=" . implode(";", $cars) . "\n");
        fwrite($f, "MAX_BALLAST_KG={$bm->maxBallast()}\n");
        fwrite($f, "TRACK=" . $track->location()->track() . "\n");
        fwrite($f, "CONFIG_TRACK=" . $track->config() . "\n");

        // create welcome message
        $welcome_message = trim($ppc->child("AcServerWelcomeMessage")->value());
        $welcome_message .= $this->getCspExtraOptions($preset);
        if (strlen($welcome_message) > 0) {
            $file_path_wm = \Core\Config::AbsPathData . "/acserver/slot{$this->id()}/cfg/welcome.txt";
            $f_wm = fopen($file_path_wm, 'w');
            if ($f_wm === FALSE) {
                \Core\Log::error("Cannot write to file '$file_path_wm'!");
            } else {
                fwrite($f_wm, $welcome_message);
                fclose($f_wm);
                @chmod($file_path_wm, 0660);
            }
            fwrite($f, "WELCOME_MESSAGE=$file_path_wm\n");
        }

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
        $weathers = $preset->weathers($track->location());
        switch ($ppc->child("WeatherRandomize")->value()) {
            case "server_run":
                $w = $weathers[rand(0, count($weathers) - 1)];
                $s = $this->writeAcServerCfgWeatherSection(0, $w, $preset);
                fwrite($f, "\n" . $s);
                break;

            // write all weather to server_cfg
            case "session":
                for ($i=0; $i<count($weathers); ++$i) {
                    $w = $weathers[$i];
                    $s = $this->writeAcServerCfgWeatherSection($i, $weathers[$i], $preset);
                    fwrite($f, "\n" . $s);
                }
                break;

            default:
                \Core\Log::error("Unnown value '{$ppc->child('WeatherRandomize')->value()}'!");
        }

        fclose($f);
        @chmod($file_path, 0660);
    }


    //! @return A string containing a weather section for the server_cfg.ini
    private function writeAcServerCfgWeatherSection(int $weather_index,
                                                    \DbEntry\Weather $weather,
                                                    \DbEntry\ServerPreset $preset
                                                    ) : string {
        $ppc = $preset->parameterCollection();
        $wpc = $weather->parameterCollection();

        $s = "";
        $s .= "[WEATHER_$weather_index]\n";

        $g = $wpc->child("Graphic");
        $g_str = $g->getGraphic();
        if ($g->csp()) {
            $g_str .= "_time=" . $ppc->child("SessionStartTime")->valueSeconds();
            $g_str .= "_mult=" . $ppc->child("AcServerTimeMultiplier")->value();
        }
        $s .= "GRAPHICS=$g_str\n";

        $base_temp_amb = $wpc->child("AmbientBase")->value();
        if ($base_temp_amb < 0) $base_temp_amb = 0;
        $variation_amebient = $wpc->child("AmbientVar")->value();
        if (($base_temp_amb - $variation_amebient) < 0) $variation_amebient = $base_temp_amb;
        $s .= "BASE_TEMPERATURE_AMBIENT={$base_temp_amb}\n";
        $s .= "VARIATION_AMBIENT=$variation_amebient\n";

        $base_temp_rd = $wpc->child("RoadBase")->value();
        if ($base_temp_rd < 0) $base_temp_rd = 0;
        $variation_rd = $wpc->child("RoadVar")->value();
        if (($base_temp_rd - $variation_rd) < 0) $variation_rd = $base_temp_rd;
        $s .= "BASE_TEMPERATURE_ROAD={$base_temp_rd}\n";
        $s .= "VARIATION_ROAD={$variation_rd}\n";

        $s .= "WIND_BASE_SPEED_MIN=" . $wpc->child("WindBaseMin")->value() . "\n";
        $s .= "WIND_BASE_SPEED_MAX=" . $wpc->child("WindBaseMax")->value() . "\n";
        $s .= "WIND_BASE_DIRECTION=" . $wpc->child("WindDirection")->value() . "\n";
        $s .= "WIND_VARIATION_DIRECTION=" . $wpc->child("WindDirectionVar")->value() . "\n";

        return $s;
    }



    private function writeAcServerWrapperParams() {
        $pc = $this->parameterCollection();

        $data_array = array();
        $data_array['description'] = $pc->child("AcServerWrapperWelcomeMsg")->value();
        $data_array['port'] = $pc->child("AcServerWrapperHttpPort")->value();
        $data_array['verboseLog'] = TRUE;
        $data_array['downloadSpeedLimit'] = $pc->child("AcServerWrapperDwnldSpdLim")->value() * 1e3;
        $data_array['downloadPasswordOnly'] = $pc->child("AcServerWrapperDwnldPassOnly")->value();
        $data_array['publishPasswordChecksum'] = $pc->child("AcServerWrapperDwnldPassOnly")->value();

        // write to file
        $data_json = json_encode($data_array,  JSON_PRETTY_PRINT);
        $file_path = \Core\Config::AbsPathData . "/acserver/slot{$this->id()}/cfg/cm_wrapper_params.json";
        $f = fopen($file_path, 'w');
        if ($f === FALSE) {
            \Core\Log::error("Cannot write to file '$file_path'!");
            return;
        }
        fwrite($f, $data_json);
        fclose($f);
        @chmod($file_path, 0660);
    }


    private function writeAcServerWrapperCmContent(\Core\EntryList $el, \DbEntry\Track $track) {
        $cm_content_dir = \Core\Config::AbsPathData . "/acserver/slot{$this->id()}/cfg/cm_content";

        // clear old content
        \Core\Helper::cleandir($cm_content_dir);

        // prepare data for content.json
        $data_array = array();

        // add track
        if (strlen($track->location()->downloadUrl()) > 0) {
            if (!array_key_exists("track", $data_array)) $data_array['track'] = array();
            $data_array['track']['url'] = $track->location()->downloadUrl();
        }

        // add cars and skins
        $processed_car_skin_ids = array();
        foreach ($el->entries() as $eli) {

            // do not process skins twice
            if (in_array($eli->CarSkin()->id(), $processed_car_skin_ids)) {
                continue;
            } else {
                $processed_car_skin_ids[] = $eli->CarSkin()->id();
            }

            // check if car download exists
            if (strlen($eli->carSkin()->car()->downloadUrl()) > 0) {

                // add cars entry
                if (!array_key_exists("cars", $data_array)) $data_array['cars'] = array();

                // add car
                $car_model = $eli->carSkin()->car()->model();
                if (!array_key_exists($car_model, $data_array['cars'])) {
                    $data_array['cars'][$car_model] = array();
                    $data_array['cars'][$car_model]['skins'] = array();
                }

                // add url
                $data_array['cars'][$car_model]['url'] = $eli->carSkin()->car()->downloadUrl();
            }

            // check if skin is packaged
            $csr = \DbEntry\CarSkinRegistration::fromCarSkinLatest($eli->CarSkin());
            if ($csr) {
                $package_path = $csr->packagedFilePath();
                if ($package_path) {
                    $dst = "$cm_content_dir/{$csr->packagedFileName()}";
                    $succ = copy($package_path, $dst);
                    if ($succ === FALSE) {
                        \Core\Log::error("Failed to copy '$package_path' to '$dst'");
                    } else {

                        // add cars entry
                        if (!array_key_exists("cars", $data_array)) $data_array['cars'] = array();

                        // add car
                        $car_model = $eli->carSkin()->car()->model();
                        if (!array_key_exists($car_model, $data_array['cars'])) {
                            $data_array['cars'][$car_model] = array();
                            $data_array['cars'][$car_model]['skins'] = array();
                        }

                        // add skin
                        $skin = $eli->carSkin()->skin();
                        if (!array_key_exists($car_model, $data_array['cars'][$car_model]['skins'])) {
                            $data_array['cars'][$car_model]['skins'][$skin] = array();
                        }

                        $data_array['cars'][$car_model]['skins'][$skin]['file'] = $csr->packagedFileName();
                        $data_array['cars'][$car_model]['skins'][$skin]['version'] = $csr->id();
                    }
                }
            }
        }

        // write content.json to file
        $data_json = json_encode($data_array,  JSON_PRETTY_PRINT);
        $file_path = "$cm_content_dir/content.json";
        $f = fopen($file_path, 'w');
        if ($f === FALSE) {
            \Core\Log::error("Cannot write to file '$file_path'!");
            return;
        }
        fwrite($f, $data_json);
        fclose($f);
        chmod($file_path, 0660);
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
        fwrite($f, "MANDATORY = " . (($ppc->child("RpAcsSolMandatory")->value()) ? "true":"false") . "\n");
        fwrite($f, "CHECK_FREQUENCY = " . $pc->child("RPGeneralCF")->value() . "\n");

        // section Custom_Shaders_Patch
        fwrite($f, "\n[Custom_Shaders_Patch]\n");
        fwrite($f, "MANDATORY = " . (($ppc->child("RpAcsCspMandatory")->value()) ? "true":"false") . "\n");
        fwrite($f, "CHECK_FREQUENCY = " . $pc->child("RPGeneralCF")->value() . "\n");

        // section Safety_Car
        fwrite($f, "\n[Safety_Car]\n");
        fwrite($f, "CAR_MODEL = " . $ppc->child("RpAcsScCarModel")->value() . "\n");
        fwrite($f, "RACE_START_BEHIND_SC = " . (($ppc->child("RpAcsScStartBehind")->value()) ? "true":"false") . "\n");
        fwrite($f, "NORMALIZED_LIGHT_OFF_POSITION = " . $ppc->child("RpAcsScNormLightOff")->value() . "\n");
        fwrite($f, "NORMALIZED_START_POSITION = " . $ppc->child("RpAcsScNormStart")->value() . "\n");
        fwrite($f, "GREEN_LIGHT_DELAY = " . $ppc->child("RpAcsScGreenDelay")->value() . "\n");
        // fwrite($f, "too_slow_delta = " . $ppc->child("RpAcsScTooSlowDelta")->value() . "\n");

        //! @todo New features not supported yet
        // fwrite($f, "vsc_reference_lap_name = \n");
        // fwrite($f, "vsc_slow_ratio = \n");
        // fwrite($f, "vsc_delta_threshold = \n");
        // fwrite($f, "vsc_delay_threshold = \n");
        // fwrite($f, "vsc_delta_delay = \n");
        // fwrite($f, "vsc_penalty_type_0 = \n");
        // fwrite($f, "vsc_delta_limit_0 = \n");
        // fwrite($f, "vsc_penalty_type_1 = \n");
        // fwrite($f, "vsc_delta_limit_1 = \n");
        // fwrite($f, "vsc_penalty_type_2 = \n");
        // fwrite($f, "vsc_delta_limit_2 = \n");
        // fwrite($f, "full_course_yellow_speed = \n");
        // fwrite($f, "rolling_start_speed = \n");
        // fwrite($f, "normalized_speed_limiter_position = \n");

        // section No_Penalty
        fwrite($f, "\n[No_Penalty]\n");
        $guids = trim($ppc->child("RpAcsNpGuids")->value());
        $tv_cars = trim(\Core\ACswui::getParam('TVCarGuids'));
        if (strlen($tv_cars)) {
            if (strlen($guids)) $guids .= ";";
            $guids .= $tv_cars;
        }
        fwrite($f, "GUIDs = $guids\n");
        fwrite($f, "Cars = " . $ppc->child("RpAcsNpCars")->value() . "\n");

        // section Admin
        fwrite($f, "\n[Admin]\n");
        fwrite($f, "GUIDs = " . $ppc->child("RPAcsAdminGuids")->value() . "\n");

        // section Helicorsa
        fwrite($f, "\n[Helicorsa]\n");
        fwrite($f, "MANDATORY = " . (($ppc->child("RPAcsHcMandatory")->value()) ? "true":"false") . "\n");
        fwrite($f, "DISTANCE_THRESHOLD = " . $ppc->child("RPAcsHcDistThld")->value() . "\n");
        fwrite($f, "WORLD_ZOOM = " . $ppc->child("RPAcsHcWorldZoom")->value() . "\n");
        fwrite($f, "OPACITY_THRESHOLD = " . $ppc->child("RPAcsHcOpaThld")->value() . "\n");
        fwrite($f, "FRONT_FADE_OUT_ARC = " . $ppc->child("RPAcsHcFrontFadeoutArc")->value() . "\n");
        fwrite($f, "FRONT_FADE_ANGLE = " . $ppc->child("RPAcsHcFrontFadeAngle")->value() . "\n");
        fwrite($f, "CAR_LENGHT = " . $ppc->child("RPAcsHcCarLength")->value() . "\n");
        fwrite($f, "CAR_WIDTH = " . $ppc->child("RPAcsHcCarWidth")->value() . "\n");

        // section Swap
        fwrite($f, "\n[Swap]\n");
        fwrite($f, "enable = " . (($ppc->child("RPAcsSwapEna")->value()) ? "true":"false") . "\n");
        fwrite($f, "min_time = " . $ppc->child("RpAcsSwapMinTime")->value() . "\n");
        fwrite($f, "min_count = " . $ppc->child("RpAcsSwapMinCount")->value() . "\n");
        fwrite($f, "enable_penalty = " . (($ppc->child("RPAcsSwapPenEna")->value()) ? "true":"false") . "\n");
        fwrite($f, "penalty_time = " . $ppc->child("RpAcsSwapPenTime")->value() . "\n");
        fwrite($f, "penalty_during_race = " . (($ppc->child("RPAcsSwapPenDurRace")->value()) ? "true":"false") . "\n");
        fwrite($f, "convert_time_penalty = " . (($ppc->child("RPAcsSwapConvertTimePen")->value()) ? "true":"false") . "\n");
        fwrite($f, "convert_race_penalty = " . (($ppc->child("RPAcsSwapConvertRacePen")->value()) ? "true":"false") . "\n");
        fwrite($f, "disqualify_time = " . $ppc->child("RpAcsSwapDsqTime")->value() . "\n");
        fwrite($f, "count_only_driver_change = " . (($ppc->child("RPAcsSwapCntOnlyDrvChnge")->value()) ? "true":"false") . "\n");
        fwrite($f, "min_time_for_every_pit = " . (($ppc->child("RPAcsSwapMinTEvryPt")->value()) ? "true":"false") . "\n");

        // section Teleport
        fwrite($f, "\n[Teleport]\n");
        fwrite($f, "max_distance = " . $ppc->child("RPAcsTeleportMaxDist")->value() . "\n");
        fwrite($f, "practice_enable = " . (($ppc->child("RPAcsTeleportEnaPractice")->value()) ? "true":"false") . "\n");
        fwrite($f, "qualify_enable = " . (($ppc->child("RPAcsTeleportEnaQualify")->value()) ? "true":"false") . "\n");
        fwrite($f, "race_enable = " . (($ppc->child("RPAcsTeleportEnaRace")->value()) ? "true":"false") . "\n");

        fclose($f);
        @chmod($file_path, 0660);
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
        fwrite($f, "ENABLE_CUTTING_PENALTIES = " . (($ppc->child("RpPsGeneralCutting")->value()) ? "true":"false") . "\n");
        fwrite($f, "ENABLE_SPEEDING_PENALTIES = " . (($ppc->child("RpPsGeneralSpeeding")->value()) ? "true":"false") . "\n");
        fwrite($f, "ENABLE_CROSSING_PENALTIES = " . (($ppc->child("RpPsGeneralCrossing")->value()) ? "true":"false") . "\n");
        fwrite($f, "ENABLE_DRS_PENALTIES = " . (($ppc->child("RpPsGeneralDrs")->value()) ? "true":"false") . "\n");
        fwrite($f, "LAPS_TO_TAKE_PENALTY = " . $ppc->child("RpPsGeneralLapsToTake")->value() . "\n");
        fwrite($f, "PENALTY_SECONDS = " . $ppc->child("RpPsGeneralPenSecs")->value() . "\n");
        fwrite($f, "LAST_TIME_WITHOUT_PENALTY = " . $ppc->child("RpPsGenerallastTimeNPen")->value() . "\n");
        fwrite($f, "LAST_LAPS_WITHOUT_PENALTY = " . $ppc->child("RpPsGenerallastLapsNPen")->value() . "\n");

        // section Cutting
        fwrite($f, "\n[Cutting]\n");
        fwrite($f, "ENABLED_DURING_SAFETY_CAR = " . (($ppc->child("RpPsCuttingEnaDurSc")->value()) ? "true":"false") . "\n");
        fwrite($f, "TOTAL_CUT_WARNINGS = " . $ppc->child("RpPsCuttingTotCtWarn")->value() . "\n");
        fwrite($f, "ENABLE_TYRES_DIRT_LEVEL = " . (($ppc->child("RpPsCuttingTyreDirt")->value()) ? "true":"false") . "\n");
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
        fwrite($f, "PIT_LANE_SPEED = " . $ppc->child("RpPsSpeedingPitLaneSpeed")->value() . "\n");
        fwrite($f, "PENALTY_TYPE_0 = " . $ppc->child("RpPsSpeedingPenType0")->value() . "\n");
        fwrite($f, "SPEED_LIMIT_PENALTY_0 = " . $ppc->child("RpPsSpeedingSpeedLimit0")->value() . "\n");
        fwrite($f, "PENALTY_TYPE_1 = " . $ppc->child("RpPsSpeedingPenType1")->value() . "\n");
        fwrite($f, "SPEED_LIMIT_PENALTY_1 = " . $ppc->child("RpPsSpeedingSpeedLimit1")->value() . "\n");
        fwrite($f, "PENALTY_TYPE_2 = " . $ppc->child("RpPsSpeedingPenType2")->value() . "\n");
        fwrite($f, "SPEED_LIMIT_PENALTY_2 = " . $ppc->child("RpPsSpeedingSpeedLimit2")->value() . "\n");
        fwrite($f, "PENALTY_TYPE_3 = " . $ppc->child("RpPsSpeedingPenType3")->value() . "\n");
        fwrite($f, "SPEED_LIMIT_PENALTY_3 = " . $ppc->child("RpPsSpeedingSpeedLimit3")->value() . "\n");
        fwrite($f, "PENALTY_TYPE_4 = " . $ppc->child("RpPsSpeedingPenType4")->value() . "\n");
        fwrite($f, "SPEED_LIMIT_PENALTY_5 = 99999\n");

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
        fwrite($f, "MAX_ILLEGAL_USES = " . $ppc->child("RpPsDRSMaxIllegal")->value() . "\n");
        fwrite($f, "ENABLED_DURING_SAFETY_CAR = " . (($ppc->child("RpPsDRSEnaDurSc")->value()) ? "true":"false") . "\n");
        fwrite($f, "OMIT_CARS = " . $ppc->child("RpPsDRSOmitCars")->value() . "\n");

        // section Blue_Flag
        fwrite($f, "\n[Blue_Flag]\n");
        fwrite($f, "QUALIFY_TIME_THRESHOLD = " . $ppc->child("RpPsBfQual")->value() . "\n");
        fwrite($f, "RACE_TIME_THRESHOLD = " . $ppc->child("RpPsBfRace")->value() . "\n");

        fclose($f);
        @chmod($file_path, 0660);
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
        fwrite($f, "AC_SERVER_PATH = " . \Core\Config::AbsPathData . "/acserver/slot$id\n");
        fwrite($f, "AC_CFG_FILE = " . \Core\Config::AbsPathData . "/acserver/slot$id/cfg/server_cfg.ini\n");
        fwrite($f, "AC_TRACKS_FOLDER = " . \Core\Config::AbsPathData . "/acserver/slot$id/content/tracks\n");
        fwrite($f, "AC_WEATHER_FOLDER = " . \Core\Config::AbsPathData . "/acserver/slot$id/content/weather\n");
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
        fwrite($f, "external_interface_udp_port = " . $pc->child("RPPortsPluginUdpR2")->value() . "\n");

        fwrite($f, "\n[Plugins_Relay]\n");
        fwrite($f, "UDP_PORT = " . $pc->child("RPPortsPluginUdpR")->value() . "\n");
        fwrite($f, "OTHER_UDP_PLUGIN = 127.0.0.1:" . $pc->child("ACswuiPortsPluginUdpL")->value() . "\n");

        fclose($f);
        @chmod($file_path, 0660);
    }
}
