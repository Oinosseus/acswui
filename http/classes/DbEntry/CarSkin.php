<?php

namespace DbEntry;

/**
 * Cached wrapper to car databse CarSkins table element
 */
class CarSkin extends DbEntry {

    private $Car = NULL;
    private $Skin = NULL;
    private $Name = NULL;
    private $Number = NULL;
    private $Steam64GUID = NULL;
    private $Deprecated = NULL;


    /**
     * Construct a new object
     * @param $id Database table id
     */
    public function __construct(int $id) {
        parent::__construct("CarSkins", $id);
    }


    //! @return The according Car object
    public function car() {
        if ($this->Car === NULL) {
            $car_id = $this->loadColumn("Car");
            $this->Car = Car::fromId($car_id);
        }
        return $this->Car;
    }


    //! @return TRUE when this skin is deprecated
    public function deprecated() {
        if ($this->Deprecated === NULL)
            $this->Deprecated = ($this->loadColumn('Deprecated') == 0) ? FALSE : TRUE;
        return $this->Deprecated;
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("CarSkins", "CarSkin", $id);
    }


    //! @return Html img tag containing preview image
    public function htmlImg() {

        $skin_name = $this->name();
        $skin_id = $this->id();
        $path = $this->previewPath();

        $html = "<a class=\"CarSkinLink\" href=\"index.php?HtmlContent=CarSkin&Id=$skin_id\">";
        $html .= "<label for=\"CarSkin$skin_id\">$skin_name</label>";
        if ($path !== NULL) {
            $html .= "<img src=\"$path\" id=\"CarSkin$skin_id\" alt=\"$skin_name\" title=\"Skin: $skin_name\">";
        } else {
            $html .= "<br>" . $this . " found!<br>";
        }
        $html .= "</a>";

        return $html;
    }


    //! @return The display name of the skin
    public function name() {
        if ($this->Name === NULL) $this->Name = $this->loadColumn("Name");
        return $this->Name;
    }


    //! @return The start number of this skin
    public function number() {
        if ($this->Number === NULL) $this->Number = (int) $this->loadColumn("Number");
        return $this->Number;
    }


    //! @return the path of the preview image
    public function previewPath() {
        $skin_skin = $this->skin();
        $car = $this->car();
        $car_model = $car->model();
        $path = \Core\Config::RelPathHtdata . "/content/cars/$car_model/skins/$skin_skin/preview.jpg";
        return $path;
    }


    //! @return Name of the skin
    public function skin() {
        if ($this->Skin === NULL) $this->Skin = $this->loadColumn("Skin");
        return $this->Skin;
    }



    //! @return If this skin is prevered this returns for which Steam64GUID it is preserved (otherwise "")
    public function steam64GUID() {
        if ($this->Steam64GUID === NULL) $this->Steam64GUID = $this->loadColumn("Steam64GUID");
        return $this->Steam64GUID;
    }


    //! @return Team name of the skin
    public function team() {
        return $this->loadColumn("Team");
    }

}
