<?php

namespace DbEntry;

/**
 * Cached wrapper to car databse Tracks table element
 */
class SessionSchedule extends DbEntry {

    private $ParameterCollection = NULL;
    private $ActiveRegisteredCarSkinIds = NULL;


    /**
     * @param $id Database table id
     */
    public function __construct($id) {
        parent::__construct("SessionSchedule", $id);
    }


    //! @return The assigned CarClass object
    public function carClass() {
        return CarClass::fromId($this->getParamValue("CarClass"));
    }


    //! Will delete this item from the database
    public function delete() {

        $query = "DELETE FROM SessionScheduleRegistrations WHERE SessionSchedule = {$this->id()}";
        \Core\Database::query($query);

        $this->deleteFromDb();
    }


    //! @return TRUE if a CarSkin is actively used in the registration
    public function carSkinOccupied(CarSkin $car_skin) {
        if ($this->ActiveRegisteredCarSkinIds === NULL) {
            $this->ActiveRegisteredCarSkinIds = array();
            $query = "SELECT CarSkin FROM SessionScheduleRegistrations WHERE SessionSchedule = {$this->id()} AND Active != 0";
            foreach (\Core\Database::fetchRaw($query) as $row)
                $this->ActiveRegisteredCarSkinIds[] = (int) $row['CarSkin'];
        }

        return in_array($car_skin->id(), $this->ActiveRegisteredCarSkinIds);
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("SessionSchedule", "SessionSchedule", $id);
    }


    //! @return The same as parameterCollection()->child($key)->value()
    public function getParamValue(string $key) {
        return $this->parameterCollection()->child($key)->value();
    }


    /**
     * Lists all SessionSchedule objects
     * @param $skip_executed If TRUE (default), then already executed sessions are skipped
     * @return An array of SessionSchedule objects, ordered by event start
     */
    public static function listSchedules(bool $skip_executed=TRUE) {
        $ret = array();
        $now = (new \DateTime("now", new \DateTimeZone(\Core\Config::LocalTimeZone)))->format("Y:m:d H:i:s");
        $query = "SELECT Id, Start FROM SessionSchedule WHERE Executed < '$now' ORDER BY Start ASC";
        foreach (\Core\Database::fetchRaw($query) as $row) {
            $ret[] = SessionSchedule::fromId($row['Id']);
        }
        return $ret;
    }


    //! @return The name of this schedule item
    public function name() {
        return $this->getParamValue("Name");
    }


    //! @return The Collection object, that stores all parameters
    public function parameterCollection() {

        // derive own parameter collection
        if ($this->ParameterCollection === NULL) {
            $this->ParameterCollection = new \Parameter\Collection(\Core\SessionScheduleDefault::parameterCollection(), NULL);
            $data_json = $this->loadColumn('ParameterData');
            $data_array = json_decode($data_json, TRUE);
            if ($data_array !== NULL) $this->ParameterCollection->dataArrayImport($data_array);
        }

        return $this->ParameterCollection;
    }


    /**
     * Save the values from the parameter collection.
     * Be aware to call SessionSchedule::parameterCollection()->storeHttpRequest() before when saving data from HTML formular
     */
    public function saveParameterCollection() {
        $column_data = array();

        // basic values
        $column_data['Start'] = $this->getParamValue("EventStart");
        $column_data['CarClass'] = $this->getParamValue("CarClass");
        $column_data['Track'] = $this->getParamValue("Track");
        $column_data['ServerPreset'] = $this->getParamValue("ServerPreset");

        // deactivate registrations when car class changes
        if ($this->getParamValue("CarClass") != $this->loadColumn("CarClass")) {
            foreach (SessionScheduleRegistration::listRegistrations($this, FALSE) as $sr) {
                SessionScheduleRegistration::register($this, $sr->user());
            }
        }

        // parameter data
        $data_array = $this->parameterCollection()->dataArrayExport();
        $data_json = json_encode($data_array);
        $column_data['ParameterData'] = $data_json;

        $this->storeColumns($column_data);
    }


    //! @return The DateTime of the event start (server timezone)
    public function start() {
        $val = $this->getParamValue("EventStart");
        $dt = new \DateTime($val, new \DateTimeZone(\Core\Config::LocalTimeZone));
        return $dt;
    }


    //! @return The assigned Track object
    public function track() {
        return Track::fromId($this->getParamValue("Track"));
    }
}
