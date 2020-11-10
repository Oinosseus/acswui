<?php

/**
 * Cached wrapper to car databse CarSkins table element
 */
class CarSkin {

    private $Id = NULL;
    private $Car = NULL;
    private $Skin = NULL;

    /**
     * @param $car_id Database table id
     */
    public function __construct($car_skin_id) {
        global $acswuiLog;
        global $acswuiDatabase;

        $this->Id = $car_skin_id;

        // get basic information
        $res = $acswuiDatabase->fetch_2d_array("CarSkins", ['Car', 'Skin'], ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find CarSkins.Id=" . $this->Id);
            return;
        }

        $this->Car = $res[0]['Car'];
        $this->Skin = $res[0]['Skin'];
    }

    //! @return Id of the CarSkin
    public function id() {
        return $this->Id;
    }

    //! @return Id of referring Car
    public function carId() {
        return $this->Car;
    }

    //! @return Name of the skin
    public function skin() {
        return $this->Skin;
    }

}

?>
