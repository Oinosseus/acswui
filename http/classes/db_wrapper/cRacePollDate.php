<?php

/**
 * Cached wrapper to RacePollDate database table element
 */
class RacePollDate {

    private $Id = NULL;
    private $Date = NULL;
    private $AvailableUsers = NULL;

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
     * Test if a certain user is available for this date
     * @param $user The requested User object
     * @return True if the user is available on this date (else false)
     */
    public function isAvailable(User $user) {
        if ($this->AvailableUsers === NULL) $this->updateAvailabilities();
        return (in_array($user->id(), $this->AvailableUsers)) ? TRUE : FALSE;
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

        $existing_maps = $acswuiDatabase->fetch_2d_array("RacePollDateMap", ['Id'], ['Date'=>$this->Id, 'User'=>$acswuiUser->Id]);

        // available
        if ($avail === TRUE) {
            if (count($existing_maps) == 0) {
                $acswuiDatabase->insert_row("RacePollDateMap", ['Date'=>$this->Id, 'User'=>$acswuiUser->Id]);
            }

        // unavailable
        } else {
            foreach ($existing_maps as $row) {
                $acswuiDatabase->delete_row("RacePollDateMap", $row['Id']);
            }
        }
    }

    private function updateAvailabilities() {
        global $acswuiDatabase;

        $this->AvailableUsers = array();
        $res = $acswuiDatabase->fetch_2d_array("RacePollDateMap", ['User'], ['Date'=>$this->Id]);
        foreach ($res as $row) {
            $this->AvailableUsers[] = $row['User'];
        }
    }
}

?>
