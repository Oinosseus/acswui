<?php

/**
 * Cached wrapper to RacePollCarClasses database table element
 */
class RacePollCarClass {

    private $Id = NULL;
    private $CarClass = NULL;
    private $Score = NULL;
    private $ScoreOverall = NULL;

    public function __construct(int $id) {
        $this->Id = (int) $id;
    }
    
    public function id() {
        return $this->Id;
    }
    
    //! @return The car class object scoped for this vote
    public function carClass() {
        global $acswuiDatabase;
        global $acswuiLog;

        if ($this->CarClass !== NULL) return $this->CarClass;
        
        $res = $acswuiDatabase->fetch_2d_array("RacePollCarClasses", ["CarClass"], ['Id'=>$this->Id]);
        if (count($res) == 0) {
            $acswuiLog->logWarning("Cannot find databse entry for Id=" . $this->Id);
            return;
        }
        $this->CarClass = new CarClass($res[0]['CarClass']);
        
        return $this->CarClass;
    }
    
    //! @return The current score value
    public function score() {
        global $acswuiDatabase;
        global $acswuiLog;
        
        if ($this->Score !== NULL) return $this->Score;
        
        $res = $acswuiDatabase->fetch_2d_array("RacePollCarClasses", ["Score"], ['Id'=>$this->Id]);
        if (count($res) == 0) {
            $acswuiLog->logWarning("Cannot find databse entry for Id=" . $this->Id);
            return;
        }
        $this->Score = $res[0]['Score'];
        
        
        return $this->Score;
    }
    
    //! @return The score cummulated over all current votes for the same car class
    public function scoreOverall() {
        global $acswuiDatabase;
        global $acswuiLog;

        if ($this->ScoreOverall !== NULL) return $this->ScoreOverall;
        
        $cc = $this->carClass();
        
        // determine score
        $this->ScoreOverall = 0;
        $count_votes = 0;        
        $res = $acswuiDatabase->fetch_2d_array("RacePollCarClasses", ["Score"], ['Id'=>$this->Id]);
        foreach ($res as $row) {
            $count_votes += 1;
            $this->ScoreOverall += (int) $row['Score'];
        }
        $this->ScoreOverall /= $count_votes;
        
        return $this->ScoreOverall;
    }
    
    /**
     * Set a new score value.
     * The new value is clipped to the range [0, 100]
     * @param $new_score A value between 0 and 100
     */
    public function setScore(int $new_score) {
        global $acswuiDatabase;    
    
        // clipping
        if ($new_score == $this->score()) return;
        if ($new_score < 0) $new_score = 0;
        if ($new_score > 100) $new_score = 100;
        
        // update DB
        $acswuiDatabase->update_row("RacePollCarClasses", $this->Id, ['Score'=>$new_score]);
        $this->ScoreOverall = NULL;
        $this->Score = $new_score;
    }

    /**
     * Constructing a RacePollCarClass object for the currently logged user and a given car class.
     * When no current user is not logged, NULL is returned.
     * When no database entry for the current user/carclass exists, it is created.
     * @param $carclass The requested car class
     */
    public static function fromCarClass(CarClass $carclass) {
        global $acswuiUser;
        global $acswuiDatabase;
    
        // check User
        if (!$acswuiUser->IsLogged) return NULL;
        
        // request entry from db
        $where = array();
        $where['User'] = $acswuiUser->Id;
        $where['CarClass'] = $carclass->id();
        $res = $acswuiDatabase->fetch_2d_array("RacePollCarClasses", ['Id'], $where);
        if (count($res) > 0) return new RacePollCarClass($res[0]['Id']);
        
        // create new entry
        $where['Score'] = 0;
        $id = $acswuiDatabase->insert_row("RacePollCarClasses", $where);
        return new RacePollCarClass($id);
    }

}

?>
