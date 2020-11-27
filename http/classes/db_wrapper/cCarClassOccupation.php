<?php

/**
 * Cached wrapper to CarClassOccupationMap table
 */
class CarClassOccupation {

    private $Id = NULL;
    private $CarSkin = NULL;
    private $User = NULL;
    private $CarClass = NULL;

    /**
     * @param $id Database table id
     * @param $carclass Optional carclass object to save db access
     */
    public function __construct(int $id, CarClass $carclass=NULL) {
        $this->Id = (int) $id;
        $this->CarClass = $carclass;
    }

    public function __toString() {
        return "CarClassOccupation(Id=" . $this->Id . ")";
    }

    public function __debugInfo() {
        return ["Id"=>$this->Id];
    }

    private function cacheUpdate() {
        global $acswuiDatabase;
        global $acswuiLog;

        $res = $acswuiDatabase->fetch_2d_array("CarClassOccupationMap", ['CarClass', 'User', 'CarSkin'], ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find CarClassOccupationMap.Id=" . $this->Id);
            return;
        }

        $this->CarSkin = new CarSkin($res[0]['CarSkin']);
        $this->User = new User($res[0]['User']);
        $this->CarClass = new CarClass($res[0]['CarClass']);
    }

    public function class() {
        if ($this->CarClass === NULL) $this->cacheUpdate();
        return $this->CarClass;
    }

    public function skin() {
        if ($this->CarSkin === NULL) $this->cacheUpdate();
        return $this->CarSkin;
    }

    public function user() {
        if ($this->User === NULL) $this->cacheUpdate();
        return $this->User;
    }
}

?>
