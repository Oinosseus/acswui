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


    //! @return The BopMap object for this event
    public function bopMap() : \Core\BopMap {
        $bopm = new \Core\BopMap();

        // assign foreign drivers
        $bopm->update($this->getParamValue("BopNonRnkBallast"),
                      $this->getParamValue("BopNonRnkRestrictor").
                      NULL);

        // retrieve from user ranking
        foreach (\DbEntry\DriverRanking::listLatest() as $drvrnk) {
            $group = $drvrnk->user()->rankingGroup();
            $ballast = $this->getParamValue("BopDrvRnkGrpBallast$group");
            $restrictor = $this->getParamValue("BopDrvRnkGrpRestrictor$group");
            $bopm->update($ballast, $restrictor, $drvrnk->user());
        }

        // retrieve from explicit settings in registration
        foreach (SessionScheduleRegistration::listRegistrations($this, FALSE) as $reg) {

            // check if any BOP is assigned
            if ($reg->ballast() > 0 || $reg->restrictor() > 0) {

                if ($reg->user()) {
                    $bopm->update($reg->ballast(), $reg->restrictor(), $reg->user());
                }

                if ($reg->teamCar()) {
                    $bopm->update($reg->ballast(), $reg->restrictor(), $reg->teamCar());
                }
            }
        }

        // retrieve from CarClass
        $cc = $this->carClass();
        foreach ($cc->cars() as $c) {
            $b = $cc->ballast($c);
            $r = $cc->restrictor($c);
            $bopm->update($b, $r, $c);
        }

        return $bopm;
    }


    //! @return The assigned CarClass object
    public function carClass() {
        return CarClass::fromId($this->getParamValue("CarClass"));
    }


    //! @return TRUE if a CarSkin is actively used in the registration
    public function carSkinOccupied(CarSkin $car_skin) {
        if ($this->ActiveRegisteredCarSkinIds === NULL) {
            $this->ActiveRegisteredCarSkinIds = array();

            // find direct registrations
            $query = "SELECT CarSkin FROM SessionScheduleRegistrations WHERE SessionSchedule = {$this->id()} AND Active != 0";
            foreach (\Core\Database::fetchRaw($query) as $row)
                $this->ActiveRegisteredCarSkinIds[] = (int) $row['CarSkin'];

            // find teamcar registrations
            $query  = "SELECT TeamCars.CarSkin";
            $query .= " FROM SessionScheduleRegistrations";
            $query .= " INNER JOIN TeamCars ON TeamCars.Id=SessionScheduleRegistrations.TeamCar";
            $query .= " WHERE SessionScheduleRegistrations.SessionSchedule={$this->id()}";
            $query .= " AND SessionScheduleRegistrations.Active!=0";
            $query .= " AND SessionScheduleRegistrations.TeamCar!=0;";
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
    public function entryList() : \Core\EntryList {

        // create EntryList
        $el = new \Core\EntryList();


        // --------------------------------------------------------------------
        //  Create From Other Session
        // --------------------------------------------------------------------

        //! @todo Implement retrieving other sessions when TeamCar is visible in session results
        // $other_session = \DbEntry\Session::fromId($this->getParamValue("SessionEntryList"));
        // if ($other_session) {
        //
        //     // fill entries from other result
        //     foreach ($session->results() as $rslt) {
        //         // if ($el->count() >= $this->track()->pitboxes()) break;
        //
        //         if (array_key_exists($rslt->user()->id(), $registered_user_ids)) {
        //             $reg = $registered_user_ids[$rslt->user()->id()];
        //             $eli = new \Core\EntryListItem($reg->carSkin(), $reg->user());//, $ballast, $restrictor);
        //             $el->add($eli);
        //         }
        //
        //     }
        // }


        // --------------------------------------------------------------------
        //  Create From Random
        // --------------------------------------------------------------------

        // if (!$other_session) {
            // fill with registrations
            foreach (SessionScheduleRegistration::listRegistrations($this) as $ssr) {

                // team-car registrations
                if ($ssr->teamCar()) {
                    $eli = new \Core\EntryListItem($ssr->teamCar()->carSkin());
                    $eli->addDriver($ssr->teamCar());
                    $el->add($eli);

                // single driver registration
                } else if ($ssr->user()) {
                    $eli = new \Core\EntryListItem($ssr->carSkin());
                    $eli->addDriver($ssr->user());
                    $el->add($eli);
                }
            }

            // shuffle registered entries
            $el->shuffle();

            // add TVCar
            $el->addTvCar();

            // fill free slots
            $max_count = $this->track()->pitboxes();
            $el->fillSKins($this->carClass(), $max_count);

            // reverse (to have free skins on top
            // AC seems to assign new drivers from top of Entry list
            $el->reverse();
        // }


        // --------------------------------------------------------------------
        //  Done
        // --------------------------------------------------------------------

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
        if ($start_after === NULL) $start_after = new \DateTime("now");
        $start_str = \Core\Database::timestamp($start_after);

        $ret = array();
        $query = "SELECT Id, Start FROM SessionSchedule WHERE Start >= '$start_str' ORDER BY Start ASC";
        foreach (\Core\Database::fetchRaw($query) as $row) {
            $ret[] = SessionSchedule::fromId($row['Id']);
        }
        return $ret;
    }


    //! @return The name of this schedule item
    public function name() {
        return $this->getParamValue("Name");
    }


    //! @return TRUE when SessionSchedule->start() is before current time
    public function obsolete() {
        return $this->start() < new \DateTime("now");
    }


    //! @return The Collection object, that stores all parameters
    public function parameterCollection() {

        // derive own parameter collection
        if ($this->ParameterCollection === NULL) {
            $this->ParameterCollection = new \Parameter\Collection(\Core\ACswui::parameterCollection()->child("SessionSchedule"), NULL);
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
        $t_start = new \DateTime($this->getParamValue("EventStart"), new \DateTimeZone("UTC"));
        $column_data['Start'] = \Core\Database::timestamp($t_start);
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
        $t_str = \Core\Database::timestamp($t);
        $this->storeColumns(["Executed"=>$t_str]);
    }


    //! @return The DateTime of the event start (server timezone)
    public function start() {
        $val = $this->getParamValue("EventStart");
        $dt = new \DateTime($val, new \DateTimeZone("UTC"));
        return $dt;
    }


    //! @return The assigned Track object
    public function track() {
        return Track::fromId($this->getParamValue("Track"));
    }
}
