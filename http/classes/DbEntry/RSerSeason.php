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


    //! Set driver registrations to inactive when they missed too much races
    public function autoUnregister() {

        // determine point of last requested activitiy
        $unregister_races = $this->series()->getParam("AutoUnregisterRaces");

        // list events
        $events = array();
        foreach ($this->listEvents() as $rser_event) {
            $e = array();
            $e['Event'] = $rser_event;
            $e['AnyResults'] = FALSE;
            foreach ($this->series()->listClasses(FALSE) as $rser_class) {
                if (count($rser_event->listResultsDriver($rser_class)) > 0) {
                    $e['AnyResults'] = TRUE;
                    break;
                }
            }
            $events[] = $e;
        }

        // list events where any activity is necessary to not unregister
        $any_activity_events = array();
        $active_event_counter = NULL;
        foreach (array_reverse($events) as $e) {
            if ($active_event_counter === NULL) {
                if ($e['AnyResults']) $active_event_counter = 0;
                else continue;
            } else {
                ++$active_event_counter;
            }

            if ($active_event_counter >= $unregister_races) break;
            else $any_activity_events[] = $e;
        }

        // check all current registrations for activity
        foreach ($this->series()->listClasses(FALSE) as $rser_class) {
            foreach ($this->listRegistrations($rser_class) as $rser_reg) {

                // list all users of this registration
                $registration_user_ids = array();
                if ($rser_reg->user()) $registration_user_ids[] = $rser_reg->user()->id();
                if ($rser_reg->teamCar()) {
                    foreach ($rser_reg->teamCar()->drivers() as $team_member) {
                        $registration_user_ids[] = $team_member->user()->id();
                    }
                }

                // check for activity
                $activity = FALSE;
                foreach ($any_activity_events as $e) {
                    foreach ($e['Event']->listResultsDriver($rser_class) as $rser_rslt) {
                        if (in_array($rser_rslt->user()->id(), $registration_user_ids)) {
                            $activity = TRUE;
                            break;
                        }
                    }
                    if ($activity) break;
                }

                // deactivate registration
                if ($activity == FALSE) $rser_reg->deactivate();
            }
        }
    }


    /**
     * @warning This function is cached and will work wrong if events change during lifetime of this object
     * @return The number of events where results are available
     */
    public function countResultedEvents() : int {
        if ($this->CacheResultedEventCount === NULL) {
            $query = "SELECT DISTINCT(RSerResultsDriver.Event) FROM RSerEvents INNER JOIN RSerResultsDriver ON RSerResultsDriver.Event = RSerEvents.Id WHERE RSerEvents.Season={$this->id()} AND RSerEvents.Valuation!=0.0;";
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
     * @param $include_link Include a link
     * @param $show_name Include full name
     * @param $show_img Include a preview image
     * @return Html content for this object
     */
    public function html(bool $include_link = TRUE,
                         bool $show_name = TRUE,
                         bool $show_img = TRUE) {

        $html = "";

        // image
        $img_alt = $this->series()->name() . " / " . $this->name();
        if ($show_img) $html .= "<img class=\"RSerSeriesLogoImage\" src=\"{$this->series()->logoPath()}\" alt=\"$img_alt\" title=\"$img_alt\">";

        $class_names = [];
        foreach ($this->series()->listClasses() as $c) $class_names[] = $c->name();
        $class_names_string = implode(", ", $class_names);

        // find current season
        $current_season = NULL;
        $next_split = $this->series()->nextSplit();
        if ($next_split) {
            $current_season = $next_split->event()->season();
        } else {
            $seasons = $this->series()->listSeasons();
            if (count($seasons)) {
                $current_season = $seasons[0];
            }
        }

        // information
        if ($show_name) {
            $html .= "<div class=\"RSerInformation\">";
            $html .= "<label>{$img_alt}</label>";
            $html .= "<div class=\"RSerInfoClasses\">$class_names_string</div>";
            if ($current_season) {
                $html .= "<div class=\"RSerInfoRegistrations\">";
                $html .= count($current_season->listRegistrations(NULL));
                $html .= " " . _("Registrations");
                $html .= "</div>";
            }
            $html .= "</div>";
        }

        // link container
        if ($include_link) {
            $html = "<a href=\"{$this->url()}\">$html</a>";
        } else {
            $html = "<div>$html</div>";
        }

        // DbEntry container
        $html = "<div class=\"DbEntryHtml DbEntryHtmlRSerSeries\" title=\"{$img_alt}\">$html</div>";
        return $html;
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


    /**
     * List all results
     * @param $class The RSerClass
     * @return A list of RSerStandingDriver objects, ordered by position
     */
    public function listStandingsDriver(RSerClass $class) : array {
        return \DbEntry\RSerStandingDriver::listResults($this, $class);
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


    //! @return URL to the season overview
    public function url() : string {
        return "index.php?HtmlContent=RSer&RSerSeries={$this->series()->id()}&RSerSeason={$this->id()}&View=SeasonOverview";
    }
}
