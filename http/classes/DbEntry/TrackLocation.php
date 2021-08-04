<?php

namespace DbEntry;

/**
 * Cached wrapper to car databse TrackLocations table element
 */
class TrackLocation extends DbEntry {

    private $Track = NULL;
    private $Name = NULL;
    private $Deprecated = NULL;

    private $ListTracksNDepr = NULL;  // excluding deprecated
    private $ListTracksDepr = NULL;  // including deprecated


    /**
     * Construct a new object
     * @param $id Database table id
     */
    public function __construct(int $id) {
        parent::__construct("TrackLocations", $id);
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


    //! @return Html img tag containing preview image
    public function htmlImg() {
        $html = "";

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

        $html = "<a class=\"TrackLink\" href=\"index.php?HtmlContent=TrackLocation&Id=$tl_id\">";
        $html .= "<label for=\"$img_id\">$tl_name</label>";
        $html .= "<img src=\"$preview_path\" id=\"$img_id\" alt=\"$tl_name\" title=\"$tl_name\">";
        $html .= "</a>";

        $html .= "<script>";
        $html .= "var e = document.getElementById('$img_id');";

        # show different hover image
        $html .= "e.addEventListener('mouseover', function() {";
        $html .= "this.src='$hover_path';";
        $html .= "});";

        # show track;
        $html .= "e.addEventListener('mouseout', function() {";
        $html .= "this.src='$preview_path';";
        $html .= "});";

        $html .= "</script>";

        return $html;
    }


    public static function listLocations($inculde_deprecated=FALSE) {
        $ret = array();

        $query = "SELECT Id FROM TrackLocations";
        if (!$inculde_deprecated) $query .= " WHERE Deprecated = 0";
        $query .= " ORDER BY Name ASC";
        $res = \core\Database::fetchRaw($query);

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


    //! @return User friendly name of the car
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
