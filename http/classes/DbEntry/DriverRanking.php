<?php
namespace DbEntry;

//! Cached wrapper to database DriverRanking table element
class DriverRanking extends DbEntry {

    private $RankingPoints = NULL;
    private $PointsIncrease = NULL;
    private $Timestamp = NULL;

    //! @param $id Database table id
    protected function __construct($id) {
        parent::__construct("DriverRanking", $id);

        if ($id === NULL) {
            $this->RankingPoints = new \Core\DriverRankingPoints();
        } else {
            $this->RankingPoints = new \Core\DriverRankingPoints($this->loadColumn('RankingData'));
        }
    }


    /**
     * Compares to objects for better ranking points.
     * The ranking group is ignored
     * This is intended for usort() of arrays with Lap objects
     * @param $rnk1 DriverRanking object
     * @param $rnk2 DriverRanking object
     * @return 1 if $rnk1 is higher, -1 when $rnk2 is high, 0 if both are equal
     */
    public static function compareRankingPoints(DriverRanking $rnk1, DriverRanking $rnk2) {
        if ($rnk1->points() < $rnk2->points()) return 1;
        if ($rnk1->points() > $rnk2->points()) return -1;
        return 0;
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) : ?DriverRanking {
        return parent::getCachedObject("DriverRanking", "DriverRanking", $id);
    }


    public static function fromUserLatest(User $user) : ?DriverRanking {
        $query = "SELECT Id FROM DriverRanking WHERE User={$user->id()} ORDER BY Id DESC LIMIT 1;";
        $res = \Core\Database::fetchRaw($query);
        if (count($res) > 0) {
            $id = (int) $res[0]['Id'];
            return self::fromId($id);
        }
        return NULL;
    }


    /**
     * Returns the minimum required ranking-points threshold of a certain group
     * If a driver is below this threshold he will be down rated,
     * if he is above he will be promoted
     * @param $group The requested ranking group
     * @return The points threshold
     */
    public static function groupThreshold(int $group) : ?float {
        return \Core\DriverRankingPoints::groupThreshold($group);
    }


    /**
     * Returns the amount of ranking points.
     * Forwards to \Core\DriverRankingPoints::points()
     * @param grp If group is NULL, the sum of all points is returned.
     * @param $key If key is NULL, the sum of the group is returned.
     * @todo reviewed
     */
    public function points($grp=NULL, $key=NULL) {
        return $this->RankingPoints->points($grp, $key);
    }


    //! @return A DateTime object from when this driver ranking was updated
    public function timestamp() {

        // update cache
        if ($this->Timestamp === NULL) {

            if ($this->id() === NULL) {
                /* when the latest ranking is loaded from file cache,
                   the timestamp should already be set to the file-modified-time.
                   Otherwise a database entry with valid Id is expected. */
                \Core\Log::debug("Did not expect to end here");
                $this->Timestamp = new \DateTime("now");

            } else {
                $this->Timestamp = new \DateTime($this->loadColumn("Timestamp"));
            }
        }

        return $this->Timestamp;
    }


    //! @return The related User object
    public function user() {
        return User::fromId((int) $this->loadColumn("User"));
    }
}
