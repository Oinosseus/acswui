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
            $query = "SELECT DISTINCT(RSerResults.Event) FROM RSerEvents INNER JOIN RSerResults ON RSerResults.Event = RSerEvents.Id WHERE RSerEvents.Season={$this->id()} AND RSerEvents.Valuation!=0.0;";
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
            $url = "index.php?HtmlContent=RSer&RSerSeries={$this->series()->id()}&RSerSeason={$this->id()}&View=SeasonOverview";
            // if ($current_season) {  // this adds a link to the current season
            //     $url .= "&RSerSeason={$current_season->id()}&View=SeasonOverview";
            // }
            $html = "<a href=\"$url\">$html</a>";
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
