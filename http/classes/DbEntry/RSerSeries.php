<?php

declare(strict_types=1);
namespace DbEntry;

//! Wrapper for database table element
class RSerSeries extends DbEntry {

    private $ParameterCollection = NULL;


    /**
     * Construct a new object
     * @param $id Database table id
     */
    protected function __construct(int $id) {
        parent::__construct("RSerSeries", $id);
    }


    /**
     * Creates a new Team that is owned by a certain user
     * @param $owner The User, that owns this Team
     * @return The new created Team object
     */
    public static function createNew() : RSerSeries {
        $id = \Core\Database::insert("RSerSeries", ['Name'=>_("New Race Series")]);
        return RSerSeries::fromId($id);
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) : ?RSerSeries {
        return parent::getCachedObject("RSerSeries", "RSerSeries", $id);
    }


    /**
     * Access parameters statically
     * @return The curent value of a certain parameter
     */
    public function getParam(string $parameter_key) {
        return $this->parameterCollection()->child($parameter_key)->value();
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
        if ($show_img) $html .= "<img class=\"RSerSeriesLogoImage\" src=\"{$this->logoPath()}\" alt=\"{$this->name()}\" title=\"{$this->name()}\">";

        $class_names = [];
        foreach ($this->listClasses() as $c) $class_names[] = $c->name();
        $class_names_string = implode(", ", $class_names);

        // information
        if ($show_name) {
            $html .= "<div class=\"RSerInformation\">";
            $html .= "<label>{$this->name()}</label>";
            $html .= "<div class=\"RSerInfoClasses\">$class_names_string</div>";
            $html .= "</div>";
        }

        // link container
        if ($include_link) {
            $html = "<a href=\"index.php?HtmlContent=RSer&RSerSeries={$this->id()}\">$html</a>";
        } else {
            $html = "<div>$html</div>";
        }

        // DbEntry container
        $html = "<div class=\"DbEntryHtml DbEntryHtmlRSerSeries\" title=\"{$this->name()}\">$html</div>";
        return $html;
    }


    /**
     * List all available classes
     * @param $active_only If TRUE (default) only active classes are returned
     * @return A list of RSerClass objects
     */
    public function listClasses(bool $active_only=TRUE) : array {
        return \DbEntry\RSerClass::listClasses($this, $active_only);
    }


    /**
     * List all available seasons
     * @return A list of RSerSeason objects
     */
    public static function listSeasons() : array {
        return RSerSeason::listSeasons($this);
    }


    /**
     * List available race series
     * @return A list of RSerSeries objects
     */
    public static function listSeries() : array {

        // scan database
        $return_list = array();
        $query  = "SELECT Id FROM RSerSeries ORDER BY ID DESC;";
        foreach (\Core\Database::fetchRaw($query) as $row) {
            $id = (int) $row['Id'];
            $return_list[] = RSerSeries::fromId($id);
        }

        return $return_list;
    }


    //! @return The path to the team logo image
    public function logoPath($html_relative = TRUE) {
        $path = ($html_relative) ? \Core\Config::RelPathHtdata : \Core\Config::AbsPathHtdata;
        $path .= "/htmlimg/rser_logos/{$this->id()}.png";
        return $path;
    }


    //! @return The name of the team
    public function name() : string {
        return $this->loadColumn("Name");
    }


    //! @return A \ParameterCollections\RSerSeries object
    public function parameterCollection() : \ParameterCollections\RSerSeries {
        if ($this->ParameterCollection === NULL) {
            $base_collection = new \ParameterCollections\RSerSeries();
            $this->ParameterCollection = new \ParameterCollections\RSerSeries($base_collection);

            // load from database
            $data_json = $this->loadColumn("ParamColl");
            $data_array = json_decode($data_json, TRUE);
            if ($data_array !== NULL) $this->ParameterCollection->dataArrayImport($data_array);
        }

        return $this->ParameterCollection;
    }


    /**
     * @param $position The final race position
     * @return the points for a certain race position
     */
    public function raceResultPoints(int $position) {
        $points = 0;

        // consolation points
        $pos_con = $this->getParam("PtsPosCons");
        if ($position <= $pos_con) {
            $points += 1;
        }

        // increment points
        $pos_inc = $this->getParam("PtsPosInc");
        if ($position <= $pos_inc) {
            $points += $pos_inc - $position + 1;
        }

        // gain stage
        $pos_gain = $this->getParam("PtsGainPos");
        if ($position <= $pos_gain) {
            $fact_gain = $this->getParam("PtsGainFact");
            $points += ($pos_gain - $position + 1) * $fact_gain;
        }


        return $points;
    }


    //! Save the current parameter collection into the database
    public function save() {
        if ($this->id() == 0) return;

        // parameter data
        $column_data = array();
        $data_array = $this->parameterCollection()->dataArrayExport();
        $data_json = json_encode($data_array);
        $column_data['ParamColl'] = $data_json;

        $this->storeColumns($column_data);
    }


    /**
     * Define a new name for the team
     * @param $new_name The new team name (will be trimmed)
     */
    public function setName(string $new_name) {
        $this->storeColumns(["Name"=>trim($new_name)]);
    }


    /**
     * Copies a Logo from the temporary upload directory into the htdata/htmlimg/rser_logos directory.
     *
     * @param $upload_path Path of the temporary upload location eg. $_FILES["xxx"]["tmp_name"]
     * @param $target_name The target filename (to identify image format) eg. $_FILES["xxx"]["name"]
     * @return True on success
     */
    public function uploadLogoFile(string $upload_path, string $target_name) : bool {

        // check for valid uploaded file (attack prevention)
        if (!is_uploaded_file($upload_path)) {
            \Core\Log::warning("Ignore not uploaded file '" . $upload_path . "'!");
            return False;
        }

        // identify format
        $format = NULL;
        $suffix = strtolower(substr($target_name, -4, 4));
        if (in_array($suffix, [".jpg", "jpeg"])) $format = "jpg";
        else if ($suffix == ".png") $format = "png";
        else \Core\Log::error("Cannot identify format from '{$target_name}'!");

        // load new logo
        $img = new \Core\ImageMerger(600, 200);
        $img->merge($upload_path, TRUE, 1.0, $format);
        $img->save($this->logoPath(FALSE));

        // if reached here, upload was successfull
        return True;
    }
}
