<?php

namespace DbEntry;

/**
 * Cached wrapper to car databse Tracks table element
 */
class SessionSchedule extends DbEntry {

    private $ParameterCollection = NULL;
    private $ActiveRegisteredCarSkinIds = NULL;
    private $SessionLast = NULL;


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


    //! Will delete this item from the database
    public function delete() {

        $query = "DELETE FROM SessionScheduleRegistrations WHERE SessionSchedule = {$this->id()}";
        \Core\Database::query($query);

        $this->deleteFromDb();
    }


    //! @return A generated EntryList object
    public function entryList() {
        $el = new \Core\EntryList();

        $map_ballast = $this->mapBallasts();
        $map_restrictors = $this->mapRestrictors();

        // $user_registrations[User->id()] = SessionScheduleRegistration
        $user_registrations = array();
        foreach (SessionScheduleRegistration::listRegistrations($this) as $reg) {
            $user_registrations[$reg->user()->id()] = $reg;
        }

        // add users by other SessionResult
        $other_session = \DbEntry\Session::fromId($this->getParamValue("SessionEntryList"));
        if ($other_session) {
            foreach ($session->results() as $rslt) {
                if ($el->count() >= $this->track()->pitboxes()) break;

                if (array_key_exists($rslt->user()->id(), $registered_user_ids)) {
                    $reg = $registered_user_ids[$rslt->user()->id()];
                    $eli = new \Core\EntryListItem($reg->carSkin(), $reg->user());//, $ballast, $restrictor);
                    $el->add($eli);
                }

            }
        }

        // add users by registration
        foreach (SessionScheduleRegistration::listRegistrations($this) as $reg) {
            if ($el->count() >= $this->track()->pitboxes()) break;

            if (!$el->containsUser($reg->user())) {
                $ballast = (array_key_exists($reg->user()->steam64GUID(), $map_ballast)) ? $map_ballast[$reg->user()->steam64GUID()] : $map_ballast["OTHER"];
                $restrictor = (array_key_exists($reg->user()->steam64GUID(), $map_restrictors)) ? $map_restrictors[$reg->user()->steam64GUID()] : $map_restrictors["OTHER"];
                $eli = new \Core\EntryListItem($reg->carSkin(), $reg->user(), $ballast, $restrictor);
                $el->add($eli);
            }
        }


        // fill random entries
        $el->fillSkins($this->carClass(), $this->track(), $map_ballast["OTHER"], $map_restrictors["OTHER"]);

        // apply ballast/restrictor accodring to CarClass
        $el->applyCarClass($this->carClass());

        // reverse to have unoccupied cars on top
        if (!$other_session) $el->reverse();

        return $el;
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
     * @return A html string with the event name and a link
     */
    public function htmlName() {
        $html = "";
        $html .= "<a href=\"index.php?HtmlContent=SessionSchedules&SessionSchedule={$this->id()}&Action=ShowRoster\">";
        $html .= $this->name();
        $html .= "</a>";
        return $html;
    }


    /**
     * Lists all SessionSchedule objects
     * @param $start_after If NULL, only items with start in future are listed, else all items from with a start date later than this are listed
     * @return An array of SessionSchedule objects, ordered by event start
     */
    public static function listSchedules(\DateTime $start_after=NULL) {
        if ($start_after === NULL) $start_after = \Core\Core::now();
        $start_str = \Core\Database::dateTime2timestamp($start_after);

        $ret = array();
        $query = "SELECT Id, Start FROM SessionSchedule WHERE Start >= '$start_str' ORDER BY Start ASC";
        foreach (\Core\Database::fetchRaw($query) as $row) {
            $ret[] = SessionSchedule::fromId($row['Id']);
        }
        return $ret;
    }


    //! @return An accociative array with Steam64GUI->Ballast and one item 'OTHER'->Ballast
    public function mapBallasts() {
        $map = array();
        $map['OTHER'] = $this->getParamValue("BopNonRnkBallast");

        // retrieve from user ranking
        foreach (\DbEntry\DriverRanking::listLatest() as $drvrnk) {
            $group = $drvrnk->rankingGroup();
            $ballast = $this->getParamValue("BopDrvRnkGrpBallast$group");
            $map[$drvrnk->user()->steam64GUID()] = $ballast;
        }

        // retrieve from explicit definition
        foreach (SessionScheduleRegistration::listRegistrations($this, FALSE) as $reg) {
            // ensure to have assigned penalty (if any assigned)
            if ($reg->ballast() > 0 || $reg->restrictor() > 0) {
                $map[$reg->user()->steam64GUID()] = $reg->ballast();
            }
        }


        return $map;
    }


    //! @return An accociative array with Steam64GUI->Restrictor and one item 'OTHER'->Restrictor
    public function mapRestrictors() {
        $map = array();
        $map['OTHER'] = $this->getParamValue("BopNonRnkRestrictor");

        // retrieve from user ranking
        foreach (\DbEntry\DriverRanking::listLatest() as $drvrnk) {
            $group = $drvrnk->rankingGroup();
            $restrictor = $this->getParamValue("BopDrvRnkGrpRestrictor$group");
            $map[$drvrnk->user()->steam64GUID()] = $restrictor;
        }

        // retrieve from explicit definition
        foreach (SessionScheduleRegistration::listRegistrations($this, FALSE) as $reg) {
            // ensure to have assigned penalty (if any assigned)
            if ($reg->ballast() > 0 || $reg->restrictor() > 0) {
                $map[$reg->user()->steam64GUID()] = $reg->restrictor();
            }
        }

        return $map;
    }


    //! @return The name of this schedule item
    public function name() {
        return $this->getParamValue("Name");
    }


    //! @return TRUE when SessionSchedule->start() is before current time
    public function obsolete() {
        return $this->start() < \Core\Core::now();
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
     * List all registrations
     * @param $only_active If TRUE (default) only active registrations are returned
     * @return A list of SessionScheduleRegistration objects
     */
    public function registrations(bool $only_active=TRUE) {
        return SessionScheduleRegistration::listRegistrations($this, $only_active);
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
        $column_data['Slot'] = $this->getParamValue("ServerSlot");

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


    //! @return The ServerSlot object
    public function serverSlot() {
        return \Core\ServerSlot::fromId($this->getParamValue("ServerSlot"));
    }


    //! @return The used ServerPreset object
    public function serverPreset() {
        return ServerPreset::fromId($this->getParamValue("ServerPreset"));
    }


    //! @return The last Session object that refers to the event (can be NULL)
    public function sessionLast() {
        if ($this->SessionLast === NULL) {
            $query = "SELECT Id FROM Sessions WHERE SessionSchedule = {$this->id()} ORDER BY Id DESC LIMIT 1;";
            $res = \Core\Database::fetchRaw($query);
            if (count($res) > 0) {
                $this->SessionLast = \DbEntry\Session::fromId($res[0]['Id']);
            }
        }

        return $this->SessionLast;
    }


    /**
     * Set the SessionSchedule as executed
     * @param $t DateTime object of when the item has been executed
     */
    public function setExecuted(\DateTime $t) {
        $t_str = \Core\Database::dateTime2timestamp($t);
        $this->storeColumns(["Executed"=>$t_str]);
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
