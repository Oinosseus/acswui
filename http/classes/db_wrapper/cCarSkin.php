<?php

/**
 * Cached wrapper to car databse CarSkins table element
 */
class CarSkin {

    private $Id = NULL;
    private $Car = NULL;
    private $Skin = NULL;

    /**
     * @param $id Database table id
     */
    public function __construct($id) {
        $this->Id = $id;
    }

    //! @return Id of the CarSkin
    public function id() {
        return $this->Id;
    }

    //! @return A Car object
    public function car() {
        if ($this->Car === NULL) $this->updateFromDb();
        return $this->Car;
    }

    //! @return Id of referring Car
    public function carId() {
        if ($this->Car === NULL) $this->updateFromDb();
        return $this->Car->id();
    }

    //! @return Html img tag containing preview image
    public function htmlImg($img_id="", $max_height=NULL) {
        global $acswuiConfig;

        $car_model = $this->car()->model();
        $car_name = $this->car()->name();
        $car_brand = $this->car()->brand();
        $skin = $this->skin();
        $img_id = ($img_id == "") ? "":"id=\"$img_id\"";
        $max_height = ($max_height === NULL) ? "" : "height=\"$max_height\"";

        $path = $acswuiConfig->AcsContent . "/content/cars/$car_model/skins/$skin/preview.jpg";

        return "<img src=\"$path\" $img_id alt=\"$car_model $skin\" title=\"Brand: $car_brand\nCar: $car_name\nSkin: $skin\" $max_height>";
    }

    //! @return Name of the skin
    public function skin() {
        if ($this->Skin === NULL) $this->updateFromDb();
        return $this->Skin;
    }

    private function updateFromDb() {
        global $acswuiDatabase;
        global $acswuiLog;

        // request from db
        $columns = array();
        $columns[] = 'Car';
        $columns[] = 'Skin';

        $res = $acswuiDatabase->fetch_2d_array("CarSkins", ['Car', 'Skin'], ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find CarSkins.Id=" . $this->Id);
            return;
        }

        $this->Car = new Car($res[0]['Car']);
        $this->Skin = $res[0]['Skin'];
    }
}

?>
