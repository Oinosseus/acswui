<?php

namespace DbEntry;

/**
 * Cached wrapper to database Polls table element
 */
class Poll extends DbEntry {
    private $Creator = NULL;
    private $Closing = NULL;

    private $Tracks = NULL;
    private $CarClasses = NULL;



    /**
     * Construct a new object
     * @param $id Database table id
     */
    protected function __construct(int $id) {
        parent::__construct("Polls", $id);
    }



    /**
     * Add a car class to vote in this poll.
     * If the car class already exists, it will be ignored
     * @param $new_carclass The new CarClass object to be added
     */
    public function addCarClass(\DbEntry\CarClass $new_carclass) {

        // check if already available
        $current_ccs = $this->carClasses();
        foreach ($current_ccs as $cc) {
            if ($cc->id() == $new_carclass->id()) return;
        }

        // add to DB
        $fields = array();
        $fields['Poll'] = $this->id();
        $fields['CarClass'] = $new_carclass->id();
        \Core\Database::insert("PollCarClasses", $fields);

        // invalidate cache
        $this->CarClasses = NULL;
    }



    /**
     * Add a track to vote in this poll.
     * If the track already exists, it will be ignored
     * @param $new_track The new Track object to be added
     */
    public function addTrack(\DbEntry\Track $new_track) {

        // check if already available
        $current_tracks = $this->tracks();
        foreach ($current_tracks as $t) {
            if ($t->id() == $new_track->id()) return;
        }

        // add to DB
        $fields = array();
        $fields['Poll'] = $this->id();
        $fields['Track'] = $new_track->id();
        \Core\Database::insert("PollTracks", $fields);

        // invalidate cache
        $this->Tracks = NULL;
    }



    //! @return A list of CarClass objects that can be voted in this poll
    public function carClasses() {
        if ($this->CarClasses === NULL) {
            $this->CarClasses = array();

            // db columns
            $columns = array();
            $columns[] = 'CarClass';

            // request from db
            $res = \Core\Database::fetch("PollCarClasses", $columns, ['Poll'=>$this->id()]);
            foreach ($res as $row) {
                $this->CarClasses[] = \DbEntry\CarClass::fromId($row['CarClass']);
            }
        }

        return $this->CarClasses;
    }



    //! @return A list of CarClass objects ordered by their voted points (this is not a cached function)
    public function carClassesOrdered() {
        $carclasses = array();
        $id = $this->id();

        $query  = "SELECT PollCarClasses.CarClass AS CarClass, SUM(PollVotes.Points) AS Points ";
        $query .= "FROM `PollVotes` ";
        $query .= "INNER JOIN PollCarClasses ON PollCarClasses.Id = PollVotes.PollCarClass ";
        $query .= "WHERE PollCarClasses.Poll = $id GROUP BY PollCarClasses.CarClass ";
        $query .= "ORDER BY Points DESC";
        $res = \Core\Database::fetchRaw($query);

        foreach ($res as $row) {
            $carclasses[] = \DbEntry\CarClass::fromId($row['CarClass']);
        }

        return $carclasses;
    }



    //! @retrun DateTime object with definition when poll closes
    public function closing() {
        if ($this->Closing === NULL) {
            $timestamp = $this->loadColumn("Closing");
            $this->Closing = new \DateTime($timestamp);
        }

        return $this->Closing;
    }



    //! @return The User  object of who created this poll (cann be NULL)
    public function creator() {
        return \DbEntry\User::fromId($this->loadColumn("Creator"));
    }



    //! @return A new created Poll object
    public static function createNew() {

        $closing = new \DateTime("now");
        $closing->add(new \DateInterval('P7D'));

        $fields = array();
        $fields['Creator'] = \Core\UserManager::currentUser()->id();
        $fields['Name'] = "New Poll";
        $fields['Closing'] = \Core\Database::timestamp($closing);

        $id = \Core\Database::insert("Polls", $fields);
        return Poll::fromId($id);
    }



    /**
     * Deletes this Poll including all votes.
     * The current object will stay in memory.
     * When calling a sat*() method, a new Poll object will be created
     */
    public function deleteFromDb() {

        // delete track votes
        $query = "SELECT PollVotes.Id FROM  PollVotes INNER JOIN PollTracks ON PollVotes.PollTrack = PollTracks.Id WHERE PollTracks.Poll={$this->id()};";
        foreach (\Core\Database::fetchRaw($query) as $row) {
            \Core\Database::delete("PollVotes", $row['Id']);
        }

        // delete tracks
        $query = "DELETE FROM PollTracks WHERE Poll={$this->id()};";
        \Core\Database::query($query);

        // delete carclass votes
        $query = "SELECT PollVotes.Id FROM  PollVotes INNER JOIN PollCarClasses ON PollVotes.PollCarClass = PollCarClasses.Id WHERE PollCarClasses.Poll={$this->id()};";
        foreach (\Core\Database::fetchRaw($query) as $row) {
            \Core\Database::delete("PollVotes", $row['Id']);
        }

        // delete car classes
        $query = "DELETE FROM PollCarClasses WHERE Poll={$this->id()};";
        \Core\Database::query($query);

        // delete this poll
        parent::deleteFromDb();
    }



    //! @retrun More detailed information about the poll
    public function description() {
        return $this->loadColumn("Description");
    }



    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("Polls", "Poll", $id);
    }



    //! @return TRUE when the closing time of the poll is passed
    public function isClosed() {
        $closing = $this->closing();
        $now = new \DateTime("now");
        return $now > $closing;
    }



    //! @retrun TRUE if this is a secret poll (not an open ballot)
    public function isSecret() {
        return ($this->loadColumn('IsSecret') == "0") ? FALSE : TRUE;
    }



    //! @return A List of existing Poll objects
    public static function listPolls() {
        $ret = array();
        $res = \Core\Database::fetch("Polls", ['Id'], [], "Closing", FALSE);
        foreach ($res as $row) {
            $ret[] = Poll::fromId($row['Id']);
        }
        return $ret;
    }



    //! @return The name of the poll
    public function name() {
        return $this->loadColumn("Name");
    }



    //! @return The amount of points that can be voted per user for all car classes in sum
    public function pointsForCarClasses() {
        return (int) $this->loadColumn("PointsForCarClasses");
    }



    //! @return The amount of points that can be voted per user for all Tracks in sum
    public function pointsForTracks() {
        return (int) $this->loadColumn("PointsForTracks");
    }



    /**
     * The amount of points votes for a certain car class.
     * When user is NULL, the summ of all users is returned.
     * When the CarClass is not valid, 0 is returned.
     * @param $user A User object or NULL (default)
     * @param $carclass The requested CarClass object
     * @return The amount of voted points for the car class
     * @todo swap arguments (since $carclass is mandatory it should be first argument)
     */
    public function pointsOfCarClass(?\DbEntry\User $user = NULL, \DbEntry\CarClass $carclass) {

        // create query
        $query = "SELECT SUM(PollVotes.Points) FROM PollVotes";
        $query .= " INNER JOIN PollCarClasses ON PollVotes.PollCarClass = PollCarClasses.Id";
        $query .= " INNER JOIN Polls ON PollCarClasses.Poll = Polls.Id";
        $query .= " WHERE Polls.Id = {$this->id()}";
        $query .= " AND PollCarClasses.CarClass = {$carclass->id()}";
        if ($user !== NULL) $query .= " AND PollVotes.User = {$user->id()}";

        // execute query
        $res = \Core\Database::fetchRaw($query);
        if (count($res) !== 1) {
            $msg = "Unexpected DB answer!\n";
            $msg .= "Poll=" . $this->id() . ", ";
            $msg .= "Track=" . $track->id() . ", ";
            $msg .= "count(res)=" . count($res);
            \Core\Log::error($msg);
            return 0;
        }
        return (int) $res[0]['SUM(PollVotes.Points)'];
    }



    /**
     * The amount of points voted for a certain track.
     * When user is NULL, the summ of all users is returned.
     * When the Track is not valid, 0 is returned.
     * @param $user A User object or NULL (default)
     * @param $track The requested Track object
     * @return The amount of voted points for the track
     * @todo swap arguments (since $track is mandatory it should be first argument)
     */
    public function pointsOfTrack(?User $user = NULL, Track $track) {

        // create query
        $query = "SELECT SUM(PollVotes.Points) FROM PollVotes";
        $query .= " INNER JOIN PollTracks ON PollVotes.PollTrack = PollTracks.Id";
        $query .= " INNER JOIN Polls ON PollTracks.Poll = Polls.Id";
        $query .= " WHERE Polls.Id = {$this->id()}";
        $query .= " AND PollTracks.Track = {$track->id()}";
        if ($user !== NULL) $query .= " AND PollVotes.User = {$user->id()}";

        // execute query
        $res = \Core\Database::fetchRaw($query);
        if (count($res) !== 1) {
            $msg = "Unexpected DB answer!\n";
            $msg .= "Poll=" . $this->id() . ", ";
            $msg .= "Track=" . $track->id() . ", ";
            $msg .= "count(res)=" . count($res);
            \Core\Log::error($msg);
            return 0;
        }
        return (int) $res[0]['SUM(PollVotes.Points)'];
    }



    //! @return The amount of points that can be voted per user for a single car classes
    public function pointsPerCarClass() {
        return (int) $this->loadColumn("PointsPerCarClass");
    }



    //! @return The amount of points that can be voted per user for a single Track
    public function pointsPerTrack() {
        return (int) $this->loadColumn("PointsPerTrack");
    }



    /**
     * Remove a car class from the vote.
     * If the car class does not exist, it will be ignored
     * @param $carclass The CarClass object to be removed
     */
    public function removeCarClass(CarClass $carclass) {

        // find carclass
        $fields = ['Poll' => $this->id(), 'CarClass' => $carclass->id()];
        $res_cc = \Core\Database::fetch("PollCarClasses", ['Id'], $fields);
        foreach ($res_cc as $row_cc) {

            // remove car class
            \Core\Database::delete("PollCarClasses", $row_cc['Id']);

            // scan and remove votes for this carclass
            $res_votes = \Core\Database::fetch("PollVotes", ['Id'], ['PollCarClass'=>$row_cc['Id']]);
            foreach ($res_votes as $row_votes) {
                \Core\Database::delete("PollVotes", $row_votes['Id']);
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

        // find track
        $fields = ['Poll' => $this->id(), 'Track' => $track->id()];
        $res_track = \Core\Database::fetch("PollTracks", ['Id'], $fields);
        foreach ($res_track as $row_track) {

            // remove track
            \Core\Database::delete("PollTracks", $row_track['Id']);

            // scan and remove votes for this track
            $res_votes = \Core\Database::fetch("PollVotes", ['Id'], ['PollTrack'=>$row_track['Id']]);
            foreach ($res_votes as $row_votes) {
                \Core\Database::delete("PollVotes", $row_votes['Id']);
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
            $res = \Core\Database::fetch("PollCarClasses", ['Id'], $fields);
            if (count($res) != 1) {
                $msg = "Missing a carclass entry, this was never expected to happen!\n";
                $msg .= "Polls.Id=" . $this->id() . " ";
                $msg .= "CarClass.Id=" . $cc->id();
                \Core\Log::error($msg);
            }
            $poll_carclass_id = $res[0]['Id'];

            // find vote of this track
            $fields = array();
            $fields['User'] = $user->id();
            $fields['PollCarClass'] = $poll_carclass_id;
            $res = \Core\Database::fetch("PollVotes", ['Id'], $fields);

            // update vote
            $fields = array();
            $fields['User'] = $user->id();
            $fields['PollTrack'] = 0;
            $fields['PollCarClass'] = $poll_carclass_id;
            $fields['Points'] = array_key_exists($cc->id(), $votes_filtered) ? $votes_filtered[$cc->id()] : 0;
            if (count($res) == 0) {
                \Core\Database::insert("PollVotes", $fields);
            } else {
                \Core\Database::update("PollVotes", $res[0]['Id'], $fields);
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
            $res = \Core\Database::fetch("PollTracks", ['Id'], $fields);
            if (count($res) != 1) {
                $msg = "Missing a track entry, this was never expected to happen!\n";
                $msg .= "Polls.Id=" . $this->id() . " ";
                $msg .= "Track.Id=" . $track->id();
                \Core\Log::error($msg);
            }
            $poll_track_id = $res[0]['Id'];

            // find vote of this track
            $fields = array();
            $fields['User'] = $user->id();
            $fields['PollTrack'] = $poll_track_id;
            $res = \Core\Database::fetch("PollVotes", ['Id'], $fields);

            // update vote
            $fields = array();
            $fields['User'] = $user->id();
            $fields['PollTrack'] = $poll_track_id;
            $fields['PollCarClass'] = 0;
            $fields['Points'] = array_key_exists($track->id(), $votes_filtered) ? $votes_filtered[$track->id()] : 0;
            if (count($res) == 0) {
                \Core\Database::insert("PollVotes", $fields);
            } else {
                \Core\Database::update("PollVotes", $res[0]['Id'], $fields);
            }
        }
    }



    //! @param $new_closing Define when the poll shall be closed
    public function setClosing(\DateTime $new_closing) {
        $timestamp = \Core\Database::timestamp($new_closing);
        $this->storeColumns(["Closing"=>$timestamp]);
        $this->Closing = $new_closing;
    }



    //! @param $new_description A new description for this poll
    public function setDescription(string $new_description) {
        $this->storeColumns(["Description"=>$new_description]);
    }



    /**
     * Set a new name for this poll
     * @param $new_name The new name for this poll
     */
    public function setName(string $new_name) {
        $this->storeColumns(["Name"=>$new_name]);
    }



    //! @param $points Set how many points can be voted per user for all car calsses in sum
    public function setPointsForCarClasses(int $points) {
        $this->storeColumns(["PointsForCarClasses"=>$points]);

        // clip points of votes
        $users = \Core\Database::fetchRaw("SELECT DISTINCT `User` FROM `PollVotes`");
        foreach ($users as $user) {
            $user_id = $user['User'];

            $query = "SELECT PollVotes.Id, PollVotes.Points FROM `PollVotes` ";
            $query .= "INNER JOIN PollCarClasses ON PollCarClasses.Id = PollVotes.PollCarClass ";
            $query .= "WHERE PollVotes.PollTrack = 0 AND User = $user_id AND PollCarClasses.Poll = {$this->id()}";
            $votes = \Core\Database::fetchRaw($query);

            // sum vote points
            $vote_points_sum = 0;
            foreach ($votes as $vote) {
                $vote_points_sum += $vote['Points'];
            }

            // rescale votes
            if ($vote_points_sum > $points) {
                foreach ($votes as $vote) {
                    $new_points = floor($vote['Points'] * $points / $vote_points_sum);
                    \Core\Database::update("PollVotes", $vote['Id'], ['Points'=>$new_points]);
                }
            }
        }
    }



    //! @param $points Set how many points can be voted per user for a single car calss
    public function setPointsPerCarClass(int $points) {
        $this->storeColumns(["PointsPerCarClass"=>$points]);

        // clip points of votes
        $res = \Core\Database::fetch("PollCarClasses", ['Id'], ['Poll'=>$this->id()]);
        foreach ($res as $row) {
            $votes = \Core\Database::fetch("PollVotes", ['Id', 'Points'], ['PollCarClass'=>$row['Id']]);
            foreach ($votes as $vote) {
                if ($vote['Points'] > $points) {
                    \Core\Database::update("PollVotes", $vote['Id'], ['Points'=>$points]);
                }
            }
        }
    }



    //! @param $points Set how many points can be voted per user for all tracks in sum
    public function setPointsForTracks(int $points) {
        $this->storeColumns(["PointsForTracks"=>$points]);

        // clip points of votes
        $users = \Core\Database::fetchRaw("SELECT DISTINCT `User` FROM `PollVotes`");
        foreach ($users as $user) {
            $user_id = $user['User'];

            $query = "SELECT PollVotes.Id, PollVotes.Points FROM `PollVotes` ";
            $query .= "INNER JOIN PollTracks ON PollTracks.Id = PollVotes.PollTrack ";
            $query .= "WHERE PollVotes.PollCarClass = 0 AND User = $user_id AND PollTracks.Poll = {$this->id()}";
            $votes = \Core\Database::fetchRaw($query);

            // sum vote points
            $vote_points_sum = 0;
            foreach ($votes as $vote) {
                $vote_points_sum += $vote['Points'];
            }

            // rescale votes
            if ($vote_points_sum > $points) {
                foreach ($votes as $vote) {
                    $new_points = floor($vote['Points'] * $points / $vote_points_sum);
                    \Core\Database::update("PollVotes", $vote['Id'], ['Points'=>$new_points]);
                }
            }
        }
    }



    //! @param $points Set how many points can be voted per user for a single track
    public function setPointsPerTrack(int $points) {
        $this->storeColumns(["PointsPerTrack"=>$points]);

        // clip points of votes
        $res = \Core\Database::fetch("PollTracks", ['Id'], ['Poll'=>$this->id()]);
        foreach ($res as $row) {
            $votes = \Core\Database::fetch("PollVotes", ['Id', 'Points'], ['PollTrack'=>$row['Id']]);
            foreach ($votes as $vote) {
                if ($vote['Points'] > $points) {
                    \Core\Database::update("PollVotes", $vote['Id'], ['Points'=>$points]);
                }
            }
        }
    }



    //! @param $secret Set to TRUE if this poll shall be secret
    public function setSecret(bool $secret) {
        $this->storeColumns(["IsSecret"=>(($secret) ? 1:0)]);
    }


    //! @return A list of Track objects that can be voted in this poll
    public function tracks(bool $order_tracks=FALSE) {

        if ($this->Tracks === NULL) {
            $this->Tracks = array();

            // db columns
            $columns = array();
            $columns[] = 'Track';

            // request from db
            $res = \Core\Database::fetch("PollTracks", $columns, ['Poll'=>$this->id()]);
            foreach ($res as $row) {
                $this->Tracks[] = Track::fromId($row['Track']);
            }
        }

        return $this->Tracks;
    }



    //! @return A list of Track objects ordered by their voted points (this is not a cached function)
    public function tracksOrdered() {

        $tracks = array();
        $id = $this->id();

        $query  = "SELECT PollTracks.Track AS Track, SUM(PollVotes.Points) AS Points ";
        $query .= "FROM `PollVotes` ";
        $query .= "INNER JOIN PollTracks ON PollTracks.Id = PollVotes.PollTrack ";
        $query .= "WHERE PollTracks.Poll = $id GROUP BY PollTracks.Track ";
        $query .= "ORDER BY Points DESC";
        $res = \Core\Database::fetchRaw($query);

        foreach ($res as $row) {
            $tracks[] = Track::fromId($row['Track']);
        }

        return $tracks;
    }



    /**
     * Determine the users that voted
     * @param $track Include users voted tracks
     * @param $carclasses Include users voted car classes
     * @return Array of User objects
     */
    public function votedUsers(bool $tracks = TRUE, bool $carclasses = TRUE) {

        $user_ids = array();

        // find all users voted for tracks
        if ($tracks) {
            $query  = "SELECT DISTINCT PollVotes.User FROM PollVotes ";
            $query .= "INNER JOIN PollTracks ON PollTracks.Id = PollVotes.PollTrack ";
            $query .= "WHERE PollTracks.Poll = " . $this->id();
            $res = \Core\Database::fetchRaw($query);
            foreach ($res as $row) {
                $uid = (int) $row['User'];
                if (!in_array($uid, $user_ids)) $user_ids[] = $uid;
            }
        }

        // find all users voted for carclasses
        if ($carclasses) {
            $query  = "SELECT DISTINCT PollVotes.User FROM PollVotes ";
            $query .= "INNER JOIN PollCarClasses ON PollCarClasses.Id = PollVotes.PollCarClass ";
            $query .= "WHERE PollCarClasses.Poll = " . $this->id();
            $res = \Core\Database::fetchRaw($query);
            foreach ($res as $row) {
                $uid = (int) $row['User'];
                if (!in_array($uid, $user_ids)) $user_ids[] = $uid;
            }
        }

        // determine user objects
        $users = array();
        foreach ($user_ids as $uid) {
            $users[] = User::fromId($uid);
        }

        return $users;
    }
}
