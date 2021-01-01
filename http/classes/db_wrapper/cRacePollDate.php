<?php

/**
 * Cached wrapper to RacePollDate database table element
 */
class RacePollDate {

    private $Id = NULL;
    private $Date = NULL;
    private $AvailableUsers = NULL;
    private $UnAvailableUsers = NULL;

    public function __construct(int $id) {
        $this->Id = (int) $id;
    }

    //! @return The number of available drivers at this date
    public function availabilities() {
        if ($this->AvailableUsers === NULL) $this->updateAvailabilities();
        return count($this->AvailableUsers);
    }

    public function id() {
        return $this->Id;
    }

    //! @return A DateTime object represening the date
    public function date() {
        global $acswuiDatabase;
        global $acswuiLog;

        if ($this->Date !== NULL) return $this->Date;

        $res = $acswuiDatabase->fetch_2d_array("RacePollDates", ['Date'], ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find Id=" . $this->Id);
            return;
        }
        $this->Date = new DateTime($res[0]['Date']);

        return $this->Date;
    }

    /**
     * Test if a certain user is available for this date.
     * Check isUnAvailable() to determine if user has voted.
     * Users that have not voted are neither available, nor unavailable
     * @param $user The requested User object
     * @return True if the user is available on this date (else false)
     */
    public function isAvailable(User $user) {
        if ($this->AvailableUsers === NULL) $this->updateAvailabilities();
        return (in_array($user->id(), $this->AvailableUsers)) ? TRUE : FALSE;
    }

    /**
     * Test if a certain user is unavailable for this date.
     * Check isAvailable() to determine if user has voted.
     * Users that have not voted are neither available, nor unavailable
     * @param $user The requested User object
     * @return True if the user is unavailable on this date (else false)
     */
    public function isUnAvailable(User $user) {
        if ($this->UnAvailableUsers === NULL) $this->updateAvailabilities();
        return (in_array($user->id(), $this->UnAvailableUsers)) ? TRUE : FALSE;
    }

    /**
     * Generate a list of all existing database items.
     * Dates that reside in the past excluded from the returned list.
     * @return A list of RacePollDate objects that not reside in the past
     */
    public static function listDates() {
        global $acswuiDatabase;

        $rpds = array();
        $current_date = date("Y-m-d");

        $query = "SELECT Id FROM RacePollDates WHERE Date >= '$current_date' ORDER BY Date ASC";
        foreach ($acswuiDatabase->fetch_raw_select($query) as $row) {
            $rpds[] = new RacePollDate($row['Id']);
        }

        return $rpds;
    }

    /**
     * Creating a new RacePollDate in the database
     * @param $datestring The date in "yyyy-mm-dd HH:MM" format
     * @return The new RacePollDate object
     */
    public static function newDate(string $datestring) {
        global $acswuiDatabase;
        $id = $acswuiDatabase->insert_row("RacePollDates", ['Date'=>$datestring]);
        return new RacePollDate($id);
    }

    /**
     * Set the current user available/unavailable for this date
     * @param $avail TRUE when available, FALSE when not
     */
    public function setAvailable($avail) {
        global $acswuiDatabase;
        global $acswuiUser;

        // ensure db entry exist
        $map_id = NULL;
        $fields = array();
        $fields['Date'] = $this->Id;
        $fields['User'] = $acswuiUser->Id;
        $existing_maps = $acswuiDatabase->fetch_2d_array("RacePollDateMap", ['Id'], $fields);
        if (count($existing_maps) == 0) {
            $map_id = $acswuiDatabase->insert_row("RacePollDateMap", $fields);
        } else if (count($existing_maps) > 0) {
            $map_id = $existing_maps[0]['Id'];
        }

        // update availability
        $fields['Availability'] = ($avail === TRUE) ? 1 : -1;
        $acswuiDatabase->update_row("RacePollDateMap", $map_id, $fields);
    }

    private function updateAvailabilities() {
        global $acswuiDatabase;

        $this->AvailableUsers = array();
        $this->UnAvailableUsers = array();
        $res = $acswuiDatabase->fetch_2d_array("RacePollDateMap", ['User', 'Availability'], ['Date'=>$this->Id]);
        foreach ($res as $row) {
            if ($row['Availability'] > 0) {
                $this->AvailableUsers[] = $row['User'];
            } else if ($row['Availability'] < 0) {
                $this->UnAvailableUsers[] = $row['User'];
            }
        }
    }
}

?>
