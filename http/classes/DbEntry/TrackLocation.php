<?php

namespace DbEntry;

/**
 * Cached wrapper to car databse TrackLocations table element
 */
class TrackLocation extends DbEntry {

    private $Track = NULL;
    private $Name = NULL;
    private $Deprecated = NULL;
    private $GeoLocation = NULL;

    private $ListTracksNDepr = NULL;  // excluding deprecated
    private $ListTracksDepr = NULL;  // including deprecated


    /**
     * Construct a new object
     * @param $id Database table id
     */
    public function __construct(int $id) {
        parent::__construct("TrackLocations", $id);
    }


    //! @return Country of the location
    public function country() {
        return $this->loadColumn("Country");
    }


    //! @return TRUE when this car is deprected
    public function deprecated() {
        if ($this->Deprecated === NULL)
            $this->Deprecated = ($this->loadColumn('Deprecated') == 0) ? FALSE : TRUE;
        return $this->Deprecated;
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("TrackLocations", "TrackLocation", $id);
    }


    //! @return An GeoLocation object
    public function geoLocation() : \Core\GeoLocation {
        if ($this->GeoLocation === NULL) {
            $lat = (float) $this->loadColumn("Latitude");
            $lon = (float) $this->loadColumn("Longitude");
            $this->GeoLocation = new \Core\GeoLocation($lat, $lon);
        }

        return $this->GeoLocation;
    }


    /**
     * @param $include_link Include a link
     * @param $show_label Include a label
     * @param $show_img Include a preview image
     * @return Html content for this object
     */
    public function html(bool $include_link = TRUE, bool $show_label = TRUE, bool $show_img = TRUE) {

        $tl_id = $this->id();
        $tl_track = $this->track();
        $tl_name = $this->name();
        $img_id = "TrackLocationImage$tl_id";

        // try to find first available Track
        $tracks = $this->listTracks();
        $basepath = "";

        $preview_path = "";
        $hover_path = "";
        if (count($tracks)) {
            $track_id = $tracks[0]->id();
            $preview_path = \Core\Config::RelPathHtdata . "/htmlimg/tracks/$track_id.png";
            $hover_path = \Core\Config::RelPathHtdata . "/htmlimg/tracks/$track_id.hover.png";
        }

        $html = "";
        if ($show_label) $html .= "<label for=\"$img_id\">$tl_name</label>";
        if ($show_img) $html .= "<img class=\"HoverPreviewImage\" src=\"$preview_path\" id=\"$img_id\" alt=\"$tl_name\" title=\"$tl_name\">";
        if ($include_link) $html = "<a href=\"index.php?HtmlContent=TrackLocation&Id=$tl_id\">$html</a>";

        $html = "<div class=\"DbEntryHtml\">$html</div>";
        return $html;
    }


    //! @return A list of all available countries, ordered by name
    public static function listCountries() {
        $countries = array();
        $res = \Core\Database::fetchRaw("SELECT DISTINCT Country FROM `TrackLocations` WHERE Deprecated=0 ORDER BY Country ASC;");
        foreach ($res as $row) {
            $countries[] = $row['Country'];
        }
        return $countries;
    }


    /**
     * @param $inculde_deprecated When TRUE (default FALSE) also deprected locations are listed
     * @param $country If not NULL (default) then only locations of this country are returned
     * @return A list of available TrackLocation objects, ordered by Name
     */
    public static function listLocations($inculde_deprecated=FALSE, $country=NULL) {
        $ret = array();

        $where = array();
        if (!$inculde_deprecated) $where["Deprecated"] = 0;
        if ($country !== NULL) $where["Country"] = $country;

        $res = \core\Database::fetch("TrackLocations", ["Id"], $where, "Name");

        // extract values
        foreach ($res as $row) {
            $id = (int) $row['Id'];
            $ret[] = TrackLocation::fromId($id);
        }

        return $ret;
    }


    //! A list of Track objects at this location
    public function listTracks($inculde_deprecated=FALSE) {

        $ret= [];

        if ($inculde_deprecated) {

            // update cache
            if ($this->ListTracksDepr == NULL) {
                $query = "SELECT Id FROM Tracks WHERE Location = " . $this->id();
                $res = \core\Database::fetchRaw($query);

                // extract values
                $this->ListTracksDepr = array();
                foreach ($res as $row) {
                    $id = (int) $row['Id'];
                    $this->ListTracksDepr[] = Track::fromId($id);
                }
            }
            $ret = $this->ListTracksDepr;

        } else {

            // update cache
            if ($this->ListTracksNDepr == NULL) {
                $query = "SELECT Id FROM Tracks WHERE Location = " . $this->id();
                $res = \core\Database::fetchRaw($query);

                // extract values
                $this->ListTracksNDepr = array();
                foreach ($res as $row) {
                    $id = (int) $row['Id'];
                    $this->ListTracksNDepr[] = Track::fromId($id);
                }
            }
            $ret = $this->ListTracksNDepr;
        }

        return $ret;
    }


    //! @return User friendly name
    public function name() {
        if ($this->Name === NULL) $this->Name = $this->loadColumn("Name");
        return $this->Name;
    }


    //! @return The track name in content/tracks folder
    public function track() {
        if ($this->Track === NULL) $this->Track = $this->loadColumn("Track");
        return $this->Track;
    }
}
