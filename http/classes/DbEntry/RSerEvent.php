<?php

declare(strict_types=1);
namespace DbEntry;

//! Wrapper for database table element
class RSerEvent extends DbEntry {

    private $Order = NULL;


    /**
     * Construct a new object
     * @param $id Database table id
     */
    protected function __construct(int $id) {
        parent::__construct("RSerEvents", $id);
    }


    //! @return The BOP Map that is applicable for this event
    public function bopMap() : \Core\BopMap {
        //! @todo TBD Respect BOP from standings

        $bm = new \Core\BopMap();

        foreach ($this->season()->series()->listClasses() as $rser_class) {
            $car_class = $rser_class->carClass();

            // RSerClass offset
            $bm->update($rser_class->getParam("BopBallastOffset"),
                        $rser_class->getParam("BopRestrictorOffset"),
                        $rser_class);

            if ($car_class) {
                // CarClass BOP
                foreach ($car_class->cars() as $car) {
                    $ballast = $car_class->ballast($car);
                    $restrictor = $car_class->restrictor($car);
                    $bm->update($ballast, $restrictor, $car);
                }
            }
        }

        return $bm;
    }


    /**
     * Creates a new season for a series.
     *
     * @param $rser_season The race series season which this event shall be added to
     * @return The new created event object
     */
    public static function createNew(RSerSeason $rser_season) : RSerEvent {

        // store into db
        $columns = array();
        $columns['Season'] = $rser_season->id();
        $id = \Core\Database::insert("RSerEvents", $columns);
        $rser_e = RSerEvent::fromId($id);

        // create a first split
        $rser_sp = RSerSplit::createNew($rser_e);

        return $rser_e;
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) : ?RSerEvent {
        return parent::getCachedObject("RSerEvents", "RSerEvent", $id);
    }


    /**
     * List all available events
     * @param $rser_season All events of this season are returned
     * @return A list of RSerEvent objects
     */
    public static function listEvents(RSerSeason $rser_season) : array {

        // prepare query
        $query = "SELECT Id FROM RSerEvents WHERE Season={$rser_season->id()} ORDER BY Id ASC";

        // list results
        $list = array();
        foreach (\Core\Database::fetchRaw($query) as $row) {
            $id = (int) $row['Id'];
            $list[] = RSerEvent::fromId($id);
        }

        return $list;
    }


    //! @return A list of RSerSplit objects
    public function listSplits() : array {
        return RSerSplit::listSplits($this);
    }


    /**
     * List all qualifications
     * @param $class The RSerClass
     * @return A list of RSerQualification objects
     */
    public function listQualifications(RSerClass $class) : array {
        return RSerQualification::listQualifications($this, $class);
    }

    //! @return The order/number of this event
    public function order() : int {
        if ($this->Order === NULL) {
            $query = "SELECT Id FROM RSerEvents WHERE Season={$this->season()->id()} ORDER BY Id ASC";
            $this->Order = 1;
            foreach (\Core\Database::fetchRaw($query) as $row) {
                if ($row['Id'] == $this->id()) break;
                $this->Order += 1;
            }
        }

        return $this->Order;
    }


    //! @return The associated season of this event
    public function season() : RSerSeason {
        return RSerSeason::fromId((int) $this->loadColumn('Season'));
    }


    /**
     * Change the track of the event.
     *
     * @warning This will delete all current qualifications of the event!
     *
     * If results are already present, changing the track will be ignored.
     * If the new track is equal to the current track, nothing happens.
     *
     * @param $track The new track for the season
     */
    public function setTrack(Track $track) {
        if ($track == $this->track()) return;

        // check for results
        $query = "SELECT Id FROM RSerResults WHERE Event={$this->id()} LIMIT 1;";
        if (count(\Core\Database::fetchRaw($query))) {
            \Core\Log::debug("Ignore changing track of {$this} because results do already exist.");
            return;
        }

        // delete qualifications
        $query = "DELETE FROM RSerQualifications WHERE Event={$this->id()}";
        \Core\Database::query($query);

        // update track
        $this->storeColumns(["Track"=>$track->id()]);
    }


    //! @return The Track of this event
    public function track() : Track {
        $t = Track::fromId((int) $this->loadColumn("Track"));
        if ($t === NULL) {
            $t = Track::listTracks()[0];
        }
        return $t;
    }
}
