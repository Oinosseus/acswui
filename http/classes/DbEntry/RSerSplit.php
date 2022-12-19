<?php

declare(strict_types=1);
namespace DbEntry;

//! Wrapper for database table element
class RSerSplit extends DbEntry {

    private $Order = NULL;


    /**
     * Construct a new object
     * @param $id Database table id
     */
    protected function __construct(int $id) {
        parent::__construct("RSerSplits", $id);
    }


    /**
     * Creates a new split  for an event.
     *
     * @param $rser_event The race series event which this split shall be added to
     * @return The new created split object
     */
    public static function createNew(RSerEvent $rser_event) : RSerSplit {

        // count existing splits
        $query = "SELECT ServerSlot FROM RSerSplits WHERE Event={$rser_event->id()} ORDER BY Id DESC Limit 1;";
        $res = \Core\Database::fetchRaw($query);
        $last_slot = \Core\Config::ServerSlotAmount;
        if (count($res)) $last_slot = $res[0]['ServerSlot'];
        $next_slot = $last_slot + 1;
        if ($next_slot > \Core\Config::ServerSlotAmount) $next_slot = 1;

        // store into db
        $columns = array();
        $columns['Event'] = $rser_event->id();
        $columns['ServerSlot'] = $next_slot;
        $columns['Start'] = \Core\Database::timestamp((new \DateTime("now"))->add(new \DateInterval("P14D")));
        $id = \Core\Database::insert("RSerSplits", $columns);

        return RSerSplit::fromId($id);
    }


    //! @return A generated EntryList object
    public function entryList() : \Core\EntryList {
        //! @todo TBD Qualifications need to be respected here

        // create EntryList
        $el = new \Core\EntryList();

        // add registrations
        foreach ($this->event()->season()->listRegistrations(NULL, TRUE) as $reg) {
            $skin = $reg->carSkin();
            if ($skin) {
                $eli = new \Core\EntryListItem($skin, $reg);
                $eli->addDriver($reg->user());
                $eli->addDriver($reg->teamCar());
                $el->add($eli);
            }
        }

        return $el;
    }


    //! @return The associated event
    public function event() : RSerEvent {
        return RSerEvent::fromId((int) $this->loadColumn('Event'));
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) : ?RSerSplit {
        return parent::getCachedObject("RSerSplits", "RSerSplit", $id);
    }


    /**
     * List all available splits
     * @param $rser_event All splits of this event are returned
     * @return A list of RSerSplit objects
     */
    public static function listSplits(RSerEvent $rser_event) : array {

        // prepare query
        $query = "SELECT Id FROM RSerSplits WHERE Event={$rser_event->id()} ORDER BY Id ASC";

        // list results
        $list = array();
        foreach (\Core\Database::fetchRaw($query) as $row) {
            $id = (int) $row['Id'];
            $list[] = RSerSplit::fromId($id);
        }

        return $list;
    }


    //! @return The order/number of this split
    public function order() : int {
        if ($this->Order === NULL) {
            $query = "SELECT Id FROM RSerSplits WHERE Event={$this->event()->id()} ORDER BY Id ASC";
            $this->Order = 1;
            foreach (\Core\Database::fetchRaw($query) as $row) {
                if ($row['Id'] == $this->id()) break;
                $this->Order += 1;
            }
        }

        return $this->Order;
    }


    //! @return The assigned ServerSlot
    public function serverSlot() : ?\Core\ServerSlot {
        $id = (int) $this->loadColumn("ServerSlot");
        return \Core\ServerSlot::fromId($id);

    }


    //! @param $server_slot Assign a new server slot where this split shall be run on.
    public function setServerSlot(\Core\ServerSlot $server_slot) {
        $this->storeColumns(["ServerSlot"=>$server_slot->id()]);
    }


    //! @param $start The new time,w hen this split shall be startet
    public function setStart(\DateTime $start) {
        $this->storeColumns(["Start"=>\Core\Database::timestamp($start)]);
    }


    //! @return The tiem, when this split is startet
    public function start() : \DateTime {
        $dt = $this->loadColumn("Start");
        return new \DateTime($dt);
    }
}
