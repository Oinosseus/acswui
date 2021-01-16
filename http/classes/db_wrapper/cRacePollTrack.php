<?php

/**
 * Cached wrapper to RacePollTracks database table element
 */
class RacePollTrack {

    private $Id = NULL;
    private $Track = NULL;
    private $CarClass = NULL;
    private $ScoreUser = NULL;
    private $ScoreOverall = NULL;

    public function __construct(int $id) {
        $this->Id = (int) $id;
    }

    public function id() {
        return $this->Id;
    }

    //! @return The according CarClass object
    public function carClass() {
        if ($this->CarClass === NULL) $this->updateDb();
        return $this->CarClass;
    }


    /**
     * Delete a track from poll
     * @param $id The DB id of the RacePollTrack to be deleted
     */
    public static function delete(int $id) {
        global $acswuiDatabase;

        // delete votes
        $res = $acswuiDatabase->fetch_2d_array("RacePollTrackMap", ['Id'], ['Track'=>$id]);
        foreach ($res as $row) {
            $acswuiDatabase->delete_row("RacePollTrackMap", $row['Id']);
        }

        // delete poll
        $acswuiDatabase->delete_row("RacePollTracks", $id);
    }


    public function getScoreOverall() {
        global $acswuiDatabase;

        if ($this->ScoreOverall === NULL) {
            $this->ScoreOverall = 0;
            $res = $acswuiDatabase->fetch_2d_array("RacePollTrackMap", ['Score'], ['Track'=>$this->Id]);
            if (count($res) > 0) {
                foreach ($res as $row) {
                    $this->ScoreOverall += $row['Score'];
                }
                $this->ScoreOverall /= count($res);
            }
        }

        return $this->ScoreOverall;
    }

    //! @return The poll score of the current logged user
    public function getScoreUser() {
        global $acswuiDatabase;
        global $acswuiUser;

        if ($this->ScoreUser === NULL) {
            $this->ScoreUser = 0;

            $res = $acswuiDatabase->fetch_2d_array("RacePollTrackMap", ['Score'], ['User'=>$acswuiUser->Id, 'Track'=>$this->Id]);
            if (count($res) > 0) {
                $this->ScoreUser = $res[0]['Score'];
            }
        }

        return $this->ScoreUser;
    }


    /**
     * Generate a list of all existing RacePollTrack items for a certain car class.
     * @param $cc The CarClass object for which the tracks shall be listed
     * @return A list of RacePollTrack objects that not reside in the past
     */
    public static function listTracks(CarClass $cc) {
        global $acswuiDatabase;

        $t = array();

        $res = $acswuiDatabase->fetch_2d_array("RacePollTracks", ['Id'], ['CarClass'=>$cc->id()]);
        foreach ($res as $row) {
            $t[] = new RacePollTrack($row['Id']);
        }

        return $t;
    }

    /**
     * Creating a new RacePollTrack in the database (if not already existent
     * @param $cc The CarClass object for which the tracks shall be listed
     * @param $t The Track object
     * @return The new (or already existing) RacePollTrack object
     */
    public static function newTrack(CarClass $cc, Track $t) {
        global $acswuiDatabase;

        // prepare db columns
        $cols = array();
        $cols['Track'] = $t->id();
        $cols['CarClass'] = $cc->id();

        // try to find existing db entry
        $res = $acswuiDatabase->fetch_2d_array("RacePollTracks", ['Id'], $cols);
        if (count($res) > 0) return new RacePollTrack($res[0]['Id']);

        // create new db entry
        $id = $acswuiDatabase->insert_row("RacePollTracks", $cols);
        return new RacePollTrack($id);
    }


    /**
     * Save a new score for the current user
     * The new value is clipped to the range [0, 100]
     * @param $new_score The new score value
     */
    public function setScoreUser(int $new_score) {
        global $acswuiDatabase;
        global $acswuiUser;

        // clipping
        if ($new_score < 0) $new_score = 0;
        if ($new_score > 100) $new_score = 100;

        // db columns
        $cols = array();
        $cols['User'] = $acswuiUser->Id;
        $cols['Track'] = $this->Id;

        // check if already in db
        $res = $acswuiDatabase->fetch_2d_array("RacePollTrackMap", ['Id'], $cols);
        $cols['Score'] = $new_score;
        if (count($res) > 0) {
            $acswuiDatabase->update_row("RacePollTrackMap", $res[0]['Id'], $cols);
        } else {
            $acswuiDatabase->insert_row("RacePollTrackMap", $cols);
        }
    }


    //! @return The according Track object
    public function track() {
        if ($this->Track === NULL) $this->updateDb();
        return $this->Track;
    }


    private function updateDb() {
        global $acswuiLog;
        global $acswuiDatabase;

        // get basic information
        $res = $acswuiDatabase->fetch_2d_array("RacePollTracks", ['Track', 'CarClass'], ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find RacePollTracks.Id=" . $this->Id);
            return;
        }

        $this->Track = new Track($res[0]['Track']);
        $this->CarClass = new CarClass($res[0]['CarClass']);
    }

//     /**
//      * Set the current user available/unavailable for this date
//      * @param $avail TRUE when available, FALSE when not
//      */
//     public function setAvailable($avail) {
//         global $acswuiDatabase;
//         global $acswuiUser;
//
//         // ensure db entry exist
//         $map_id = NULL;
//         $fields = array();
//         $fields['Date'] = $this->Id;
//         $fields['User'] = $acswuiUser->Id;
//         $existing_maps = $acswuiDatabase->fetch_2d_array("RacePollDateMap", ['Id'], $fields);
//         if (count($existing_maps) == 0) {
//             $map_id = $acswuiDatabase->insert_row("RacePollDateMap", $fields);
//         } else if (count($existing_maps) > 0) {
//             $map_id = $existing_maps[0]['Id'];
//         }
//
//         // update availability
//         $fields['Availability'] = ($avail === TRUE) ? 1 : -1;
//         $acswuiDatabase->update_row("RacePollDateMap", $map_id, $fields);
//     }

//     private function updateAvailabilities() {
//         global $acswuiDatabase;
//
//         $this->AvailableUsers = array();
//         $this->UnAvailableUsers = array();
//         $res = $acswuiDatabase->fetch_2d_array("RacePollDateMap", ['User', 'Availability'], ['Date'=>$this->Id]);
//         foreach ($res as $row) {
//             if ($row['Availability'] > 0) {
//                 $this->AvailableUsers[] = $row['User'];
//             } else if ($row['Availability'] < 0) {
//                 $this->UnAvailableUsers[] = $row['User'];
//             }
//         }
//     }
}

?>
