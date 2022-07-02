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

    private $ListCarsNoDeprecated = NULL;
    private $ListCarsAll = NULL;


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


    /**
     * @param $include_deprecated If TRUE, also deprecated cars will be listed
     * @return A list of Car objects that belong to this brand
     * @todo
     */
    public function listCars($include_deprecated = FALSE) {
        if ($this->ListCarsAll === NULL) {
            $this->ListCarsAll = array();
            $this->ListCarsNoDeprecated = array();
            $res = \Core\Database::fetch("Cars", ['Id'], ['Brand'=>$this->id()], 'Name');
            foreach ($res as $row) {
                $car = Car::fromId($row['Id']);
                $this->ListCarsAll[] = $car;
                if (!$car->deprecated()) $this->ListCarsNoDeprecated[] = $car;
            }
        }
        return ($include_deprecated) ? $this->ListCarsAll : $this->ListCarsNoDeprecated;
    }


    /**
     * @param $include_link Include a link
     * @param $show_label Include a label
     * @param $show_img Include a preview image
     * @return Html content for this object
     */
    public function html(bool $include_link = TRUE, bool $show_label = TRUE, bool $show_img = TRUE) {

        $brand_name = $this->name();
        $brand_id = $this->id();
        $car = $this->badgeCar();
        $car_model = $car->model();
        $img_id = "CarBrand$brand_id";
        $path = \Core\Config::RelPathHtdata . "/content/cars/$car_model/ui/badge.png";

        $html = "";

        if ($show_label) $html .= "<label for=\"$img_id\">$brand_name</label>";
        if ($show_img) $html .= "<img src=\"$path\" id=\"$img_id\" alt=\"$brand_name\" title=\"$brand_name\">";
        if ($include_link) $html = "<a href=\"index.php?HtmlContent=CarBrand&Id=$brand_id\">$html</a>";

        $html = "<div class=\"DbEntryHtml\">$html</div>";
        return $html;
    }


    //! @return User friendly name of the car
    public function name() {
        if ($this->Name === NULL) $this->Name = $this->loadColumn("Name");
        return $this->Name;
    }
}
