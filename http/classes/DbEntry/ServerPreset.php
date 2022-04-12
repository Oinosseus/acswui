<?php

namespace DbEntry;

class ServerPreset extends DbEntry {

    private $ChildPresets = NULL;
    private $ParameterCollection = NULL;


    /**
     * Construct a new object
     * @param $id Database table id
     */
    protected function __construct(int $id) {
        parent::__construct("ServerPresets", $id);
    }


    //! @return TRUE if the preset is allowed to be used by the current user
    public function allowed() {
        $user = \Core\UserManager::currentUser();
        return  ($this->parameterCollection()->child("ACswuiPresetUsers")->containsUser($user)) ? TRUE : FALSE;
    }


    //! @return TRUE if any of the weathers in the preset is using custom shader patch weather
    public function anyWeatherUsesCsp() {
        $ppc = $this->parameterCollection();
        if (count($ppc->child("Weathers")->valueList()) > 0) {
            foreach ($ppc->child("Weathers")->valueList() as $w_id) {
                $weather = \DbEntry\Weather::fromId($w_id);
                if ($weather->parameterCollection()->child("Graphic")->csp()) return TRUE;
            }
        } else {
            $weather = \DbEntry\Weather::fromId(0);
            if ($weather->parameterCollection()->child("Graphic")->csp()) return TRUE;
        }
        return FALSE;
    }


    //! @return An array of ServerPreset objects that are children of this preset
    public function children() {
        if ($this->ChildPresets === NULL) {
            $this->ChildPresets = array();

            $res = \Core\Database::fetch("ServerPresets", ['Id'], ['Parent'=>$this->id()], 'Name');
            foreach ($res as $row) {
                $this->ChildPresets[] = ServerPreset::fromId($row['Id']);
            }
        }
        return $this->ChildPresets;
    }


    //! Delete this preset from the database
    public function delete() {

        // remove children
        foreach ($this->children() as $child) {
            $child->delete();
        }

        // remove from parents child list
        $parent = $this->parent();
        if ($parent !== NULL && $parent->ChildPresets !== NULL) {
            $new_parent_child_list = array();
            foreach ($parent->ChildPresets as $child) {
                if ($child->id() !== $this->id()) $new_parent_child_list[] = $child;
            }
            $parent->ChildPresets = $new_parent_child_list;
        }

        // delete from database
        $this->deleteFromDb();
    }


    /**
     * Create a new preset, which is derived from a parent.
     * @param $parent The parenting preset which shall be derived
     * @return A new ServerPreset object
     */
    public static function derive(ServerPreset $parent) {
        $new_id = \Core\Database::insert("ServerPresets", ['Name'=>"New Preset", 'Parent'=>$parent->id()]);
        $new_preset = ServerPreset::fromId($new_id);
        return $new_preset;
    }


    //! @return TRUE if a server run, when now started with this preset, will finish before $t
    public function finishedBefore(\DateTime $t) {

        // determine finishing time of the preset
        $finish = new \DateTime("now");
        foreach ($this->schedule() as [$interval, $uncertainty, $type, $name]) {
            $finish->add($interval->toDateInterval());
            $finish->add($uncertainty->toDateInterval());
        }

        // add maring
        $finish->add(new \DateInterval("PT10M"));

        return $finish < $t;
    }


    //! @return A ServerPreset object, retreived from database by ID ($id=0 will return a non editable default preset)
    public static function fromId(int $id) {

        $sp = NULL;

        // create default server preset when ID=0 is requested
        if ($id == 0) {

            $sp = new ServerPreset(0);

        // if $id is not 0, get from database
        } else {
            $sp = parent::getCachedObject("ServerPresets", "ServerPreset", $id);
        }

        return $sp;
    }


    /**
     * Access parameter values directly
     *
     * getParam("fooBar")
     * is the same as
     * (new parameterCollection()->child"fooBar")->value()
     * @return The curent value of a certain parameter
     */
    public function getParam(string $parameter_key) {
        return $this->parameterCollection()->child($parameter_key)->value();
    }


    /**
     * Retrieve all current existing presets as an array
     * The returned list is sorted by preset names
     * @param $allowed_only If set to TRUE (default) only presets are listed that are allowed for the current user
     * @return An array of ServerPreset objects
     */
    public static function listPresets(bool $allowed_only=TRUE) {
        $presets = array();

        foreach (\Core\Database::fetch("ServerPresets", ['Id'], [], 'Name') as $row) {
            $p = ServerPreset::fromId($row['Id']);

            if (!$allowed_only || $p->allowed()) {
                $presets[] = $p;
            }
        }

        return $presets;
    }


    //! @return The name of the preset
    public function name() {
        if ($this->id() === 0) return _("Factory Default");
        else return $this->loadColumn("Name");
    }


    //! @return The Collection object, that stores all parameters
    public function parameterCollection() {
        if ($this->ParameterCollection === NULL) {

            // derive collection from base
            if ($this->parent() !== NULL) {
                $base_collection = $this->parent()->parameterCollection();
                $this->ParameterCollection = new \Parameter\Collection($base_collection, NULL);
                $data_json = $this->loadColumn('ParameterData');
                $data_array = json_decode($data_json, TRUE);
                if ($data_array !== NULL) $this->ParameterCollection->dataArrayImport($data_array);

            // create collection
            } else {
                $this->ParameterCollection = new \Parameter\Collection(NULL, NULL, "Root", _("Root"), _("Collection of server preset settings"));


                // ------------------------------------------------------------
                //                    ACswui Preset Settings
                // ------------------------------------------------------------

                $coll_general = new \Parameter\Collection(NULL, $this->ParameterCollection, "ACswui1", _("ACswui"), _("General settings for the preset"));
                $p = new \Parameter\ParamString(NULL, $coll_general, "ACswuiPresetName", _("Name"), _("Name of the preset"), "", "");
                $p = new \Parameter\ParamBool(NULL, $coll_general, "ACswuiPreservedKick", _("Preseved Kick"), _("Kick drivers if they join with a car from the entry list that is preserved for other drivers."), "", TRUE);
                $p = new \Parameter\ParamSpecialGroups(NULL, $coll_general, "ACswuiPresetUsers", _("Users"), _("Which user groups are allowed to use this preset"), "", "");


                // ------------------------------------------------------------
                //                    AcServer Settings
                // ------------------------------------------------------------


                $coll_acserver = new \Parameter\Collection(NULL, $this->ParameterCollection, "AcServer", _("AC Server"), _("Assetto Corsa Server Sesstings"));


                ////////////////////
                // General Settings

                $coll_group = new \Parameter\Collection(NULL, $coll_acserver, "AcServerGeneral", _("General"), _("General settings for the AC server"));

                //! @todo Welcome Message
                $p = new \Parameter\ParamBool(NULL, $coll_group, "AcServerPickupMode", _("Pickup Mode"), _("If 0 the server start in booking mode (do not use it). Warning: in pickup mode you have to list only a circuit under TRACK and you need to list a least one car in the entry_list"), "", TRUE);
                $p = new \Parameter\ParamBool(NULL, $coll_group, "AcServerLockedEntryList", _("Locked Entries"), _("same as in booking mode, only players already included in the entry list can join the server (password not needed)."), "", FALSE);
                $p = new \Parameter\ParamString(NULL, $coll_group, "AcServerLegalTyres", _("Legal Tyres"), _("List of the tyre's that will be allowed in the server (separated by semicolon, eg. 'S;M;H')."), "", "");
                $p = new \Parameter\ParamInt(NULL, $coll_group, "AcServerResultScreenTime", _("Result Screen Time"), _("seconds of result screen between racing sessions."), "s", 60);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamText(NULL, $coll_group, "AcServerWelcomeMessage", _("Welcome Message"), _("A textual message that is displayed to drivers when joining the server"), "", "");

                // Environment
                $coll = new \Parameter\Collection(NULL, $coll_group, "AcServerEnvironment", _("Environment"), "");
                $p = new \Parameter\ParamTime(NULL, $coll, "SessionStartTime", _("Session Start Time"), _("Time at when the session shall start (vanilla AC will clip to the range 08:00 .. 18:00, only CSP weather can go beyond)"), "°", "08:00");
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerTimeMultiplier", _("Time Multiplier"), _("multiplier for the time of day"), "", 2);
                $p->setMin(0);
                $p->setMax(99);

                // voting
                $coll = new \Parameter\Collection(NULL, $coll_group, "AcServerVoting", _("Voting"), "");
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerKickQuorum", _("Kick Quorum"), _("Percentage that is required for the kick vote to pass"), "&percnt;", 75);
                $p->setMin(0);
                $p->setMax(100);
                $p = new \Parameter\ParamEnum(NULL, $coll, "AcServerBlacklistMode", _("Blacklist Mode"), _("0 = normal kick, kicked player can rejoin; 1 = kicked player cannot rejoin until server restart; 2 = kick player and add to blacklist.txt, kicked player can not rejoin unless removed from blacklist (Better to use ban_id command rather than set this)."));
                new \Parameter\EnumItem($p, 0, _("Rejoin"));
                new \Parameter\EnumItem($p, 1, _("Server Restart"));
                //! @todo !Blacklist handling not implemented yet
//                 new \Parameter\EnumItem($p, 2, _("Blacklist"));
                $p->setValue(1);
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerVotingQuorum", _("Voting Quorum"), _("Percentage that is required for the session vote to pass"), "&percnt;", 75);
                $p->setMin(0);
                $p->setMax(100);
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerVoteDuration", _("Vote Duration"), _("Vote length in seconds"), "s", 30);
                $p->setMin(0);
                $p->setMax(300);

                // weather
                $coll = new \Parameter\Collection(NULL, $coll_group, "WeatherGroup", _("Weather"), "");
                $p = new \Parameter\ParamSpecialWeathers(NULL, $coll, "Weathers", _("Weathers"), _("Select which weathers shall be used on the server"));


                ////////////////////
                // Session Settings

                $coll_group = new \Parameter\Collection(NULL, $coll_acserver, "AcServerSessions", _("Sessions"), _("Session Settings"));

                // booking
                $coll = new \Parameter\Collection(NULL, $coll_group, "AcServerSessionsBooking", _("Booking"), _("Settings for Booking Session"));
                $p = new \Parameter\ParamString(NULL, $coll, "AcServerBookingName", _("Name"), _("Name of the session"), "", "Booking");
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerBookingTime", _("Time"), _("Duration of session in Minutes"), "min", 0);
                $p->setMin(0);
                $p->setMax(999);

                // practice
                $coll = new \Parameter\Collection(NULL, $coll_group, "AcServerSessionsPractice", _("Practice"), _("Settings for Practice Session"));
                $p = new \Parameter\ParamString(NULL, $coll, "AcServerPracticeName", _("Name"), _("Name of the session"), "", "Practice");
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerPracticeTime", _("Time"), _("Duration of session in Minutes"), "Min", 60);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamEnum(NULL, $coll, "AcServerPracticeIsOpen", _("Is Open"), _("Either Session can be joined"));
                new \Parameter\EnumItem($p, 0, _("No Join"));
                new \Parameter\EnumItem($p, 1, _("Free Join"));
                $p->setValue(1);

                // qualifying
                $coll = new \Parameter\Collection(NULL, $coll_group, "AcServerSessionsQualifying", _("Qualifying"), _("Settings for Qualifying Session"));
                $p = new \Parameter\ParamString(NULL, $coll, "AcServerQualifyingName", _("Name"), _("Name of the session"), "", "Qualifying");
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerQualifyingTime", _("Time"), _("Duration of session in Minutes"), "Min", 15);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamEnum(NULL, $coll, "AcServerQualifyingIsOpen", _("Is Open"), _("Either Session can be joined"));
                new \Parameter\EnumItem($p, 0, _("No Join"));
                new \Parameter\EnumItem($p, 1, _("Free Join"));
                $p->setValue(1);
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerQualifyingWaitPerc", _("Wait"), _("this is the factor to calculate the remaining time in a qualify session after the session is ended: 120 means that 120% of the session fastest lap remains to end the current lap."), "&percnt;", 120);
                $p->setMin(0);
                $p->setMax(999);

                // race
                $coll = new \Parameter\Collection(NULL, $coll_group, "AcServerSessionsRace", _("Race"), _("Settings for Race Session"));
                $p = new \Parameter\ParamString(NULL, $coll, "AcServerRaceName", _("Name"), _("Name of the session"), "", "Race");
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerRaceLaps", _("Laps"), _("Amount of Laps for the race"), "Laps", 0);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerRaceTime", _("Time"), _("Amount of Minutes for the race (only if Laps=0)"), "Min", 60);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerRaceWaitTime", _("Wait Time"), _("Seconds before start of the Session"), "s", 300);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamEnum(NULL, $coll, "AcServerRaceIsOpen", _("Is Open"), _("Either Session can be joined"));
                new \Parameter\EnumItem($p, 0, _("No Join"));
                new \Parameter\EnumItem($p, 1, _("Free Join"));
                new \Parameter\EnumItem($p, 2, _("Join 20s"));
                $p->setValue(1);
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerRaceOverTime", _("Over Time"), _("Time remaining in seconds to finish the race from the moment the first one passes on the finish line"), "s", 600);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerPitWinOpen", _("Pit Window Open"), _("Pit window open at lap/minute (depends on the race mode)"), "", 0);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerPitWinClose", _("Pit Window Close"), _("Pit window closes at lap/minute (depends on the race mode)"), "", 0);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamEnum(NULL, $coll, "AcServerReversedGrid", _("Reversed Grid"), _("0 = no additional race, 1toX = only those position will be reversed for the next race, -1 = all the position will be reversed (Retired players will be on the last positions)"));
                new \Parameter\EnumItem($p, 0, _("No"));
                new \Parameter\EnumItem($p, -1, _("All"));
                for ($i=1; $i<=20; ++$i) new \Parameter\EnumItem($p, $i, $i . " " . _("Positions"));
                $p->setValue(0);
                $p = new \Parameter\ParamBool(NULL, $coll, "AcServerExtraLap", _("Extra Lap"), _("if it's a timed race, with 1 the race will not end when the time is over and the leader crosses the line, but the latter will be forced to drive another extra lap."), "", FALSE);


                ////////////////////
                // Realism Settings

                $coll_group = new \Parameter\Collection(NULL, $coll_acserver, "AcServerRealism", _("Realism"), _("Realism Settings"));

                // penalties
                $coll = new \Parameter\Collection(NULL, $coll_group, "AcServerPenalties", _("Penalties"), "");
                $p = new \Parameter\ParamBool(NULL, $coll, "AcServerRaceGasPenalty", _("Race Gas Penalty"), _("0  any cut will be penalized with the gas cut message; 1 no penalization will be forced, but cuts will be saved in the race result json."), "", FALSE);
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerMaxContactsPerKm", _("Max Contacts"), _("If a driver has more contacts per kilometer he will be reset to pit"), "1/km", -1);
                $p->setMin(-1);
                $p->setMax(99);
                $p = new \Parameter\ParamEnum(NULL, $coll, "AcServerStartRule", _("Start Rule"), _("0 is car locked until start;   1 is teleport   ; 2 is drivethru (if race has 3 or less laps then the Teleport penalty is enabled)"));
                new \Parameter\EnumItem($p, 0, _("Locked"));
                new \Parameter\EnumItem($p, 1, _("Teleport"));
                new \Parameter\EnumItem($p, 2, _("Drivethru"));
                $p->setValue(2);
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerTyresOut", _("Allowed Tyres Out"), _("Amount of tyres that are allowed to cross the driving line"), _("Tyres"), 2);
                $p->setMin(0);
                $p->setMax(4);

                // driving aids
                $coll = new \Parameter\Collection(NULL, $coll_group, "AcServerRealismDrivingAids", _("Driving Aids"), _("Allowance of systems that support drivability"));
                $p = new \Parameter\ParamEnum(NULL, $coll, "AcServerAbsAllowed", _("ABS"), _("Anti-Lock Braking System"));
                new \Parameter\EnumItem($p, 0, _("Disabled"));
                new \Parameter\EnumItem($p, 1, _("Car Dependent"));
                new \Parameter\EnumItem($p, 2, _("Enabled"));
                $p->setValue(1);
                $p = new \Parameter\ParamEnum(NULL, $coll, "AcServerTcAllowed", _("TC"), _("Traction Control"));
                new \Parameter\EnumItem($p, 0, _("Disabled"));
                new \Parameter\EnumItem($p, 1, _("Car Dependent"));
                new \Parameter\EnumItem($p, 2, _("Enabled"));
                $p->setValue(1);
                $p = new \Parameter\ParamEnum(NULL, $coll, "AcServerEscAllowed", _("ESC"), _("Electronic Stability Control"));
                new \Parameter\EnumItem($p, 0, _("Disabled"));
                new \Parameter\EnumItem($p, 1, _("Allow"));
                $p->setValue(1);
                $p = new \Parameter\ParamEnum(NULL, $coll, "AcServerAutoClutchAllowed", _("Automatic Clutch"), _("Automatic Clutch Assist"));
                new \Parameter\EnumItem($p, 0, _("Disabled"));
                new \Parameter\EnumItem($p, 1, _("Allow"));
                $p->setValue(1);
                $p = new \Parameter\ParamEnum(NULL, $coll, "AcServerTyreBlankets", _("Tyre Blankets"), _("At the start of the session or after the pitstop the tyre will have the the optimal temperature"));
                new \Parameter\EnumItem($p, 0, _("No"));
                new \Parameter\EnumItem($p, 1, _("Yes"));
                $p->setValue(0);
                $p = new \Parameter\ParamEnum(NULL, $coll, "AcServerForceVirtualMirror", _("Virtual Mirror"), _("With this setting the virtual mirror can be forced for every driver."));
                new \Parameter\EnumItem($p, 0, _("Optional"));
                new \Parameter\EnumItem($p, 1, _("Forced"));
                $p->setValue(0);

                // physics
                $coll = new \Parameter\Collection(NULL, $coll_group, "AcServerRealismPhysics", _("Physics"), _("Adjustment constants of physics"));
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerFuelRate", _("Fuel Rate"), _("Fuel usage from 0 (no fuel usage) to XXX (100 is the realistic one)"), "&percnt;", 100);
                $p->setMin(0);
                $p->setMax(1000);
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerDamageMultiplier", _("Damage Multiplier"), _("Damage from 0 (no damage) to 100 (full damage)"), "&percnt;", 100);
                $p->setMin(0);
                $p->setMax(100);
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerTyreWearRate", _("Tyre Wear Rate"), _("Tyre wear from 0 (no tyre wear) to XXX (100 is the realistic one)"), "&percnt;", 100);
                $p->setMin(0);
                $p->setMax(999);

                // dynamic track
                $coll = new \Parameter\Collection(NULL, $coll_group, "AcServerRealismDynamicTrack", _("Dynamic Track"), _("Dynamic Grip Level of the Track"));
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerDynamicTrackSessionStart", _("Start Grip"), _("Amount of Grip at session start"), "&percnt;", 96);
                $p->setMin(0);
                $p->setMax(100);
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerDynamicTrackRandomness", _("Randomness"), _("Level of randomness added to the start grip"), "&percnt;", 2);
                $p->setMin(0);
                $p->setMax(100);
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerDynamicTrackLapGain", _("Lap Gain"), _("How many laps are needed to increase the grip by 1%"), "Laps", 20);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerDynamicTrackSessionTransfer", _("Session Transfer"), _("How much of the gained grip is to be added to the next session 100 -> all the gained grip. Example: difference between starting (90) and ending (96) grip in the session = 6%, with session_transfer = 50 then the next session is going to start with 93."), "&percnt;", 50);
                $p->setMin(0);
                $p->setMax(100);


                // ------------------------------------------------------------
                //                     Real Penalty Settings
                // ------------------------------------------------------------

                $coll_rps = new \Parameter\Collection(NULL, $this->ParameterCollection, "RP", _("Real Penalty"), _("Real Penalty Plugin Sesstings"));


                //////////////
                // AcSettings

                $coll_group = new \Parameter\Collection(NULL, $coll_rps, "RpAcs", _("AC Settings"), "");

                // General
                $coll = new \Parameter\Collection(NULL, $coll_group, "RPAcsGeneral", _("General"), "");
                $p = new \Parameter\ParamBool(NULL, $coll, "RpAcsGeneralCockpitCam", _("Cockpit Camera"), "Set to true for mandatory cockpit visual for all drivers.", "", FALSE);
                $p = new \Parameter\ParamBool(NULL, $coll, "RpAcsGeneralWeatherChecksum", _("Weather Checksum"), "Set to true for mandatory cockpit visual for all drivers.", "", FALSE);
                $p = new \Parameter\ParamBool(NULL, $coll, "RpAcsSolMandatory", _("SOL Mandatory"), "Set to true if the event is with mod sol \"day to night transition\"", "", FALSE);
                $p = new \Parameter\ParamBool(NULL, $coll, "RpAcsCspMandatory", _("CSP Mandatory"), "Set if you want that all drivers need the Custom Shaders Patch (version 0.1.46 or newer) to diver on the server.", "", FALSE);

                // Safety Car
                $coll = new \Parameter\Collection(NULL, $coll_group, "RPAcsSc", _("Safety Car"), "");
                $p = new \Parameter\ParamString(NULL, $coll, "RpAcsScCarModel", _("Car Model"), _("Car model of safety car (folder name in assettocorsa/content/cars)"), "", "");
                $p = new \Parameter\ParamBool(NULL, $coll, "RpAcsScStartBehind", _("Sart Behind SC"), "Rolling start. Set to true if race starts afer the first lap beheind Safety Car (it works with or without a real Safety Car on the server.", "", FALSE);
                $p = new \Parameter\ParamFloat(NULL, $coll, "RpAcsScNormLightOff", _("Normalized Light Off Position"), _("Rolling start: normalized position (rance 0-1 - 0 = start of first lap, 1 = end of first lap) of the first driver during rolling start when the app switchs off the SC signal."), "", 0.5);
                $p->setMin(0);
                $p->setMax(10);
                $p = new \Parameter\ParamFloat(NULL, $coll, "RpAcsScNormStart", _("Normalized Start Position"), _("Rolling start: normalized position (racege 0 - 1.xx - 0 = start of first lap, 1 = end of first lap, >1 during second lap) of the first driver during rolling start when the app switchs on the red signal\nSTART_NORMALIZED_POSITION must be greater than LIGHT_OFF_NORMALIZED_POSITION"), "", 0.95);
                $p->setMin(0);
                $p->setMax(10);
                $p = new \Parameter\ParamFloat(NULL, $coll, "RpAcsScGreenDelay", _("Green Light Delay"), _("Rolling start: delay of the green light after red signal (seconds). Default 2.5"), "", 2.5);
                $p->setMin(0);
                $p->setMax(60);

                // No Penalty
                $coll = new \Parameter\Collection(NULL, $coll_group, "RPAcsNp", _("No Penalty"), "");
                $p = new \Parameter\ParamString(NULL, $coll, "RpAcsNpGuids", _("GUIDs"), _("List of the Steam GUIDs (seperated by a semicolon) that can connect to the server without the app and sol (for example \"Race Direction\" or for \"Live\") "), "", "");
                $p = new \Parameter\ParamString(NULL, $coll, "RpAcsNpCars", _("Cars"), _("List of car models (seperated by a semicolon) wihtout penalties and checks.\nExample: Cars=car_name1;car_name2,car_name3"), "");

                // Admin
                $coll = new \Parameter\Collection(NULL, $coll_group, "RPAcsAdmin", _("Admin"), "");
                $p = new \Parameter\ParamString(NULL, $coll, "RPAcsAdminGuids", _("GUIDs"), _("list of the Steam GUIDs. This drivers can send \"commands\" to the server via chat"), "", "");

                // Helicorsa
                $coll = new \Parameter\Collection(NULL, $coll_group, "RPAcsHc", _("Helicorsa"), "");
                $p = new \Parameter\ParamBool(NULL, $coll, "RPAcsHcMandatory", _("Mandatory"), "if you set true all driver have the helicorsa radar on the center of the screen and can not disabile it!! ", "", FALSE);
                $p = new \Parameter\ParamFloat(NULL, $coll, "RPAcsHcDistThld", _("Distance Threshold"), _("Distance threshold: How far away are the cars we paint?"), "", 30);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamFloat(NULL, $coll, "RPAcsHcWorldZoom", _("World Zoom"), _(" world coordinates zoom or how big the bars are"), "", 5);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamFloat(NULL, $coll, "RPAcsHcOpaThld", _("Opacity Threshold"), _("Opacity threshold: At wich distance (in meters) should the cars start to fade?"), "m", 8);
                $p->setMin(0);
                $p->setMax(100);
                $p = new \Parameter\ParamFloat(NULL, $coll, "RPAcsHcFrontFadeoutArc", _("Front Fadeout Arc"), _("fade out cars in front of the player in an arc of X degrees (0 to disable)"), "&deg;", 90);
                $p->setMin(0);
                $p->setMax(180);
                $p = new \Parameter\ParamFloat(NULL, $coll, "RPAcsHcFrontFadeAngle", _("Front Fade Angle"), _("if a car in front is faded out, how soft should it fade? (again in degrees, 0 to disable = on/off, 10? is default and quite nice)"), "&deg;", 10);
                $p->setMin(0);
                $p->setMax(180);
                $p = new \Parameter\ParamFloat(NULL, $coll, "RPAcsHcCarLength", _("Car Length"), _("Real Penalty reads the size of the cars from collider. Only if enable to do it because some error it use this option. Default 4.3/1.8"), "m", 4.3);
                $p->setMin(0);
                $p->setMax(30);
                $p = new \Parameter\ParamFloat(NULL, $coll, "RPAcsHcCarWidth", _("Car Width"), _("Real Penalty reads the size of the cars from collider. Only if enable to do it because some error it use this option. Default 4.3/1.8"), "m", 1.8);
                $p->setMin(0);
                $p->setMax(30);

                // Driver Swap
                $coll = new \Parameter\Collection(NULL, $coll_group, "RPAcsSwap", _("Driver Swap"), "");
                $p = new \Parameter\ParamBool(NULL, $coll, "RPAcsSwapEna", _("Enable"), "Enable the swap driver function for the race. Default false\nIMPORTANT: multi GUIDs in the locked entry list of the server are needed!!!!", "", FALSE);
                $p = new \Parameter\ParamInt(NULL, $coll, "RpAcsSwapMinTime", _("Min Time"), _("Min swap time (pit lane time) in seconds. Wihtout this value the swap does not work! No default"), "s", 0);
                $p->setMin(0);
                $p->setMax(99);
                $p = new \Parameter\ParamInt(NULL, $coll, "RpAcsSwapMinCount", _("Min Count"), _("min mandatory swap count for every team until the end of the race . Wihtout this value the swap does not work! Zero is also ok! No default"), "s", 0);
                $p->setMin(0);
                $p->setMax(99);
                $p = new \Parameter\ParamBool(NULL, $coll, "RPAcsSwapPenEna", _("Enable Penalty"), "Enable penalty for wrong swap (too fast). Default true\nIf true the team must remain in the pit line until counter = 0. If not it receive a Stop&Go Peanlty (seconds = penalty_time + time saved during swap).\nIf false each wrong swap will be not counted to reach the min_count (the team needs one more stop/swap to prevent disqualify)\nI advise against disabling this option!", "", TRUE);
                $p = new \Parameter\ParamInt(NULL, $coll, "RpAcsSwapPenTime", _("Penalty Time"), _("Only if enable_penalty = true. Additional penalty time for each invalid swap (for stop&go or to the final time) for each wrong swap."), "s", 5);
                $p->setMin(0);
                $p->setMax(99);
                $p = new \Parameter\ParamBool(NULL, $coll, "RPAcsSwapPenDurRace", _("Penalty During Race"), "Only if enable_penalty = true. Default true\nIf enabled the team must take the penalty for wrong swap during the race (stop & go N seconds -> N = penalty_time + remaining  from min_time). \nIf disable the team receive time penalty at the end of the race (for each invalid stop --> penalty_time + remaining from min_time)", "", TRUE);
                $p = new \Parameter\ParamBool(NULL, $coll, "RPAcsSwapConvertTimePen", _("Convert Time Penalty"), "add the time penalty from the last driver to the min current swap time. Valid only if penalty_during_race is set.", "", TRUE);
                $p = new \Parameter\ParamBool(NULL, $coll, "RPAcsSwapConvertRacePen", _("Convert Race Penalty"), "convert the penalties (DT and S&G) from the last driver to time penalty and add it to the min current swap time. Valid only if penalty_during_race is set.", "", TRUE);
                $p = new \Parameter\ParamInt(NULL, $coll, "RpAcsSwapDsqTime", _("Disqualify Time"), _("Only if enable_penalty = true. Disqualify if the team completes the swap and the remaining time > disqualify_time.\nExample: 50% of Min-Time"), "s", 0);
                $p->setMin(0);
                $p->setMax(99);
                $p = new \Parameter\ParamBool(NULL, $coll, "RPAcsSwapCntOnlyDrvChnge", _("Driver Change"), "If enable only the swaps with driver change are counted as valid for the min_count. If disable every reconnection is considered as valid swap (a driver con leave the server and rejoin the race --> swap is valid). Default True\nYou can set it to false and use the swap function as mandatory long pit stops. The driver has to leave the server and rejoin the race or a new driver can just join the server; both cases are considered valid long pit.\nThe false value is enable only if convert_time_penalty = false and convert_race_penalty = false", "", TRUE);

                // Teleport
                $coll = new \Parameter\Collection(NULL, $coll_group, "RPAcsTeleport", _("Teleport"), "");
                $p = new \Parameter\ParamInt(NULL, $coll, "RPAcsTeleportMaxDist", _("Max Distance"), _("Permitted max distance from the pit for teleport. Default 10\nThis value is valid only inside the pit lane! Every teleport from the track is not allowed! "), "m", 10);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamBool(NULL, $coll, "RPAcsTeleportEnaPractice", _("Practice Enabled"), "Teleport in practice session allowed.", "", TRUE);
                $p = new \Parameter\ParamBool(NULL, $coll, "RPAcsTeleportEnaQualify", _("Qualify Enabled"), "Teleport in qualify session allowed.", "", TRUE);
                $p = new \Parameter\ParamBool(NULL, $coll, "RPAcsTeleportEnaRace", _("Race Enabled"), "Teleport in race session allowed.", "", TRUE);


                ////////////////////
                // Penalty Settings

                $coll_group = new \Parameter\Collection(NULL, $coll_rps, "RpPs", _("Penalty Settings"), "");

                // General
                $coll = new \Parameter\Collection(NULL, $coll_group, "RpPsGeneral", _("General"), "");
                $p = new \Parameter\ParamInt(NULL, $coll, "RpPsGeneralLapsToTake", _("Laps To Take Penalty"), _("How many laps a driver has to take a penalty in. Default 3 (if LAPS_TO_TAKE_PENALTY > 2 --> Jump start is always 2 for AC limit!)"), "", 2);
                $p->setMin(0);
                $p->setMax(99);
                $p = new \Parameter\ParamInt(NULL, $coll, "RpPsGeneralPenSecs", _("Penalty Seconds"), _("Number of seconds to add manually to the final race result for all penalties not taken during the race."), "s", 20);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamInt(NULL, $coll, "RpPsGenerallastTimeNPen", _("Last Time Without Penalty"), _("Only for Time Race - Seconds at and of race to end race time (remembere the optional +1 lap is extra) without mandatory penalty\nAll panalties not taken ----> Panlty seconds to add to the final race time (in chat and log). Default 360 (6 minutes)\nIMPORTAN! Adjust this value in according to the track, the cars and the additional lap (ca. 3/5 laps but in according to LAPS_TO_TAKE_PENALTY)."), "s", 360);
                $p->setMin(0);
                $p->setMax(9999);
                $p = new \Parameter\ParamInt(NULL, $coll, "RpPsGenerallastLapsNPen", _("Last Laps Without Penalty"), _("If a divers gets a penalty in the last N laps (N = LAPS_TO_TAKE_PENALTY + LAST_LAPS_WITHOUT_PENALTY) can drive to the end of the race without taking it but receive time penalty! (Drive through = PENALTY_SECONDS, Stop & GO 10 = PENALTY_SECONDS + 10, .......)."), "Laps", 2);
                $p->setMin(0);
                $p->setMax(999);

                // Cutting
                $coll = new \Parameter\Collection(NULL, $coll_group, "RpPsCutting", _("Cutting"), "");
                $p = new \Parameter\ParamBool(NULL, $coll, "RpPsGeneralCutting", _("Enable"), "Set to true to enable cuttings penalties; false to issue warnings only.", "", TRUE);
                $p = new \Parameter\ParamBool(NULL, $coll, "RpPsCuttingEnaDurSc", _("During Safety Car"), "Cutting penalty enable during Safety Car or Virtual Safety Car.", "", FALSE);
                $p = new \Parameter\ParamInt(NULL, $coll, "RpPsCuttingTotCtWarn", _("Total Cut Warnings"), _("How many warnings are allowed before a penalty is given."), "", 3);
                $p->setMin(0);
                $p->setMax(99);
                $p = new \Parameter\ParamBool(NULL, $coll, "RpPsCuttingTyreDirt", _("Tyre Dirt"), "Tyres dirt for cutting.", "", FALSE);
                $p = new \Parameter\ParamInt(NULL, $coll, "RpPsCuttingMinSpeed", _("Minimum Speed"), _("The minimum speed, in kph, that will trigger a cut."), "km/h", 40);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamInt(NULL, $coll, "RpPsCuttingSecsBetween", _("Seconds Between Cuts"), _("You won't get a cut until this many seconds after the last one."), "s", 4);
                $p->setMin(0);
                $p->setMax(99);
                $p = new \Parameter\ParamInt(NULL, $coll, "RpPsCuttingMaxTime", _("Maximum Time"), _("The maximum time cut (in seconds) to trigger a warning. If you make this too long, off-track accidents may trigger cut warnings."), "s", 3);
                $p->setMin(0);
                $p->setMax(99);
                $p = new \Parameter\ParamFloat(NULL, $coll, "RpPsCuttingMinSlowdownRatio", _("Min Slowdown Ratio"), _("The minimum ratio of speed at leaving the track to speed at re-entering the track that will trigger a warning.\nA speed ratio under this means that the car has slowed, and negated any advantage gained, and a warning won't be triggered.\n0.9 means the car is re-entering the track at 90% of the speed at which it left it.\nA value < 1 means the car has slowed during the cut; a value > 1 means the car has sped up. Default 0.9."), "", 0.9);
                $p->setMin(0);
                $p->setMax(9);
                $p = new \Parameter\ParamFloat(NULL, $coll, "RpPsCuttingQualSlowdownSpeed", _("Qual Slow Down Speed"), _("Slowing down to this speed (kph) will make the \"INVALID LAP, SLOW DOWN\" message in qualifying go away. Default 50."), "km/h", 45);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamFloat(NULL, $coll, "RpPsCuttingQualMaxSecOutSpeed", _("Qual Max Sector Out Speed"), _("Initial qualify max allowed speed on the start line after cutting in the last corner. Default 150 \nIMPORTAN! Adjust this value in according to the track and the cars!!!!!!!!!"), "km/h", 150);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamFloat(NULL, $coll, "RpPsCuttingQualSlowdownRatio", _("Qual Slowdown Ratio"), _("Ratio of the QUALIFY_MAX_SECTOR_OUT_SPEED. Default 0.99\nThe max allowed speed on the start line after cutting  =\nQUALIFY_MAX_SECTOR_OUT_SPEED (other new max speed of driver) * QUALIFY_SLOW_DOWN_SPEED_RATIO"), "", 0.99);
                $p->setMin(0);
                $p->setMax(9);
                $p = new \Parameter\ParamInt(NULL, $coll, "RpPsCuttingPostCtTime", _("Post Cutting Time"), _("Bonus time after reentering on the track. If speed decreses in this time --> No cutting! Default 2.\nSet to 0 to have cutting checks similar to PLP (no check after re-entering the track)"), "s", 2);
                $p->setMin(0);
                $p->setMax(99);
                $p = new \Parameter\ParamSpecialPenaltyType(NULL, $coll, "RpPsCuttingPenType", _("Penalty Type"), _("Penalty type for cutting."), TRUE);

                // Speeding
                $coll = new \Parameter\Collection(NULL, $coll_group, "RpPsSpeeding", _("Speeding"), "");
                $p = new \Parameter\ParamBool(NULL, $coll, "RpPsGeneralSpeeding", _("Speeding"), "Set to true to enable pit lane speeding penalties.", "", TRUE);
                $p = new \Parameter\ParamInt(NULL, $coll, "RpPsSpeedingPitLaneSpeed", _("Pit Lane Speed"), _("The speed, in kph, above which you will be deemed to be speeding in pits. Make this higher to give more leniency on pit lane entry."), "km/h", 82);
                $p->setMin(0);
                $p->setMax(299);
                $p = new \Parameter\ParamSpecialPenaltyType(NULL, $coll, "RpPsSpeedingPenType0", _("Penalty Type 0"), _("Penalty type for speeding"), TRUE);
                $p = new \Parameter\ParamInt(NULL, $coll, "RpPsSpeedingSpeedLimit0", _("Speed Limit 0"), "", "km/h", 100);
                $p->setMin(0);
                $p->setMax(199);
                $p = new \Parameter\ParamSpecialPenaltyType(NULL, $coll, "RpPsSpeedingPenType1", _("Penalty Type 1"), _("Penalty type for speeding"), TRUE);
                $p->setValue("sg10");
                $p = new \Parameter\ParamInt(NULL, $coll, "RpPsSpeedingSpeedLimit1", _("Speed Limit 1"), "", "km/h", 200);
                $p->setMin(0);
                $p->setMax(599);
                $p = new \Parameter\ParamSpecialPenaltyType(NULL, $coll, "RpPsSpeedingPenType2", _("Penalty Type 2"), _("Penalty type for speeding"), TRUE);
                $p->setValue("dsq");
                $p = new \Parameter\ParamInt(NULL, $coll, "RpPsSpeedingSpeedLimit2", _("Speed Limit 2"), "", "km/h", 999);
                $p->setMin(0);
                $p->setMax(999);

                // Crossing
                $coll = new \Parameter\Collection(NULL, $coll_group, "RpPsCrossing", _("Crossing"), "");
                $p = new \Parameter\ParamBool(NULL, $coll, "RpPsGeneralCrossing", _("Crossing"), "Set to true to enable exit pit lane crossing line penalty.", "", TRUE);
                $p = new \Parameter\ParamSpecialPenaltyType(NULL, $coll, "RpPsCrossingPenType", _("Penalty Type"), _("Penalty type for speeding"), TRUE);

                // Jump Start
                $coll = new \Parameter\Collection(NULL, $coll_group, "RpPsJumpStart", _("Jump Start"), "");
                $p = new \Parameter\ParamSpecialPenaltyType(NULL, $coll, "RpPsJumpStartPenType0", _("Penalty Type 0"), _("Penalty type for speeding"), TRUE);
                $p = new \Parameter\ParamInt(NULL, $coll, "RpPsJumpStartSpeedLimit0", _("Speed Limit 0"), "", "km/h", 50);
                $p->setMin(0);
                $p->setMax(199);
                $p = new \Parameter\ParamSpecialPenaltyType(NULL, $coll, "RpPsJumpStartPenType1", _("Penalty Type 1"), _("Penalty type for speeding"), TRUE);
                $p->setValue("sg10");
                $p = new \Parameter\ParamInt(NULL, $coll, "RpPsJumpStartSpeedLimit1", _("Speed Limit 1"), "", "km/h", 100);
                $p->setMin(0);
                $p->setMax(599);
                $p = new \Parameter\ParamSpecialPenaltyType(NULL, $coll, "RpPsJumpStartPenType2", _("Penalty Type 2"), _("Penalty type for speeding"), TRUE);
                $p->setValue("dsq");
                $p = new \Parameter\ParamInt(NULL, $coll, "RpPsJumpStartSpeedLimit2", _("Speed Limit 2"), "", "km/h", 200);
                $p->setMin(0);
                $p->setMax(999);

                // DRS
                $coll = new \Parameter\Collection(NULL, $coll_group, "RpPsDRS", _("DRS"), "");
                $p = new \Parameter\ParamBool(NULL, $coll, "RpPsGeneralDrs", _("Drs"), "Set to true to enable DRS penalty (only for car wiht DRS).", "", TRUE);
                $p = new \Parameter\ParamSpecialPenaltyType(NULL, $coll, "RpPsDRSPenType", _("Penalty Type"), _("Penalty type for speeding"), TRUE);
                $p = new \Parameter\ParamFloat(NULL, $coll, "RpPsDRSGap", _("Gap"), _("Max gap in seconds from front car."), "", 1.0);
                $p->setMin(0);
                $p->setMax(29);
                $p = new \Parameter\ParamInt(NULL, $coll, "RpPsDRSEnaAfterLaps", _("Enabled After"), _("DRS enabled after N lap from start (+1 if rolling start with SC - file ac_settings.ini)."), "Laps", 1);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamInt(NULL, $coll, "RpPsDRSMinSpeed", _("Minimum Speed"), _("IF the car speed < MIN SPEED no penaly for illegal DRS use."), "km/h", 50);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamFloat(NULL, $coll, "RpPsDRSBonusTime", _("Bonus Time"), _("How many seconds the DRS can remain open during each illegal use before the penalty."), "s", 0.9);
                $p->setMin(0);
                $p->setMax(99);
                $p = new \Parameter\ParamInt(NULL, $coll, "RpPsDRSMaxIllegal", _("Illegal Uses"), _("How many time the driver can open the illegal DRS in each sector before the penalty."), "", 1);
                $p->setMin(0);
                $p->setMax(99);
                $p = new \Parameter\ParamBool(NULL, $coll, "RpPsDRSEnaDurSc", _("During Safetycar"), "DRS penalty enable during Safety Car or Virtual Safety Car.", "", FALSE);
                $p = new \Parameter\ParamString(NULL, $coll, "RpPsDRSOmitCars", _("Omit Cars"), _("List of cars wihtout DRS (sorry, no information from AC available)\nif all cars are without DRS set ENABLE_DRS_PENALTIES in General to false!!!"), "");

                // Blue Flag
                $coll = new \Parameter\Collection(NULL, $coll_group, "RpPsBf", _("Blue Flag"), "");
                $p = new \Parameter\ParamFloat(NULL, $coll, "RpPsBfQual", _("Qualifying"), _("Time distance (seconds) to show the blue flag in qualifying."), "s", 2.5);
                $p->setMin(0);
                $p->setMax(99);
                $p = new \Parameter\ParamFloat(NULL, $coll, "RpPsBfRace", _("Race"), _("Time distance (seconds) to show the blue flag in race."), "s", 2.5);
                $p->setMin(0);
                $p->setMax(99);


                // set all deriveable and visible
                $this->ParameterCollection->setAllAccessible();
            }
        }

        return $this->ParameterCollection;
    }


    //! @return The parenting ServerPreset object (can be NULL)
    public function parent() {
        if ($this->id() === NULL) return NULL;
        else if ($this->id() === 0) return NULL;
        else return ServerPreset::fromId($this->loadColumn("Parent"));
    }


    //! Store settings to database
    public function save() {
        if ($this->id() == 0) return;

        $column_data = array();

        // name, slot-id
        $column_data['Name'] = $this->parameterCollection()->child("ACswuiPresetName")->valueLabel();

        // parameter data
        $data_array = $this->parameterCollection()->dataArrayExport();
        $data_json = json_encode($data_array);
        $column_data['ParameterData'] = $data_json;

        $this->storeColumns($column_data);
    }


    /**
     * A list of entries.
     * Each entry contains a \Core\TimeInterval object as duration, a Core\TimeInterval as positive uncertainty and the name of the entry.
     * The last element contains the end of the session.
     *
     * Example to get the end of the session:
     * $session_time = new \DateTime("now");
     * foreach ($my_session->schedule() as [$interval, $uncertainty, $type, $name]) {
     *     $session_time->add($interval->toDateInterval());
     *     $session_time->add($uncertainty->toDateInterval());
     *     echo $session_time->format("c") , " $name<br>";
     * }
     * // now $session_time points to the latest end of the session (including uncertainty)
     *
     * The returned array will be like this:
     * [ [\Core\TimeInterval, \Core\TimeInterval, \DbEntry\Session::TypeQualifying, "Qualifying"],
     *   [\Core\TimeInterval, \Core\TimeInterval, \DbEntry\Session::TypeInvalid, "Pause"],
     *   [\Core\TimeInterval, \Core\TimeInterval, \DbEntry\Session::TypeRace, "Race"]
     * ]
     *
     * @return A list of [\Core\TimeInterval, \Core\TimeInterval, Name]-tuples
     */
    public function schedule(Track $t=NULL, CarClass $cc=NULL) {

        // estimate laptimes
        $laptime_typ = 0;  // [s]
        $laptime_max = 0;  // [s]
        if ($t !== NULL) {
            [$min, $typ, $max] = $t->estimeLaptime($cc);
            $laptime_typ = $typ / 1e3;
            $laptime_max = $max / 1e3;
        }


        $schedule = array();

        // Booking
        $t = $this->getParam("AcServerBookingTime");
        if ($t > 0) {
            $name = $this->getParam("AcServerBookingName");
            $interval = new \Core\TimeInterval($t * 60);
            $uncertainty = new \Core\TimeInterval(0);
            $schedule[] = array($interval, $uncertainty, Session::TypeBooking, $name);
        }

        // Practice
        $t = $this->getParam("AcServerPracticeTime");
        if ($t > 0) {
            $name = $this->getParam("AcServerPracticeName");
            $interval = new \Core\TimeInterval($t * 60);
            $uncertainty = new \Core\TimeInterval(0);
            $schedule[] = array($interval, $uncertainty, Session::TypePractice, $name);

            // Result Screen Time
            $t = $this->getParam("AcServerResultScreenTime");
            if ($t > 0) {
                $name = _("Result Screen");
                $interval = new \Core\TimeInterval($t);
                $uncertainty = new \Core\TimeInterval(0);
                $schedule[] = array($interval, $uncertainty, Session::TypeInvalid, $name);
            }
        }

        // Qualifying
        $t = $this->getParam("AcServerQualifyingTime");
        if ($t > 0) {
            $name = $this->getParam("AcServerQualifyingName");
            $interval = new \Core\TimeInterval($t * 60);
            $uncertainty = new \Core\TimeInterval($laptime_max * $this->getParam("AcServerQualifyingWaitPerc") / 100);
            $schedule[] = array($interval, $uncertainty, Session::TypeQualifying, $name);

            // Result Screen Time
            $rst = $this->getParam("AcServerResultScreenTime");
            if ($rst > 0) {
                $name = _("Result Screen");
                $interval = new \Core\TimeInterval($rst);
                $uncertainty = new \Core\TimeInterval();
                $schedule[] = array($interval, $uncertainty, Session::TypeInvalid, $name);
            }
        }

        // Race
        $races = ($this->getParam("AcServerReversedGrid") == 0) ? 1 : 2;
        for ($i=0; $i<$races; ++$i) {
            $t = $this->getParam("AcServerRaceTime");
            $l = $this->getParam("AcServerRaceLaps");
            if ($t > 0 || $l > 0) {  // wait time

                // wait time
                $wt = $this->getParam("AcServerRaceWaitTime");
                if ($wt > 0) {
                    $name = _("Wait Time");
                    $interval = new \Core\TimeInterval($wt);
                    $uncertainty = new \Core\TimeInterval(0);
                    $schedule[] = array($interval, $uncertainty, Session::TypeInvalid, $name);
                }

                // race
                $uncertainty = new \Core\TimeInterval(0);
                $interval = new \Core\TimeInterval(0);
                if ($l > 0) {  // lap race
                    $interval->add($l * $laptime_typ);
                    $uncertainty->add($l * ($laptime_max - $laptime_typ));
                } else if ($t > 0) {  // timed race
                    $interval->add($t * 60);
                }
                $ot = $this->getParam("AcServerRaceOverTime");
                if ($ot > 0) {
                    $uncertainty->add($ot);
                }
                if ($this->getParam("AcServerExtraLap")) {
                    $interval->add($laptime_typ);
                    $interval->add($laptime_max - $laptime_typ);
                }
                $name = $this->getParam("AcServerRaceName");
                $schedule[] = array($interval, $uncertainty, Session::TypeRace, $name);

                // Result Screen Time
                $rst = $this->getParam("AcServerResultScreenTime");
                if ($rst > 0) {
                    $name = _("Result Screen");
                    $interval = new \Core\TimeInterval($rst);
                    $uncertainty = new \Core\TimeInterval();
                    $schedule[] = array($interval, $uncertainty, Session::TypeInvalid, $name);
                }
            }
        }

        // Session End
        $name = _("End");
        $interval = new \Core\TimeInterval();
        $uncertainty = new \Core\TimeInterval();
        $schedule[] = array($interval, $uncertainty, Session::TypeInvalid, $name);

        return $schedule;
    }


    //! @return An array of Weather objects, which are used for this preset
    public function weathers() {
        $ppc = $this->parameterCollection();
        $weathers = array();
        if (count($ppc->child("Weathers")->valueList()) > 0) {
            foreach ($ppc->child("Weathers")->valueList() as $w_id) {
                $weathers[] = \DbEntry\Weather::fromId($w_id);
            }
        } else {
            $weathers[] = \DbEntry\Weather::fromId(0);
        }
        return $weathers;
    }
}
