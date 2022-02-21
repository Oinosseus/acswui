<?php

namespace DbEntry;

class Weather extends DbEntry {

    private $ChildWeathers = NULL;
    private $ParameterCollection = NULL;


    /**
     * Construct a new object
     * @param $id Database table id
     */
    protected function __construct(int $id) {
        parent::__construct("Weathers", $id);
    }


    //! @return An array of Weather objects that are children of this
    public function children() {
        if ($this->ChildWeathers === NULL) {
            $this->ChildWeathers = array();

            $res = \Core\Database::fetch("Weathers", ['Id'], ['Parent'=>$this->id()], 'Name');
            foreach ($res as $row) {
                $this->ChildWeathers[] = Weather::fromId($row['Id']);
            }
        }
        return $this->ChildWeathers;
    }


    //! Delete this weather from the database
    public function delete() {

        // remove children
        foreach ($this->children() as $child) {
            $child->delete();
        }

        // remove from parents child list
        $parent = $this->parent();
        if ($parent !== NULL && $parent->ChildWeathers !== NULL) {
            $new_parent_child_list = array();
            foreach ($parent->ChildWeathers as $child) {
                if ($child->id() !== $this->id()) $new_parent_child_list[] = $child;
            }
            $parent->ChildWeathers = $new_parent_child_list;
        }

        // delete from database
        $this->deleteFromDb();
    }


    /**
     * Create new weather, which is derived from a parent.
     * @param $parent The parenting Weather which shall be derived
     * @return A new Weather object
     */
    public static function derive(Weather $parent) {
        $new_id = \Core\Database::insert("Weathers", ['Name'=>"New Preset", 'Parent'=>$parent->id()]);
        $new_preset = Weather::fromId($new_id);
        return $new_preset;
    }


    //! @return A Weather object, retreived from database by ID ($id=0 will return a non editable default weather)
    public static function fromId(int $id) {

        $sp = NULL;

        // create default server preset when ID=0 is requested
        if ($id == 0) {

            $sp = new Weather(0);

        // if $id is not 0, get from database
        } else {
            $sp = parent::getCachedObject("Weathers", "Weather", $id);
        }

        return $sp;
    }


    /**
     * Retrieve all current existing weathers as an array
     * The returned list is sorted by name
     * @return An array of Weather objects
     */
    public static function listPresets() {
        $weathers = array();

        foreach (\Core\Database::fetch("Weathers", ['Id'], [], 'Name') as $row) {
            $p = Weather::fromId($row['Id']);
            $presets[] = $p;
        }

        return $weathers;
    }


    /**
     * List all available weathers
     * @param $final_dervied_only If TRUE (default) then only the final weathers will be listed (not further derived weathers)
     * @return A list of Weather objects
     */
    public static function listWeathers(bool $final_dervied_only = TRUE) {
        $weathers = array();

        foreach (\Core\Database::fetch("Weathers", ['Id'], [], "Name") as $row) {
            $weather = Weather::fromId($row['Id']);

            if (count($weather->children()) == 0 || $final_dervied_only == FALSE) {
                $weathers[] = $weather;
            }
        }

        return $weathers;
    }


    //! @return The name of the weather
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
                $this->ParameterCollection = new \Parameter\Collection(NULL, NULL, "Weather", _("Weather"), _("Collection of weather settings"));

                $p = new \Parameter\ParamString(NULL, $this->ParameterCollection, "Name", _("Name"), _("Arbitrary name for this weather"), "", "");
                $p = new \Parameter\ParamSpecialWeatherGraphic(NULL, $this->ParameterCollection, "Graphic", _("Graphic"), _("it's exactly one of the folder name that you find into \"content\weather\" directory"));

                // temperatures
                $coll = new \Parameter\Collection(NULL, $this->ParameterCollection, "Temperatures", _("Temperatures"), _("Environment temperature settings"));
                $p = new \Parameter\ParamInt(NULL, $coll, "AmbientBase", _("Ambient Base"), _("temperature of the Ambient"), "&deg;C", 18);
                $p->setMin(-50);
                $p->setMax(100);
                $p = new \Parameter\ParamInt(NULL, $coll, "AmbientVar", _("Ambient Variation"), _("variation of the ambient's temperature. In this example final ambient's temperature can be 16 or 20"), "K", 2);
                $p->setMin(0);
                $p->setMax(25);
                $p = new \Parameter\ParamInt(NULL, $coll, "RoadBase", _("Road Relative"), _("Relative road temperature: this value will be added to the final ambient temp. In this example the road temperature will be between 22 (16 + 6) and 26 (20 + 6). It can be negative."), "K", 6);
                $p->setMin(0);
                $p->setMax(25);
                $p = new \Parameter\ParamInt(NULL, $coll, "RoadVar", _("Road Variation"), _("variation of the road's temperature. Like the ambient one"), "K", 1);
                $p->setMin(0);
                $p->setMax(25);

                // wind
                $coll = new \Parameter\Collection(NULL, $this->ParameterCollection, "Wind", _("Wind"), _("Environment wind settings"));
                $p = new \Parameter\ParamInt(NULL, $coll, "WindBaseMin", _("Minimum Speed"), _("Min speed of the session possible"), "m/s", 3);
                $p->setMin(0);
                $p->setMax(999);
                $p = new \Parameter\ParamInt(NULL, $coll, "WindBaseMax", _("Maximum Speed"), _("Max speed of session possible (max 40)"), "m/s", 15);
                $p->setMin(0);
                $p->setMax(40);
                $p = new \Parameter\ParamInt(NULL, $coll, "WindDirection", _("Direction"), _("base direction of the wind (wind is pointing at); 0 = North, 90 = East etc"), "&deg;", 30);
                $p->setMin(0);
                $p->setMax(359);
                $p = new \Parameter\ParamInt(NULL, $coll, "WindDirectionVar", _("Direction Variation"), _("variation (+ or -) of the base direction"), "&deg;", 15);
                $p->setMin(0);
                $p->setMax(180);

                // set all deriveable and visible
                $this->ParameterCollection->setAllAccessible();
            }
        }

        return $this->ParameterCollection;
    }


    //! @return The parenting Weather object (can be NULL)
    public function parent() {
        if ($this->id() === NULL) return NULL;
        else if ($this->id() === 0) return NULL;
        else return Weather::fromId($this->loadColumn("Parent"));
    }


    //! Store settings to database
    public function save() {
        if ($this->id() == 0) return;

        $column_data = array();

        // name, slot-id
        $column_data['Name'] = $this->parameterCollection()->child("Name")->valueLabel();

        // parameter data
        $data_array = $this->parameterCollection()->dataArrayExport();
        $data_json = json_encode($data_array);
        $column_data['ParameterData'] = $data_json;

        $this->storeColumns($column_data);
    }
}
