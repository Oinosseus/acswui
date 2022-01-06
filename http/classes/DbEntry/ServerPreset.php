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
     * Retrieve all current existing presets as an array
     * The returned list is sorted by preset names
     * @param $allowed_only If set to TRUE (default) only presets are listed that are allowed for the current user
     * @return An array of ServerPreset objects
     */
    public static function listPresets(bool $allowed_only=TRUE) {
        $presets = array();

        // check current user
        $user = \Core\UserManager::loggedUser();
        if ($user !== NULL || $allowed_only === FALSE) {

            foreach (\Core\Database::fetch("ServerPresets", ['Id'], [], 'Name') as $row) {
                $p = ServerPreset::fromId($row['Id']);

                if ($allowed_only) {
                    if ($p->parameterCollection()->child("ACswuiPresetUsers")->containsUser($user)) {
                        $presets[] = $p;
                    }
                } else {
                    $presets[] = $p;
                }
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
                $p = new \Parameter\ParamSpecialTyres(NULL, $coll_group, "AcServerLegalTyres", _("Legal Tyres"), _("List of the tyre's that will be allowed in the server (unselect all to allow all)."), "", "");
                $p = new \Parameter\ParamInt(NULL, $coll_group, "AcServerResultScreenTime", _("Result Screen Time"), _("seconds of result screen between racing sessions."), "s", 60);
                $p->setMin(0);
                $p->setMax(999);

                // Environment
                $coll = new \Parameter\Collection(NULL, $coll_group, "AcServerEnvironment", _("Environment"), "");
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerSunAngle", _("Sun Angle"), _("angle of the position of the sun"), "°", 0);
                $p->setMin(-180);
                $p->setMax(180);
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
                $coll = new \Parameter\Collection(NULL, $coll_group, "AcServerWeatherGroup", _("Weather"), "");
                $p = new \Parameter\ParamSpecialWeathers(NULL, $coll, "AcServerWeather", _("Weathers"), _("Select which weathers shall be used on the server"));


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
                $p = new \Parameter\ParamInt(NULL, $coll, "AcServerTyresOut", _("Allowed Tyres Out"), _("Amount of tyres that are allowed to cross the driving line"), "", 2);
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

//                 $coll_group = new \Parameter\Collection(NULL, $this->ParameterCollection, "RP", _("Real Penalty"), _("Settings for the Real Penalty plugin"));

                // driving aids
                $coll = new \Parameter\Collection(NULL, $coll_rps, "FooBarBaz", _("Driving Aids"), _("Allowance of systems that support drivability"));
                $p = new \Parameter\ParamInt(NULL, $coll, "TyresOut", _("Allowed Tyres Out"), _("Amount of tyres that are allowed to cross the driving line"), "", 2);
                $p->setMin(0);
                $p->setMax(4);



                // set all deriveable and visible
                function __adjust_derived_collection($collection) {
                    $collection->derivedAccessability(2);
                    foreach ($collection->children() as $child) {
                        __adjust_derived_collection($child);
                    }
                }
                __adjust_derived_collection($this->ParameterCollection);
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
}
