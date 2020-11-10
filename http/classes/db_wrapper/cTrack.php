<?php

/**
 * Cached wrapper to car databse Tracks table element
 */
class Track {

    private $Id = NULL;
    private $Track = NULL;
    private $Config = NULL;
    private $Name = NULL;
    private $Length = NULL;
    private $Pitboxes = NULL;

    /**
     * @param $id Database table id
     */
    public function __construct($id) {
        global $acswuiLog;
        global $acswuiDatabase;

        $this->Id = $id;

        // get basic information
        $res = $acswuiDatabase->fetch_2d_array("Tracks", ['Track', 'Config', 'Name', 'Length', 'Pitboxes'], ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find Tracks.Id=" . $this->Id);
            return;
        }

        $this->Track = $res[0]['Track'];
        $this->Config = $res[0]['Config'];
        $this->Name = $res[0]['Name'];
        $this->Length = (int) $res[0]['Length'];
        $this->Pitboxes = (int) $res[0]['Pitboxes'];
    }

    //! @return Track identification name
    public function track() {
        return $this->Track;
    }

    //! @return Track config identification name
    public function config() {
        return $this->Config;
    }

    //! @return User friendly name of the track
    public function name() {
        return $this->Name;
    }

    //! @return Length of the track in meters
    public function length() {
        return $this->Length;
    }

    //! @return Amount of pitboxes
    public function pitboxes() {
        return $this->Pitboxes;
    }

}

?>
