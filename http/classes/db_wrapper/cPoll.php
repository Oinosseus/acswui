<?php

/**
 * Cached wrapper to database Polls table element
 */
class Poll {
    private $Id = NULL;
    private $IsSecret = NULL;
    private $PointsForTracks = NULL;
    private $PointsPerTrack = NULL;
    private $PointsPerCarClass = NULL;
    private $PointsForCarClasses = NULL;
    private $Name = NULL;
    private $Description = NULL;
    private $Closing = NULL;

    private $Tracks = NULL;
    private $CarClasses = NULL;



    //! @param $id The Id of an existing Polls database row
    public function __construct(int $id) {
        $this->Id = $id;
    }



    //! @return A list of CarClass objects that can be voted in this poll
    public function carClasses() {
        global $acswuiDatabase;

        if ($this->CarClasses === NULL) {
            $this->CarClasses = array();

            // db columns
            $columns = array();
            $columns[] = 'CarClass';

            // request from db
            $res = $acswuiDatabase->fetch_2d_array("PollCarClasses", $columns, ['Poll'=>$this->Id]);
            foreach ($res as $row) {
                $this->CarClasses[] = new CarClass($row['CarClass']);
            }
        }

        return $this->CarClasses;
    }



    //! @return A list of CarClass objects ordered by their voted points (this is not a cached function)
    public function carClassesOrdered() {
        global $acswuiDatabase;

        $carclasses = array();
        $id = $this->id();

        $query  = "SELECT PollCarClasses.CarClass AS CarClass, SUM(PollVotes.Points) AS Points ";
        $query .= "FROM `PollVotes` ";
        $query .= "INNER JOIN PollCarClasses ON PollCarClasses.Id = PollVotes.PollCarClass ";
        $query .= "WHERE PollCarClasses.Poll = $id GROUP BY PollCarClasses.CarClass ";
        $query .= "ORDER BY Points DESC";
        $res = $acswuiDatabase->fetch_raw_select($query);

        foreach ($res as $row) {
            $carclasses[] = new CarClass($row['CarClass']);
        }

        return $carclasses;
    }



    //! @retrun DateTime object with definition when poll closes
    public function closing() {
        if ($this->Closing === NULL) $this->updateFromDb();
        return $this->Closing;
    }



    /**
     * Add a car class to vote in this poll.
     * If the car class already exists, it will be ignored
     * @param $new_carclass The new CarClass object to be added
     */
    public function addCarClass(CarClass $new_carclass) {
        global $acswuiDatabase;

        // check if already available
        $current_ccs = $this->carClasses();
        foreach ($current_ccs as $cc) {
            if ($cc->id() == $new_carclass->id()) return;
        }

        // add to DB
        $fields = array();
        $fields['Poll'] = $this->id();
        $fields['CarClass'] = $new_carclass->id();
        $acswuiDatabase->insert_row("PollCarClasses", $fields);

        // invalidate cache
        $this->CarClasses = NULL;
    }



    /**
     * Add a track to vote in this poll.
     * If the track already exists, it will be ignored
     * @param $new_track The new Track object to be added
     */
    public function addTrack(Track $new_track) {
        global $acswuiDatabase;

        // check if already available
        $current_tracks = $this->tracks();
        foreach ($current_tracks as $t) {
            if ($t->id() == $new_track->id()) return;
        }

        // add to DB
        $fields = array();
        $fields['Poll'] = $this->id();
        $fields['Track'] = $new_track->id();
        $acswuiDatabase->insert_row("PollTracks", $fields);

        // invalidate cache
        $this->Tracks = NULL;
    }



    //! @return A new created Poll object
    public static function createNew() {
        global $acswuiDatabase;

        $closing = new DateTime();
        $closing->add(new DateInterval('P7D'));

        $fields = array();
        $fields['Name'] = "New Poll";
        $fields['Closing'] = $closing->format("Y-m-d H:i");
        $fields['PointsTrack'] = 10;
        $fields['PointsCarClass'] = 10;

        $id = $acswuiDatabase->insert_row("Polls", $fields);
        return new Poll($id);
    }



    //! @retrun More detailed information about the poll
    public function description() {
        if ($this->Description === NULL) $this->updateFromDb();
        return $this->Description;
    }



    //! @return The database Id of the Poll
    public function id() {
        return $this->Id;
    }



    //! @return TRUE when the closing time of the poll is passed
    public function isClosed() {
        $closing = $this->closing();
        $now = new DateTime();
        return $now > $closing;
    }



    //! @retrun TRUE if this is a secret poll (not an open ballot)
    public function isSecret() {
        if ($this->IsSecret === NULL) $this->updateFromDb();
        return $this->IsSecret;
    }



    //! @return A List of existing Poll objects
    public static function listPolls() {
        global $acswuiDatabase;
        $ret = array();
        $res = $acswuiDatabase->fetch_2d_array("Polls", ['Id'], [], "Closing", FALSE);
        foreach ($res as $row) {
            $ret[] = new Poll($row['Id']);
        }
        return $ret;
    }



    //! @return The name of the poll
    public function name() {
        if ($this->Name === NULL) $this->updateFromDb();
        return $this->Name;
    }



    //! @return The amount of points that can be voted per user for all car classes in sum
    public function pointsForCarClasses() {
        if ($this->PointsForCarClasses === NULL) $this->updateFromDb();
        return $this->PointsForCarClasses;
    }



    //! @return The amount of points that can be voted per user for all Tracks in sum
    public function pointsForTracks() {
        if ($this->PointsForTracks === NULL) $this->updateFromDb();
        return $this->PointsForTracks;
    }



    /**
     * The amount of points votes for a certain car class.
     * When user is NULL, the summ of all users is returned.
     * When the CarClass is not valid, 0 is returned.
     * @param $user A User object or NULL (default)
     * @param $carclass The requested CarClass object
     * @return The amount of voted points for the car class
     */
    public function pointsOfCarClass(User $user = NULL, CarClass $carclass) {
        global $acswuiDatabase;
        global $acswuiLog;
        $points = 0;

        // get ID in PollTracks DB
        $fields = array();
        $fields['Poll'] = $this->id();
        $fields['CarClass'] = $carclass->id();
        $res = $acswuiDatabase->fetch_2d_array("PollCarClasses", ["Id"], $fields);
        if (count($res) !== 1) {
            $msg = "Unexpected DB answer!\n";
            $msg .= "Poll=" . $this->id() . ", ";
            $msg .= "CarClass=" . $carclass->id() . ", ";
            $msg .= "count(res)=" . count($res);
            $acswuiLog->logError($msg);
            return 0;
        }
        $poll_cc_id = $res[0]['Id'];

        // count votes
        $fields = array();
        $fields['PollCarClass'] = $poll_cc_id;
        if ($user !== NULL) $fields['User'] = $user->id();
        $res = $acswuiDatabase->fetch_2d_array("PollVotes", ["Points"], $fields);
        foreach ($res as $row) {
            $points += $row['Points'];
        }

        return $points;
    }



    /**
     * The amount of points votes for a certain track.
     * When user is NULL, the summ of all users is returned.
     * When the Track is not valid, 0 is returned.
     * @param $user A User object or NULL (default)
     * @param $track The requested Track object
     * @return The amount of voted points for the track
     */
    public function pointsOfTrack(User $user = NULL, Track $track) {
        global $acswuiDatabase;
        global $acswuiLog;
        $points = 0;

        // get ID in PollTracks DB
        $fields = array();
        $fields['Poll'] = $this->id();
        $fields['Track'] = $track->id();
        $res = $acswuiDatabase->fetch_2d_array("PollTracks", ["Id"], $fields);
        if (count($res) !== 1) {
            $msg = "Unexpected DB answer!\n";
            $msg .= "Poll=" . $this->id() . ", ";
            $msg .= "Track=" . $track->id() . ", ";
            $msg .= "count(res)=" . count($res);
            $acswuiLog->logError($msg);
            return 0;
        }
        $poll_track_id = $res[0]['Id'];

        // count votes
        $fields = array();
        $fields['PollTrack'] = $poll_track_id;
        if ($user !== NULL) $fields['User'] = $user->id();
        $res = $acswuiDatabase->fetch_2d_array("PollVotes", ["Points"], $fields);
        foreach ($res as $row) {
            $points += $row['Points'];
        }

        return $points;
    }



    //! @return The amount of points that can be voted per user for a single car classes
    public function pointsPerCarClass() {
        if ($this->PointsPerCarClass === NULL) $this->updateFromDb();
        return $this->PointsPerCarClass;
    }



    //! @return The amount of points that can be voted per user for a single Track
    public function pointsPerTrack() {
        if ($this->PointsPerTrack === NULL) $this->updateFromDb();
        return $this->PointsPerTrack;
    }



    /**
     * Remove a car class from the vote.
     * If the car class does not exist, it will be ignored
     * @param $carclass The CarClass object to be removed
     */
    public function removeCarClass(CarClass $carclass) {
        global $acswuiDatabase;

        // find carclass
        $fields = ['Poll' => $this->id(), 'CarClass' => $carclass->id()];
        $res_cc = $acswuiDatabase->fetch_2d_array("PollCarClasses", ['Id'], $fields);
        foreach ($res_cc as $row_cc) {

            // remove car class
            $acswuiDatabase->delete_row("PollCarClasses", $row_cc['Id']);

            // scan and remove votes for this carclass
            $res_votes = $acswuiDatabase->fetch_2d_array("PollVotes", ['Id'], ['PollCarClass'=>$row_cc['Id']]);
            foreach ($res_votes as $row_votes) {
                $acswuiDatabase->delete_row("PollVotes", $row_votes['Id']);
            }
        }

        // invalidate cache
        $this->CarClasses = NULL;
    }



    /**
     * Remove a track from the vote.
     * If the track does not exist, it will be ignored
     * @param $track The Track object to be removed
     */
    public function removeTrack(Track $track) {
        global $acswuiDatabase;

        // find track
        $fields = ['Poll' => $this->id(), 'Track' => $track->id()];
        $res_track = $acswuiDatabase->fetch_2d_array("PollTracks", ['Id'], $fields);
        foreach ($res_track as $row_track) {

            // remove track
            $acswuiDatabase->delete_row("PollTracks", $row_track['Id']);

            // scan and remove votes for this track
            $res_votes = $acswuiDatabase->fetch_2d_array("PollVotes", ['Id'], ['PollTrack'=>$row_track['Id']]);
            foreach ($res_votes as $row_votes) {
                $acswuiDatabase->delete_row("PollVotes", $row_votes['Id']);
            }
        }

        // invalidate cache
        $this->Tracks = NULL;
    }



    /**
     * Save the votes for car class points of a certain user.
     * The votes array is like: {CarClass-ID=>Points}
     * @param $user The related User object
     * @param $votes An associative array where keys are CarClass Ids and values are Points
     */
    public function saveCarClassVotes(User $user, array $votes) {
        global $acswuiDatabase;
        global $acswuiLog;

        $ppcc = $this->pointsPerCarClass();
        $pfcc = $this->pointsForCarClasses();

        // check for valid carclasses
        $votes_filtered = array();
        foreach ($this->carClasses() as $cc) {
            if (array_key_exists($cc->id(), $votes)) {
                $votes_filtered[$cc->id()] = $votes[$cc->id()];
            }
        }

        // limit points per carclass
        $votes_points_sum = 0;
        foreach (array_keys($votes_filtered) as $carclass_id) {
            if ($votes_filtered[$carclass_id] > $ppcc) $votes_filtered[$carclass_id] = $ppcc;
            $votes_points_sum += $votes_filtered[$carclass_id];
        }

        // limit points for all carclasses
        if ($votes_points_sum > $pfcc) {
            $factor = $pfcc / $votes_points_sum;
            foreach (array_keys($votes_filtered) as $carclass_id) {
                $votes_filtered[$carclass_id] = floor($votes_filtered[$carclass_id] * $factor);
            }
        }

        // update existing votes
        foreach ($this->carClasses() as $cc) {

            // find track poll entry
            $fields = ['Poll' => $this->id(), 'CarClass' => $cc->id()];
            $res = $acswuiDatabase->fetch_2d_array("PollCarClasses", ['Id'], $fields);
            if (count($res) != 1) {
                $msg = "Missing a carclass entry, this was never expected to happen!\n";
                $msg .= "Polls.Id=" . $this->id() . " ";
                $msg .= "CarClass.Id=" . $cc->id();
                $acswuiLog->logError($msg);
            }
            $poll_carclass_id = $res[0]['Id'];

            // find vote of this track
            $fields = array();
            $fields['User'] = $user->id();
            $fields['PollCarClass'] = $poll_carclass_id;
            $res = $acswuiDatabase->fetch_2d_array("PollVotes", ['Id'], $fields);

            // update vote
            $fields = array();
            $fields['User'] = $user->id();
            $fields['PollTrack'] = 0;
            $fields['PollCarClass'] = $poll_carclass_id;
            $fields['Points'] = array_key_exists($cc->id(), $votes_filtered) ? $votes_filtered[$cc->id()] : 0;
            if (count($res) == 0) {
                $acswuiDatabase->insert_row("PollVotes", $fields);
            } else {
                $acswuiDatabase->update_row("PollVotes", $res[0]['Id'], $fields);
            }
        }
    }



    /**
     * Save the votes for track points of a certain user.
     * The votes array is like: {Track-ID=>Points}
     * @param $user The related User object
     * @param $votes An associative array where keys are Track Ids and values are Points
     */
    public function saveTrackVotes(User $user, array $votes) {
        global $acswuiDatabase;
        global $acswuiLog;

        $ppt = $this->pointsPerTrack();
        $pft = $this->pointsForTracks();

        // check for valid tracks
        $votes_filtered = array();
        foreach ($this->tracks() as $track) {
            if (array_key_exists($track->id(), $votes)) {
                $votes_filtered[$track->id()] = $votes[$track->id()];
            }
        }

        // limit points per track
        $votes_points_sum = 0;
        foreach (array_keys($votes_filtered) as $track_id) {
            if ($votes_filtered[$track_id] > $ppt) $votes_filtered[$track_id] = $ppt;
            $votes_points_sum += $votes_filtered[$track_id];
        }

        // limit points for all tracks
        if ($votes_points_sum > $pft) {
            $factor = $pft / $votes_points_sum;
            foreach (array_keys($votes_filtered) as $track_id) {
                $votes_filtered[$track_id] = floor($votes_filtered[$track_id] * $factor);
            }
        }

        // update existing votes
        foreach ($this->tracks() as $track) {

            // find track poll entry
            $fields = ['Poll' => $this->id(), 'Track' => $track->id()];
            $res = $acswuiDatabase->fetch_2d_array("PollTracks", ['Id'], $fields);
            if (count($res) != 1) {
                $msg = "Missing a track entry, this was never expected to happen!\n";
                $msg .= "Polls.Id=" . $this->id() . " ";
                $msg .= "Track.Id=" . $track->id();
                $acswuiLog->logError($msg);
            }
            $poll_track_id = $res[0]['Id'];

            // find vote of this track
            $fields = array();
            $fields['User'] = $user->id();
            $fields['PollTrack'] = $poll_track_id;
            $res = $acswuiDatabase->fetch_2d_array("PollVotes", ['Id'], $fields);

            // update vote
            $fields = array();
            $fields['User'] = $user->id();
            $fields['PollTrack'] = $poll_track_id;
            $fields['PollCarClass'] = 0;
            $fields['Points'] = array_key_exists($track->id(), $votes_filtered) ? $votes_filtered[$track->id()] : 0;
            if (count($res) == 0) {
                $acswuiDatabase->insert_row("PollVotes", $fields);
            } else {
                $acswuiDatabase->update_row("PollVotes", $res[0]['Id'], $fields);
            }
        }
    }



    //! @param $new_closing Define when the poll shall be closed
    public function setClosing(DateTime $new_closing) {
        global $acswuiDatabase;
        $acswuiDatabase->update_row("Polls", $this->Id, ["Closing"=>$new_closing->format("Y-m-d H:i")]);
        $this->Closing = $new_closing;
    }



    //! @param $new_description A new description for this poll
    public function setDescription(string $new_description) {
        global $acswuiDatabase;
        $acswuiDatabase->update_row("Polls", $this->Id, ["Description"=>$new_description]);
        $this->Name = $new_description;
    }



    /**
     * Set a new name for this poll
     * @param $new_name The new name for this poll
     */
    public function setName(string $new_name) {
        global $acswuiDatabase;
        $acswuiDatabase->update_row("Polls", $this->Id, ["Name"=>$new_name]);
        $this->Name = $new_name;
    }



    //! @param $points Set how many points can be voted per user for all car calsses in sum
    public function setPointsForCarClasses(int $points) {
        global $acswuiDatabase;
        $acswuiDatabase->update_row("Polls", $this->Id, ["PointsForCarClasses"=>$points]);
        $this->PointsForCarClasses = $points;

        // clip points of votes
        $users = $acswuiDatabase->fetch_raw_select("SELECT DISTINCT `User` FROM `PollVotes`");
        foreach ($users as $user) {
            $user_id = $user['User'];

            $query = "SELECT PollVotes.Id, PollVotes.Points FROM `PollVotes` ";
            $query .= "INNER JOIN PollCarClasses ON PollCarClasses.Id = PollVotes.PollCarClass ";
            $query .= "WHERE PollVotes.PollTrack = 0 AND User = $user_id";
            $votes = $acswuiDatabase->fetch_raw_select($query);

            // sum vote points
            $vote_points_sum = 0;
            foreach ($votes as $vote) {
                $vote_points_sum += $vote['Points'];
            }

            // rescale votes
            if ($vote_points_sum > $points) {
                foreach ($votes as $vote) {
                    $new_points = floor($vote['Points'] * $points / $vote_points_sum);
                    $acswuiDatabase->update_row("PollVotes", $vote['Id'], ['Points'=>$new_points]);
                }
            }
        }
    }



    //! @param $points Set how many points can be voted per user for a single car calss
    public function setPointsPerCarClass(int $points) {
        global $acswuiDatabase;
        $acswuiDatabase->update_row("Polls", $this->Id, ["PointsPerCarClass"=>$points]);
        $this->PointsPerCarClass = $points;

        // clip points of votes
        $res = $acswuiDatabase->fetch_2d_array("PollCarClasses", ['Id'], ['Poll'=>$this->id()]);
        foreach ($res as $row) {
            $votes = $acswuiDatabase->fetch_2d_array("PollVotes", ['Id', 'Points'], ['PollCarClass'=>$row['Id']]);
            foreach ($votes as $vote) {
                if ($vote['Points'] > $points) {
                    $acswuiDatabase->update_row("PollVotes", $vote['Id'], ['Points'=>$points]);
                }
            }
        }
    }



    //! @param $points Set how many points can be voted per user for all tracks in sum
    public function setPointsForTracks(int $points) {
        global $acswuiDatabase;
        $acswuiDatabase->update_row("Polls", $this->Id, ["PointsForTracks"=>$points]);
        $this->PointsForTracks = $points;

        // clip points of votes
        $users = $acswuiDatabase->fetch_raw_select("SELECT DISTINCT `User` FROM `PollVotes`");
        foreach ($users as $user) {
            $user_id = $user['User'];

            $query = "SELECT PollVotes.Id, PollVotes.Points FROM `PollVotes` ";
            $query .= "INNER JOIN PollTracks ON PollTracks.Id = PollVotes.PollTrack ";
            $query .= "WHERE PollVotes.PollCarClass = 0 AND User = $user_id";
            $votes = $acswuiDatabase->fetch_raw_select($query);

            // sum vote points
            $vote_points_sum = 0;
            foreach ($votes as $vote) {
                $vote_points_sum += $vote['Points'];
            }

            // rescale votes
            if ($vote_points_sum > $points) {
                foreach ($votes as $vote) {
                    $new_points = floor($vote['Points'] * $points / $vote_points_sum);
                    $acswuiDatabase->update_row("PollVotes", $vote['Id'], ['Points'=>$new_points]);
                }
            }
        }
    }



    //! @param $points Set how many points can be voted per user for a single track
    public function setPointsPerTrack(int $points) {
        global $acswuiDatabase;
        $acswuiDatabase->update_row("Polls", $this->Id, ["PointsPerTrack"=>$points]);
        $this->PointsPerTrack = $points;
        $this->PointsTrackChanged = TRUE;

        // clip points of votes
        $res = $acswuiDatabase->fetch_2d_array("PollTracks", ['Id'], ['Poll'=>$this->id()]);
        foreach ($res as $row) {
            $votes = $acswuiDatabase->fetch_2d_array("PollVotes", ['Id', 'Points'], ['PollTrack'=>$row['Id']]);
            foreach ($votes as $vote) {
                if ($vote['Points'] > $points) {
                    $acswuiDatabase->update_row("PollVotes", $vote['Id'], ['Points'=>$points]);
                }
            }
        }
    }



    //! @param $secret Set to TRUE if this poll shall be secret
    public function setSecret(bool $secret) {
        global $acswuiDatabase;
        $acswuiDatabase->update_row("Polls", $this->Id, ["IsSecret"=>(($secret) ? 1:0)]);
        $this->IsSecret = $secret;
    }


    //! @return A list of Track objects that can be voted in this poll
    public function tracks(bool $order_tracks=FALSE) {
        global $acswuiDatabase;

        if ($this->Tracks === NULL) {
            $this->Tracks = array();

            // db columns
            $columns = array();
            $columns[] = 'Track';

            // request from db
            $res = $acswuiDatabase->fetch_2d_array("PollTracks", $columns, ['Poll'=>$this->Id]);
            foreach ($res as $row) {
                $this->Tracks[] = new Track($row['Track']);
            }
        }

        return $this->Tracks;
    }



    //! @return A list of Track objects ordered by their voted points (this is not a cached function)
    public function tracksOrdered() {
        global $acswuiDatabase;

        $tracks = array();
        $id = $this->id();

        $query  = "SELECT PollTracks.Track AS Track, SUM(PollVotes.Points) AS Points ";
        $query .= "FROM `PollVotes` ";
        $query .= "INNER JOIN PollTracks ON PollTracks.Id = PollVotes.PollTrack ";
        $query .= "WHERE PollTracks.Poll = $id GROUP BY PollTracks.Track ";
        $query .= "ORDER BY Points DESC";
        $res = $acswuiDatabase->fetch_raw_select($query);

        foreach ($res as $row) {
            $tracks[] = new Track($row['Track']);
        }

        return $tracks;
    }



    //! load from DB
    private function updateFromDb() {
        global $acswuiDatabase;
        global $acswuiLog;

        // db columns
        $columns = array();
        $columns[] = 'IsSecret';
        $columns[] = 'PointsPerTrack';
        $columns[] = 'PointsForTracks';
        $columns[] = 'PointsPerCarClass';
        $columns[] = 'PointsForCarClasses';
        $columns[] = 'Name';
        $columns[] = 'Description';
        $columns[] = 'Closing';

        // request from db
        $res = $acswuiDatabase->fetch_2d_array("Polls", $columns, ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find Polls.Id=" . $this->Id);
            return;
        }

        // gather data
        $this->IsSecret = ($res[0]['IsSecret'] == "0") ? FALSE : TRUE;
        $this->PointsPerTrack = (int) $res[0]['PointsPerTrack'];
        $this->PointsForTracks = (int) $res[0]['PointsForTracks'];
        $this->PointsPerCarClass = (int) $res[0]['PointsPerCarClass'];
        $this->PointsForCarClasses = (int) $res[0]['PointsForCarClasses'];
        $this->Name = $res[0]['Name'];
        $this->Description = $res[0]['Description'];
        $this->Closing = new DateTime($res[0]['Closing']);
    }


    /**
     * Determine the current votes of each user for a certain track.
     * The result is
     * @param $track The requested Track
     * @return an
     */
    public function votedUsers(bool $tracks = TRUE, bool $carclasses = TRUE) {
        global $acswuiDatabase;

        $user_ids = array();

        // find all users voted for tracks
        if ($tracks) {
            $query  = "SELECT DISTINCT PollVotes.User FROM PollVotes ";
            $query .= "INNER JOIN PollTracks ON PollTracks.Id = PollVotes.PollTrack ";
            $query .= "WHERE PollTracks.Poll = " . $this->id();
            $res = $acswuiDatabase->fetch_raw_select($query);
            foreach ($res as $row) {
                $uid = (int) $row['User'];
                if (!in_array($uid, $user_ids)) $user_ids[] = $uid;
            }
        }

        // find all users voted for carclasses
        if ($carclasses) {
            $query  = "SELECT DISTINCT PollVotes.User FROM PollVotes ";
            $query .= "INNER JOIN PollCarClasses ON PollCarClasses.Id = PollVotes.PollCarClass ";
            $query .= "WHERE PollCarClass.Poll = " . $this->id();
            $res = $acswuiDatabase->fetch_raw_select($query);
            foreach ($res as $row) {
                $uid = (int) $row['User'];
                if (!in_array($uid, $user_ids)) $user_ids[] = $uid;
            }
        }

        // determine user objects
        $users = array();
        foreach ($user_ids as $uid) {
            $users[] = new User($uid);
        }

        return $users;
    }
}

?>
