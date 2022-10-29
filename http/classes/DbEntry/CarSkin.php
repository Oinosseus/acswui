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


    /**
     * Copies a file from the temporary upload directory into the htcache/owned_skins directory.
     *
     * Only '*.dds' files, 'livery.png' and 'preview.jpg' are accepted.
     *
     * @param $upload_path Path of the temporary upload location eg. $_FILES["xxx"]["tmp_name"]
     * @param $target_name The target filename eg. $_FILES["xxx"]["name"]
     * @return True on success
     */
    public function addUploadedFile(string $upload_path, string $target_name) : bool {

        // check for valid uploaded file (attack prevention)
        if (!is_uploaded_file($upload_path)) {
            \Core\Log::warning("Ignore not uploaded file '" . $target_name . "'!");
            return False;
        }

        // check filename
        $valid_filename = (bool) ((preg_match("`^[-0-9a-zA-Z_\.]+$`i", $target_name)) ? true : false);
        $valid_filename |= (bool) ((strlen($target_name) <= 225) ? true : false);
        if (!$valid_filename) {
            \Core\Log::warning("Ignore illegal filename '" . $target_name . "'!");
            return False;
        }

        // check file type
        $filetype_dds = (bool) ((preg_match("`^.*\.dds$`i", $target_name)) ? true : false);
        $filetype_livery = (bool) ($target_name == "livery.png") ? true : false;
        $filetype_preview = (bool) ($target_name == "preview.jpg") ? true : false;
        if (!$filetype_dds && !$filetype_livery && !$filetype_preview) {
            \Core\Log::warning("Ignore illegal filetype '" . $target_name . "'!");
            return False;
        }

        // create target directory
        $dst_dir = \Core\Config::AbsPathData . "/htcache/owned_skins/" . $this->id();
        if (!is_dir($dst_dir)) {
            if (!mkdir($dst_dir, 0775)) {
                \Core\Log::error("Cannot create directory '{$dst_dir}'!");
                return False;
            }
        }

        // move uploaded file
        $src = $_FILES["CarSkinFile"]["tmp_name"];
        $dst = $dst_dir . "/" . $target_name;
        if (!move_uploaded_file($src, $dst)) {
            \Core\Log::warning("File move-upload failed '" . $target_name . "'!");
            return False;
        }

        // if reached here, upload was successfull
        return True;
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
        $columns['Deprecated'] = 1;
        $columns['Number'] = 9999;
        $columns['Name'] = $owner->name();
        $columns['Owner'] = $owner->id();
        $id = \Core\Database::insert("CarSkins", $columns);

        // update skin path name
        $skin_path_name = "acswui_{$owner->id()}_{$id}_" . bin2hex(random_bytes(2));
        \Core\Database::update("CarSkins", $id, ["Skin"=>$skin_path_name]);

        return CarSkin::fromId($id);
    }


    /**
     * Deletes a previously uploadedfile from the skin
     * @param $file_name The target filename eg. "preview.png"
     * @return True on success
     */
    public function deleteUploadedFile(string $file_name) : bool {

        // security check
        $file_name = trim($file_name);
        $file_exists = False;
        foreach ($this->files() as $f) {
            if ($f === $file_name) {
                $file_exists = True;
                break;
            }
        }
        if (!$file_exists) {
            \Core\Log::warning("Prevent deleting non-existing file '$file_name'!");
            return False;
        }

        // delete
        $file_path = \Core\Config::AbsPathData . "/htcache/owned_skins/{$this->id()}/$file_name";
        return unlink($file_path);
    }


    //! @return TRUE when this skin is deprecated
    public function deprecated() {
        if ($this->Deprecated === NULL)
            $this->Deprecated = ($this->loadColumn('Deprecated') == 0) ? FALSE : TRUE;
        return $this->Deprecated;
    }


    //! @return An array with all filenames for owned skins
    public function files() : array {
        $files = array();
        $dir = \Core\Config::AbsPathData . "/htcache/owned_skins/" . $this->id();
        if (is_dir($dir)) {
            foreach (scandir($dir) as $f) {
                if (substr($f, 0, 1) == ".") continue;
                $files[] = $f;
            }
        }
        return $files;
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


    //! @return The returned information from the carskin registration
    public function registrationInfo() : string {
        if ($this->registrationStatus() == \Enums\CarSkinRegistrationStatus::Pending) {
            return _("waiting for registration ...");
        } else {
            return $this->loadColumn("RegistrationInfo");
        }
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
