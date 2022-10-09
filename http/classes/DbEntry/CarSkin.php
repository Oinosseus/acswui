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


    /**
     * Creates a new car skin that is owned by a certain user
     * @param $car The car model the new skin shall be assigned to
     * @param $owner The User, that owns this car (skin)
     * @return The new created CarSkin object
     */
    public static function createNew(\DbEntry\Car $car,
                                      \DbEntry\User $owner) {
        $columns = array();
        $columns['Car'] = $car->id();
        // $columns['Skin'] = "???";
        $columns['Deprecated'] = 0;
        $columns['Number'] = 9999;
        $columns['Name'] = $owner->name();
        $columns['Owner'] = $owner->id();
        $id = \Core\Database::insert("CarSkins", $columns);

        // update skin path name
        $skin_path_name = "acswui_{$owner->id()}_$id";
        \Core\Database::update("CarSkins", $id, ["Skin"=>$skin_path_name]);

        return CarSkin::fromId($id);
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


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @param $car The destinated Car object
     * @param $skin The name of the content/cars/.../skin/$skin directory
     * @return A CarSkin object (or NULL)
     */
    public static function fromSkin(Car $car, string $skin) {
        $skin = \Core\Database::escape($skin);
        $query = "SELECT Id FROM CarSkins WHERE Car = '{$car->id()}' AND Skin = '$skin';";
        $res = \Core\Database::fetchRaw($query);
        if (count($res) == 0) {
            return NULL;
        } else if (count($res) == 1) {
            return parent::getCachedObject("CarSkins", "CarSkin", $res[0]['Id']);
        } else {
            \Core\Log::error("Ambigous CarSkin for Car.Id={$car->id()} and skin='$skin'!");
            return NULL;
        }
    }



    /**
     * @param $include_link Include a link
     * @param $show_label Include a label
     * @param $show_img Include a preview image
     * @return Html content for this object
     */
    public function html(bool $include_link = TRUE, bool $show_label = TRUE, bool $show_img = TRUE) {

        $skin_name = trim($this->name());
        if ($skin_name == "") $skin_name = "&nbsp;";

        $skin_id = $this->id();
        $preview_path = \Core\Config::RelPathHtdata . "/htmlimg/car_skins/$skin_id.png";
        $hover_path = \Core\Config::RelPathHtdata . "/htmlimg/car_skins/$skin_id.hover.png";

        $html = "";

        if ($show_label) $html .= "<label for=\"CarSkin$skin_id\">$skin_name</label>";

        if ($show_img) $html .= "<img class=\"HoverPreviewImage\" src=\"$preview_path\" id=\"CarSkin$skin_id\" alt=\"$skin_name\" title=\"{$this->car()->name()}\n$skin_name\">";

        if ($include_link) {
            $html = "<a href=\"index.php?HtmlContent=CarSkin&Id=$skin_id\">$html</a>";
        }

        $html = "<div class=\"DbEntryHtml\">$html</div>";
        return $html;
    }


    //! @return The display name of the skin
    public function name() {
        if ($this->Name === NULL) $this->Name = $this->loadColumn("Name");
        return $this->Name;
    }


    //! @return The start number of this skin
    public function number() {
        if ($this->Number === NULL) $this->Number = $this->loadColumn("Number");
        return $this->Number;
    }


    //! @return The owner of this CarSkin (can be NULL if not owned)
    public function owner() : ?\DbEntry\User {
        $uid = (int) $this->loadColumn("Owner");
        if ($uid <= 0) return NULL;  // user-0 is an invalid owner
        return \DbEntry\User::fromId($uid);
    }


    //! @return the path of the preview image
    public function previewPath() {
        $skin_skin = $this->skin();
        $car = $this->car();
        $car_model = $car->model();
        $path = \Core\Config::RelPathHtdata . "/content/cars/$car_model/skins/$skin_skin/preview.jpg";
        return $path;
    }


    //! @param $new_name Saves $new_name as new name of the carskin into the database
    public function saveName(string $new_name) {

        // sanitize
        $new_name = trim($new_name);
        $new_name = \Core\Database::escape($new_name);

        // save
        $this->storeColumns(['Name'=>$new_name]);
    }


    //! @param $new_number Saves $new_number as new number of the carskin into the database
    public function saveNumber(string $new_number) {

        // sanitize
        $new_number = trim($new_number);
        $new_number = \Core\Database::escape($new_number);

        // save
        $this->storeColumns(['Number'=>$new_number]);
    }


    //! @return Name of the skin
    public function skin() {
        if ($this->Skin === NULL) $this->Skin = $this->loadColumn("Skin");
        return $this->Skin;
    }
}
