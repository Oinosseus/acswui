<?php

namespace DbEntry;

/**
 * Cached wrapper to car databse CarBrands table element
 */
class CarBrand extends DbEntry {

    // local cache
    private $Model = NULL;
    private $Name = NULL;
    private $BadgeCar = NULL;

    private static $ListBrands = NULL;

    private $ListCars = NULL;


    /**
     * Construct a new object
     * @param $id Database table id
     */
    public function __construct(int $id) {
        parent::__construct("CarBrands", $id);
    }


    //! @return The car that represents this brand
    public function badgeCar() {
        if ($this->BadgeCar === NULL) {
            $car = Car::fromId($this->loadColumn('BadgeCar'));
            $this->BadgeCar = $car;
        }
        return $this->BadgeCar;
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("CarBrands", "CarBrand", $id);
    }


    /**
     * @param $inculde_deprecated If set to TRUE, also deprectaed items are listed (Default: False)
     * @return An array of all available car brands
     */
    public static function listBrands($inculde_deprecated=FALSE) {

        // update cache
        if (CarBrand::$ListBrands === NULL) {
            CarBrand::$ListBrands = array();
            $res = \Core\Database::fetch("CarBrands", ['Id'], [], "Name", TRUE);
            foreach ($res as $row) {
                CarBrand::$ListBrands[] = CarBrand::fromId($row['Id']);
            }
        }

        return CarBrand::$ListBrands;
    }


    //! @return A list of Car objects that belong to this brand
    public function listCars() {
        if ($this->ListCars === NULL) {
            $this->ListCars = array();
            $res = \Core\Database::fetch("Cars", ['Id'], ['Brand'=>$this->id()], 'Name');
            foreach ($res as $row) {
                $this->ListCars[] = Car::fromId($row['Id']);
            }
        }
        return $this->ListCars;
    }


    //! @return Html img tag containing preview image
    public function htmlImg() {

        $brand_name = $this->name();
        $brand_id = $this->id();
        $car = $this->badgeCar();
        $car_model = $car->model();
        $img_id = "CarBrand$brand_id";
        $path = \Core\Config::RelPathHtdata . "/content/cars/$car_model/ui/badge.png";

        $html = "<a class=\"CarBrandLink\" href=\"index.php?HtmlContent=CarBrand&Id=$brand_id\">";
        $html .= "<label for=\"$img_id\">$brand_name</label>";
        $html .= "<img src=\"$path\" id=\"$img_id\" alt=\"$brand_name\" title=\"$brand_name\">";
        $html .= "</a>";

        return $html;
    }


    //! @return User friendly name of the car
    public function name() {
        if ($this->Name === NULL) $this->Name = $this->loadColumn("Name");
        return $this->Name;
    }
}
