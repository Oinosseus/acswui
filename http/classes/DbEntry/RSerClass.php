<?php

declare(strict_types=1);
namespace DbEntry;

//! Wrapper for database table element
class RSerClass extends DbEntry {

    private $ParameterCollection = NULL;


    /**
     * Construct a new object
     * @param $id Database table id
     */
    protected function __construct(int $id) {
        parent::__construct("RSerClasses", $id);
    }


    /**
     * @param $position The season position
     * @return the ballast for a certain season position
     */
    public function bopBallast(int $position) {
        $bop = 0;
        $bop += $this->getParam("BopBallastOffset");

        $pos_relative = $this->getParam("BopBallastPosition") - $position + 1;
        if ($pos_relative > 0) $bop += $pos_relative * $this->getParam("BopBallastGain");

        return $bop;
    }


    /**
     * @param $position The season position
     * @return the restrictor for a certain season position
     */
    public function bopRestrictor(int $position) {
        $bop = 0;
        $bop += $this->getParam("BopRestrictorOffset");

        $pos_relative = $this->getParam("BopRestrictorPosition") - $position + 1;
        if ($pos_relative > 0) $bop += $pos_relative * $this->getParam("BopRestrictorGain");

        return $bop;
    }


    //! @return The assigned CarClass object
    public function carClass() : ?CarClass {
        return CarClass::fromId((int) $this->loadColumn("CarClass"));
    }


    /**
     * Creates a new class for a series.
     * If the CarClass already exists in the database, it will be reactivated and returned.
     *
     * @param $rser_series The race series which this class shall be added to
     * @param $car_class The related CarClass
     * @return The new created Team object
     */
    public static function createNew(RSerSeries $rser_series,
                                     CarClass $car_class) : RSerClass {

        $id = NULL;  // invalid int to force exception on error

        // check for existing deactive class
        $query = "SELECT Id FROM RSerClasses WHERE Active=0 AND Series={$rser_series->id()} AND CarClass={$car_class->id()}";
        $res = \Core\Database::fetchRaw($query);
        if (count($res) > 0) {
            $id = (int) $res[0]['Id'];
            \Core\Database::query("UPDATE RSerClasses SET Active=1 WHERE Id=$id");  // re-activate

        // create new class
        } else {

            // get default serialized prameter collection
            $pc = new \ParameterCollections\RSerClass();
            $pc->child("Name")->setValue($car_class->name());

            // store into db
            $columns = array();
            $columns['Name'] = $car_class->name();
            $columns['CarClass'] = $car_class->id();
            $columns['Series'] = $rser_series->id();
            $columns['Active'] = 1;
            $columns['ParamColl'] = json_encode($pc->dataArrayExport());
            $id = \Core\Database::insert("RSerClasses", $columns);
        }

        return RSerClass::fromId($id);
    }


    //! deactivates this class (can be re-activated with createNew()
    public function deactivate() {
        $this->storeColumns(["Active"=>0]);
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) : ?RSerClass {
        return parent::getCachedObject("RSerClasses", "RSerClass", $id);
    }


    /**
     * Access parameters statically
     * @return The curent value of a certain parameter
     */
    public function getParam(string $parameter_key) {
        return $this->parameterCollection()->child($parameter_key)->value();
    }


    /**
     * List all available classes
     * @param $rser_series If not NULL, only classes of this series are returned
     * @param $active_only If TRUE (default) only active classes are returned
     * @return A list of RSerClass objects
     */
    public static function listClasses(RSerSeries $rser_series=NULL,
                                bool $active_only=TRUE) : array {

        // prepare query
        $query = "SELECT Id FROM RSerClasses WHERE Series={$rser_series->id()}";
        if ($active_only) $query .= " AND Active!=0";
        $query .= " ORDER BY Id ASC";

        // list results
        $list = array();
        foreach (\Core\Database::fetchRaw($query) as $row) {
            $id = (int) $row['Id'];
            $list[] = RSerClass::fromId($id);
        }

        return $list;
    }


    //! @return The race-series-name of this car class
    public function name() {
        return $this->loadColumn("Name");
    }


    //! @return A \ParameterCollections\RSerClass object
    public function parameterCollection() : \ParameterCollections\RSerClass {
        if ($this->ParameterCollection === NULL) {
            $base_collection = new \ParameterCollections\RSerClass();
            $this->ParameterCollection = new \ParameterCollections\RSerClass($base_collection);

            // load from database
            $data_json = $this->loadColumn("ParamColl");
            $data_array = json_decode($data_json, TRUE);
            if ($data_array !== NULL) $this->ParameterCollection->dataArrayImport($data_array);
        }

        return $this->ParameterCollection;
    }


    //! Save the current parameter collection into the database
    public function save() {
        if ($this->id() == 0) return;

        // serialize parameter collection
        $data_array = $this->parameterCollection()->dataArrayExport();
        $data_json = json_encode($data_array);

        // update db
        $column_data = array();
        $column_data['ParamColl'] = $data_json;
        $column_data['Name'] = $this->parameterCollection()->child("Name")->value();
        $this->storeColumns($column_data);
    }
}
