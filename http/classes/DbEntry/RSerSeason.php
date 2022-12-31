<?php

declare(strict_types=1);
namespace DbEntry;

//! Wrapper for database table element
class RSerSeason extends DbEntry {


    private $CacheResultedEventCount = NULL;


    /**
     * Construct a new object
     * @param $id Database table id
     */
    protected function __construct(int $id) {
        parent::__construct("RSerSeasons", $id);
    }


    /**
     * @warning This function is cached and will work wrong if events change during lifetime of this object
     * @return The number of events where results are available
     */
    public function countResultedEvents() : int {
        if ($this->CacheResultedEventCount === NULL) {
            $query = "SELECT DISTINCT(RSerResults.Event) FROM RSerEvents INNER JOIN RSerResults ON RSerResults.Event = RSerEvents.Id WHERE RSerEvents.Season={$this->id()};";
            $res = \Core\Database::fetchRaw($query);
            $this->CacheResultedEventCount = count($res);
        }

        return $this->CacheResultedEventCount;
    }


   /**
     * Creates a new season for a series.
     *
     * @param $rser_series The race series which this season shall be added to
     * @return The new created Team object
     */
    public static function createNew(RSerSeries $rser_series) : RSerSeason {

        // store into db
        $columns = array();
        $columns['Name'] = _("New Season");
        $columns['Series'] = $rser_series->id();
        $id = \Core\Database::insert("RSerSeasons", $columns);

        return RSerSeason::fromId($id);
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) : ?RSerSeason {
        return parent::getCachedObject("RSerSeasons", "RSerSeason", $id);
    }


    /**
     * Get a stading
     * @param $registration The RSerRegistration
     * @return A RSerStanding object
     */
    public function getStanding(RSerRegistration $registration) : ?RSerStanding {
        return RSerStanding::getStanding($this, $registration);
    }


    /**
     * @return TRUE if this season has still events planned
     */
    public function isActive() : bool {
        //! @todo TBD Determine if a season is active
        return TRUE;
    }


    /**
     * All classes than can be registered, or where registrations currently exist.
     * @return A list of RSerClass objects
     */
    public function listClasses() : array {

        // get active classes of the series
        $classes = $this->series()->listClasses();

        // get inactive classes which have active registrations
        $query = "SELECT DISTINCT(RSerRegistrations.Class) FROM RSerRegistrations";
        $query .= " INNER JOIN RSerClasses ON RSerRegistrations.Class=RSerClasses.Id";
        $query .= " WHERE RSerRegistrations.Season={$this->id()}";
        $query .= " AND RSerRegistrations.Active!=0";
        $query .= " AND RSerClasses.Active=0";
        $res = \Core\Database::fetchRaw($query);
        foreach ($res as $row) {
            $classes[] = RSerClass::fromId((int) $row['Class']);
        }

        return $classes;
    }


    //! @return A list of RSerEvent objects
    public function listEvents() : array {
        return RSerEvent::listEvents($this);
    }


    /**
     * List registrations for a certain class
     * @param $class The RSerClass
     * @param $active_only If TRUE (default=FALSE), then only actrive registrations are returned
     * @return A list of RSerRegistration objects
     */
    public function listRegistrations(?RSerClass $class,
                                      bool $active_only=FALSE) : array {
        return RSerRegistration::listRegistrations($this, $class, $active_only);
    }


    /**
     * List all available seasons
     * @param $rser_series All seasons of this series are returned
     * @return A list of RSerSeason objects
     */
    public static function listSeasons(RSerSeries $rser_series) : array {

        // prepare query
        $query = "SELECT Id FROM RSerSeasons WHERE Series={$rser_series->id()} ORDER BY Id DESC";

        // list results
        $list = array();
        foreach (\Core\Database::fetchRaw($query) as $row) {
            $id = (int) $row['Id'];
            $list[] = RSerSeason::fromId($id);
        }

        return $list;
    }



    /**
     * List all stadings
     * @param $class The RSerClass
     * @return A list of RSerStanding objects, ordered by position
     */
    public function listStandings(RSerClass $class) : array {
        return RSerStanding::listStandings($this, $class);
    }


    //! @return The race-series-name of this car class
    public function name() {
        return $this->loadColumn("Name");
    }


    //! @return The according RSerSeries object
    public function series() : RSerSeries {
        return RSerSeries::fromId((int) $this->loadColumn("Series"));
    }


    //! @param $new_name The new name for the season
    public function setName(string $new_name) {
        $this->storeColumns(["Name"=>$new_name]);
    }
}
