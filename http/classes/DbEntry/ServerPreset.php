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


    //! @return TRUE, when the preset is deriveable by the user $user
    public function isDeriveable(User $user) {

        if ($user === NULL) return FALSE;
        if ($user->isRoot()) return TRUE;

        // scan for allowed deriveable presets
        $query_where = "";
        foreach ($user->groups() as $group) {
            if (strlen($query_where) > 0) $query_where .= " OR ";
            $query_where .= "`Group`=" . $group->id();
        }
        $query = "SELECT DISTINCT ServerPreset FROM ServerPresetDerivers WHERE ServerPreset=" . $this->id() . " AND ($query_where);";
        $res = \Core\Database::fetchRaw($query);
        return count($res) == 1;
    }


//     //! @return An array of the first ServerPreset objects that are in the child hierarchy and are deriveable by the current logged user
//     private function listChildDeriveable() {
//     }
//
//
//     //! @return An array of ServerPreset objects (top-level parents) that can be derived by the current user group
//     public function listTopDeriveable() {
//
//         $current_user = \Core\UserManager::loggedUser();
//         $sp = array();
//
//         if ($current_user == NULL) {
//             // nothing for unlogged users
//
//         } else if ($current_user->isRoot()) {
//             // top level preset for root users
//             $sp[] = ServerPreset::fromId(0);
//
//         } else {
//
//             if (count($current_user->groups()) > 0) {
//
//
//                 $top_level_preset = ServerPreset::fromId(0);
//
//
//                 // scan for allowed deriveable presets
//                 $query_where = "";
//                 foreach ($current_user->groups() as $group) {
//                     if (strlen($query_where) > 0) $query_where .= " OR ";
//                     $query_where .= "`Group`=" . $group->id();
//                 }
//                 $query = "SELECT DISTINCT ServerPreset FROM ServerPresetDerivers WHERE $query_where ORDER BY ServerPreset;";
//                 $res = \Core\Database::fetchRaw($query);
//                 $deriveable_presets = array();
//                 foreach ($res as $row) {
//                     $deriveable_presets [] = ServerPreset::fromId($row['ServerPreset']);
//                 };
//
//                 // remove presets, where parent is already in list
//                 for ($i=0; $i < count($deriveable_presets); ++$i) {
//                     if ($deriveable_presets[$i] !== NULL) {
//                         // set entry to NULL if parent preset is already in the list
//                         $parent_preset = $deriveable_presets[$i]->parent();
//                         if (in_array($parent_preset, $deriveable_presets)) {
//                             $deriveable_presets[$i] = NULL;
//                         }
//                     }
//                 }
//             }
//         }
//
//         return $sp;
//     }


    //! @return The name of the preset
    public function name() {
        if ($this->id() === 0) return _("Default");
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

//                 $c1p1 = new \Parameter\ParamBool(NULL, $this->ParameterCollection, "C1P1", "Parameter 1", "First Parameter", "°C", TRUE);
//                 $c1p2 = new \Parameter\ParamBool(NULL, $this->ParameterCollection, "C1P2", "Parameter 2", "Second Parameter", "km", FALSE);
//                 $c1c1 = new \Parameter\Collection(NULL, $this->ParameterCollection, "C1C1", "Empty Collection", "Just for testing");
//                 $c1p3 = new \Parameter\ParamString(NULL, $this->ParameterCollection, "C1P3", "Parameter 3", "Third Parameter", "", "S;H;L");
//
//                 $p = new \Parameter\ParamInt(NULL, $this->ParameterCollection, "C1P4", "ParamInt", "Integer Parameter", "Laps", 3);
//                 $p->setMin(0);
//                 $p->setMax(100);


                // ------------------------------------------------------------
                //                    Session Settings
                // ------------------------------------------------------------

                $coll_group = new \Parameter\Collection(NULL, $this->ParameterCollection, "Sessions", _("Sessions"), _("Session Settings"));

                // booking
                $coll = new \Parameter\Collection(NULL, $coll_group, "Booking", _("Booking"), _("Settings for Booking Session"));
                $p = new \Parameter\ParamString(NULL, $coll, "Name", _("Name"), _("Name of the session"), "", "Booking");
                $p = new \Parameter\ParamInt(NULL, $coll, "Time", _("Time"), _("Duration of session in Minutes"), "min", 30);
                $p->setMin(0);
                $p->setMax(999);

                // practice
                $coll = new \Parameter\Collection(NULL, $coll_group, "Practice", _("Practice"), _("Settings for Practice Session"));
                $p = new \Parameter\ParamString(NULL, $coll, "Name", _("Name"), _("Name of the session"), "", "Practice");
                $p = new \Parameter\ParamInt(NULL, $coll, "Time", _("Time"), _("Duration of session in Minutes"), "min", 30);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamEnum(NULL, $coll, "IsOpen", _("Is Open"), _("Either Session can be joined"));
                new \Parameter\EnumItem($p, 0, _("No Join"));
                new \Parameter\EnumItem($p, 1, _("Free Join"));
                $p->setValue(1);

                // qualifying
                $coll = new \Parameter\Collection(NULL, $coll_group, "Qualifying", _("Qualifying"), _("Settings for Qualifying Session"));
                $p = new \Parameter\ParamString(NULL, $coll, "Name", _("Name"), _("Name of the session"), "", "Qualifying");
                $p = new \Parameter\ParamInt(NULL, $coll, "Time", _("Time"), _("Duration of session in Minutes"), "min", 30);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamEnum(NULL, $coll, "IsOpen", _("Is Open"), _("Either Session can be joined"));
                new \Parameter\EnumItem($p, 0, _("No Join"));
                new \Parameter\EnumItem($p, 1, _("Free Join"));
                $p->setValue(1);

                // race
                $coll = new \Parameter\Collection(NULL, $coll_group, "Race", _("Race"), _("Settings for Race Session"));
                $p = new \Parameter\ParamString(NULL, $coll, "Name", _("Name"), _("Name of the session"), "", "Race");
                $p = new \Parameter\ParamInt(NULL, $coll, "Laps", _("Laps"), _("Amount of Laps for the race"), "Laps", 0);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamInt(NULL, $coll, "Time", _("Time"), _("Amount of Minutes for the race"), "Laps", 0);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamInt(NULL, $coll, "WaitTime", _("Wait Time"), _("Seconds before start of the Session"), "s", 0);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamEnum(NULL, $coll, "IsOpen", _("Is Open"), _("Either Session can be joined"));
                new \Parameter\EnumItem($p, 0, _("No Join"));
                new \Parameter\EnumItem($p, 1, _("Free Join"));
                $p->setValue(1);


                // ------------------------------------------------------------
                //                    Realism Settings
                // ------------------------------------------------------------

                $coll_group = new \Parameter\Collection(NULL, $this->ParameterCollection, "Realism", _("Realism"), _("Realism Settings"));

                // driving aids
                $coll = new \Parameter\Collection(NULL, $coll_group, "DrivingAids", _("Driving Aids"), _("Allowance of systems that support drivability"));
                $p = new \Parameter\ParamInt(NULL, $coll, "TyresOut", _("Allowed Tyres Out"), _("Amount of tyres that are allowed to cross the driving line"), "", 2);
                $p->setMin(0);
                $p->setMax(4);
                $p = new \Parameter\ParamEnum(NULL, $coll, "Abs", _("ABS"), _("Anti-Lock Braking System"));
                new \Parameter\EnumItem($p, 0, _("Disabled"));
                new \Parameter\EnumItem($p, 1, _("Car Dependent"));
                new \Parameter\EnumItem($p, 2, _("Enabled"));
                $p->setValue(1);
                $p = new \Parameter\ParamEnum(NULL, $coll, "Tc", _("TC"), _("Traction Control"));
                new \Parameter\EnumItem($p, 0, _("Disabled"));
                new \Parameter\EnumItem($p, 1, _("Car Dependent"));
                new \Parameter\EnumItem($p, 2, _("Enabled"));
                $p->setValue(1);
                $p = new \Parameter\ParamEnum(NULL, $coll, "Esp", _("ESC"), _("Electronic Stability Control"));
                new \Parameter\EnumItem($p, 0, _("Disabled"));
                new \Parameter\EnumItem($p, 1, _("Allow"));
                $p->setValue(1);
                $p = new \Parameter\ParamEnum(NULL, $coll, "Clutch", _("Automatic Clutch"), _("Automatic Clutch Assist"));
                new \Parameter\EnumItem($p, 0, _("Disabled"));
                new \Parameter\EnumItem($p, 1, _("Allow"));
                $p->setValue(1);
                $p = new \Parameter\ParamEnum(NULL, $coll, "TyreBlankets", _("Tyre Blankets"), _("At the start of the session or after the pitstop the tyre will have the the optimal temperature"));
                new \Parameter\EnumItem($p, 0, _("No"));
                new \Parameter\EnumItem($p, 1, _("Yes"));
                $p->setValue(0);
                $p = new \Parameter\ParamEnum(NULL, $coll, "Mirror", _("Virtual Mirror"), _("With this setting the virtual mirror can be forced for every driver."));
                new \Parameter\EnumItem($p, 0, _("Optional"));
                new \Parameter\EnumItem($p, 1, _("Forced"));
                $p->setValue(0);

                // physics
                $coll = new \Parameter\Collection(NULL, $coll_group, "Physics", _("Physics"), _("Adjustment constants of physics"));
                $p = new \Parameter\ParamInt(NULL, $coll, "FuelRate", _("Fuel Rate"), _("Fuel usage from 0 (no fuel usage) to XXX (100 is the realistic one)"), "&percnt;", 100);
                $p->setMin(0);
                $p->setMax(1000);
                $p = new \Parameter\ParamInt(NULL, $coll, "DamageMultiplier", _("Damage Multiplier"), _("Damage from 0 (no damage) to 100 (full damage)"), "&percnt;", 100);
                $p->setMin(0);
                $p->setMax(100);
                $p = new \Parameter\ParamInt(NULL, $coll, "TyreWearRate", _("Tyre Wear Rate"), _("Tyre wear from 0 (no tyre wear) to XXX (100 is the realistic one)"), "&percnt;", 100);
                $p->setMin(0);
                $p->setMax(999);

                // dynamic track
                $coll = new \Parameter\Collection(NULL, $coll_group, "DynamicTrack", _("Dynamic Track"), _("Dynamic Grip Level of the Track"));
                $p = new \Parameter\ParamInt(NULL, $coll, "SessionStart", _("Start Grip"), _("Amount of Grip at session start"), "&percnt;", 96);
                $p->setMin(0);
                $p->setMax(100);
                $p = new \Parameter\ParamInt(NULL, $coll, "Randomness", _("Randomness"), _("Level of randomness added to the start grip"), "&percnt;", 2);
                $p->setMin(0);
                $p->setMax(100);
                $p = new \Parameter\ParamInt(NULL, $coll, "LapGain", _("Lap Gain"), _("How many laps are needed to increase the grip by 1%"), "Laps", 20);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamInt(NULL, $coll, "SessionTransfer", _("Session Transfer"), _("How much of the gained grip is to be added to the next session 100 -> all the gained grip. Example: difference between starting (90) and ending (96) grip in the session = 6%, with session_transfer = 50 then the next session is going to start with 93."), "&percnt;", 50);
                $p->setMin(0);
                $p->setMax(100);


//                 // ------------------------------------------------------------
//                 //                    Weather
//                 // ------------------------------------------------------------
//
//                 $coll_group = new \Parameter\Collection(NULL, $this->ParameterCollection, "Weather", _("Weather"), _("Weather Settings"));
//
//                 // weather count
//                 $weather_amount = 3;
//                 $p = new \Parameter\ParamInt(NULL, $coll_group, "WeatherAmount", _("Weather Amount"), _("Defines the number of the last weather setting which is announced to the server"), "", $weather_amount);
//                 $p->setMin(1);
//                 $p->setMax($weather_amount);
//
//                 for ($i=1; $i <= $weather_amount; ++$i) {
//                     $coll = new \Parameter\Collection(NULL, $coll_group, "Weather$i", _("Weather") . " $i", _("Weather settings"));
//                     $p = new \Parameter\ParamInt(NULL, $coll, "AmbientBase", _("Ambient Temperature"), _("temperature of the Ambient"), "&deg;C", 25);
//                     $p->setMin(-50);
//                     $p->setMax(100);
//                     $p = new \Parameter\ParamInt(NULL, $coll, "AmbientVar", _("Ambient Variantion"), _("variation of the ambient's temperature. In this example final ambient's temperature can be 16 or 20"), "&deg;C", 3);
//                     $p->setMin(0);
//                     $p->setMax(100);
//                     $p = new \Parameter\ParamInt(NULL, $coll, "RoadRel", _("Relative Road"), _("Relative road temperature: this value will be added to the final ambient temp. In this example the road temperature will be between 22 (16 + 6) and 26 (20 + 6). It can be negative."), "&deg;C", 10);
//                     $p->setMin(-50);
//                     $p->setMax(100);
//                     $p = new \Parameter\ParamInt(NULL, $coll, "RoadVar", _("Road Variantion"), _("variation of the road's temperature. Like the ambient one"), "&deg;C", 5);
//                     $p->setMin(0);
//                     $p->setMax(100);
//                     $p = new \Parameter\ParamInt(NULL, $coll, "WindMin", _("Wind Min"), _("Min speed of the session possible"), "", 0);
//                     $p->setMin(0);
//                     $p->setMax(40);
//                     $p = new \Parameter\ParamInt(NULL, $coll, "WindMax", _("Wind Max"), _("Max speed of session possible (max 40)"), "", 5);
//                     $p->setMin(0);
//                     $p->setMax(40);
//                     $p = new \Parameter\ParamInt(NULL, $coll, "WindDirBase", _("Wind Direction"), _("Base direction of the wind (wind is pointing at); 0 = North, 90 = East etc"), "&deg;", 0);
//                     $p->setMin(0);
//                     $p->setMax(360);
//                     $p = new \Parameter\ParamInt(NULL, $coll, "WindDirVar", _("Wind Variation"), _("Variation (+ or -) of the base direction"), "&deg;", 180);
//                     $p->setMin(0);
//                     $p->setMax(180);
//                 }


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
        if ($this->id() === 0) return NULL;
        else return ServerPreset::fromId($this->loadColumn("Parent"));
    }


    //! Store settings to database
    public function save() {
        if ($this->id() == 0) return;

        $data_array = $this->parameterCollection()->dataArrayExport();
        $data_json = json_encode($data_array);
        $this->storeColumns(['ParameterData'=>$data_json]);
    }
}
