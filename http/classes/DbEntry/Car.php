<?php

namespace DbEntry;

/**
 * Cached wrapper to car databse Cars table element
 */
class Car extends DbEntry {

    // local cache
    private $Model = NULL;
    private $Name = NULL;
    private $Brand = NULL;
    private $Skins = NULL;
    private $Deprecated = NULL;
    private $TorqueCurve = NULL;
    private $PowerCurve = NULL;
    private $Weight = NULL;


    /**
     * Construct a new object
     * @param $id Database table id
     */
    public function __construct(int $id) {
        parent::__construct("Cars", $id);
    }


    //! @return User friendly brand name of the car
    public function brand() {
        if ($this->Brand === NULL) {
            $this->Brand = CarBrand::fromId($this->loadColumn("Brand"));
        }
        return $this->Brand;
    }


    //! @return TRUE when this car is deprected
    public function deprecated() {
        if ($this->Deprecated === NULL)
            $this->Deprecated = ($this->loadColumn('Deprecated') == 0) ? FALSE : TRUE;
        return $this->Deprecated;
    }


    //! @return Description of the car_id
    public function description() {
        return $this->loadColumn("Description");
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("Cars", "Car", $id);
    }


    /**
     * @param $inculde_deprecated If set to TRUE, also deprectaed items are listed (Default: False)
     * @return An array of all available Car objects, ordered by name
     */
    public static function listCars($inculde_deprecated=FALSE) {

        // query db
        $where = array();
        if ($inculde_deprecated !== TRUE) $where['Deprecated'] = 0;
        $res = \core\Database::fetch("Cars", ["Id"], $where);

        // extract values
        $carlist = array();
        foreach ($res as $row) {
            $id = (int) $row['Id'];
            $carlist[] = Car::fromId($id);
        }

        return $carlist;
    }


    //! @return Html img tag containing preview image
    public function htmlImg() {

        $car_id = $this->id();
        $car_name = $this->name();
        $car_model = $this->model();
        $img_id = "CarModel$car_id";

        // try to find first available CarSkin
        $skins = $this->skins();
        $path = NULL;
        if (count($skins)) {
            $skin = $skins[0]->skin();
            $path = \Core\Config::RelPathHtdata . "/content/cars/$car_model/skins/$skin/preview.jpg";
        }

        $html = "<a class=\"CarModelLink\" href=\"index.php?HtmlContent=CarModel&Id=$car_id\">";
        $html .= "<label for=\"$img_id\">$car_name</label>";
        if ($path !== NULL) {
            $html .= "<img src=\"$path\" id=\"$img_id\" alt=\"$car_name\" title=\"$car_name\">";
        } else {
            $html .= "<br>No skin for " . $this . " found!<br>";
        }
        $html .= "</a>";

        return $html;
    }



    //! @return model name of the car
    public function model() {
        if ($this->Model === NULL) $this->Model = $this->loadColumn("Car");
        return $this->Model;
    }


    //! @return User friendly name of the car
    public function name() {
        if ($this->Name === NULL) $this->Name = $this->loadColumn("Name");
        return $this->Name;
    }


    //! @return Array of (revolution, power) value pairs
    public function powerCurve() {
        if ($this->PowerCurve === NULL) {
            $this->PowerCurve = json_decode($this->loadColumn("PowerCurve"));
        }

        return $this->PowerCurve;
    }


    /**
     * @param $inculde_deprecated If set to TRUE, also deprectaed skins are listed (Default: False)
     * @return A List of according CarSkin objects
     */
    public function skins($inculde_deprecated=FALSE) {

        // update cache
        if ($this->Skins == NULL) {

            $query = "SELECT Id FROM CarSkins WHERE Car = " . $this->id();

            $res = \core\Database::fetchRaw($query);

            // extract values
            $this->Skins = array();
            foreach ($res as $row) {
                $id = (int) $row['Id'];
                $this->Skins[] = CarSkin::fromId($id);
            }
        }

        return $this->Skins;
    }


    //! @return Array of (revolution, torque) value pairs
    public function torqueCurve() {
        if ($this->TorqueCurve === NULL) {
            $this->TorqueCurve = json_decode($this->loadColumn("TorqueCurve"));
        }

        return $this->TorqueCurve;
    }


    //! @return The weight of the car in kg
    public function weight() {
        if ($this->Weight === NULL) {
            $this->Weight = (int) $this->loadColumn("Weight");
        }
        return $this->Weight;
    }

}
