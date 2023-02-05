<?php

namespace DbEntry;


/**
 * Cached wrapper to car databse CarSkins table element
 */
class CarSkinRegistration extends DbEntry {


    /**
     * Construct a new object
     * @param $id Database table id
     */
    public function __construct(int $id) {
        parent::__construct("CarSkinRegistrations", $id);
    }



    /**
     * Calculates a factor whcih can be applied to width and height to fit into a maximum width/heigt
     *
     * @param $width
     * @param $height
     * @param $max_width
     * @param $max_height
     * @param $overlap Set to FALSE (default) if the image shall fit completely, TRUE to fit shortest edge
     * @return The factor to multiply width and height so that they fit into max width/height (can be greater than 1.0)
     */
    private function calculateImageRescaleFactor(int $width, int $height,
                                                 int $max_width, int $max_height,
                                                 bool $overlap = FALSE) : float {
        $width_factor = $max_width / $width;
        $height_factor = $max_height / $height;
        $compare = ($overlap) ? ($width_factor > $height_factor) : ($width_factor < $height_factor);
        $factor = ($compare) ? $width_factor : $height_factor;
        return $factor;
    }


    //! @return The according CarSkin object
    public function carSkin() : CarSkin {
        return CarSkin::fromId($this->loadColumn('CarSkin'));
    }


    /**
     * Clears the registration temp directory and returns the path to the directory
     * This directory is intended for preparing to package the skin
     * @return The path to the registration temp directory
     */
    private function cleanRegistrationTemp() : string {

    }


    /**
     * Request a new registration for a certain CarSkin
     * @param $carskin The CarSkin for which the registration is requested
     * @return A new CarSkinRegistration object or NULL on failure
     */
    public static function create(\DbEntry\CarSkin $carskin) : ?CarSkinRegistration {

        // check for owned CarSkin
        if ($carskin->owner() === NULL) {
            \Core\Log::error("Cannot register {$carskin} because it has no owner!");
            return NULL;
        }

        // check for pending registrations
        $csr = CarSkinRegistration::fromCarSkinPending($carskin);
        if ($csr !== NULL) {
            \Core\Log::warning("Ignore registration for {$carskin} because registrations are pendingr!");
            return NULL;
        }

        // create new registration
        $fields = array();
        $fields['CarSkin'] = $carskin->id();
        $fields['Requested'] = \Core\Database::timestamp(new \DateTime("now"));
        $id = \Core\Database::insert("CarSkinRegistrations", $fields);
        return CarSkinRegistration::fromId($id);
    }



    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) : ?CarSkinRegistration {
        return parent::getCachedObject("CarSkinRegistrations", "CarSkinRegistration", $id);
    }



    /**
     * Get the last available registration for a certain CarSkin
     * @return A CarSkinRegistration object or NULL
     */
    public static function fromCarSkinLatest(CarSkin $carskin) : ?CarSkinRegistration {
        $query = "SELECT Id FROM CarSkinRegistrations WHERE CarSkin = {$carskin->id()} ORDER BY Id DESC LIMIT 1;";
        $res = \Core\Database::fetchRaw($query);
        if (count($res) == 0) return NULL;
        else return CarSkinRegistration::fromId($res[0]['Id']);
    }



    /**
     * Find current pending registration for a certain carskin
     * @param $carskin The requested CarSkin
     * @return A CarSkinRegistration object or NULL if no registration is pending
     */
    public static function fromCarSkinPending(\DbEntry\CarSkin $carskin) : ?CarSkinRegistration {
        $query = "SELECT Id FROM CarSkinRegistrations WHERE CarSkin = {$carskin->id()} AND Processed < Requested ORDER BY Id DESC;";
        $res = \Core\Database::fetchRaw($query);
        if (count($res) == 0) return NULL;
        return CarSkinRegistration::fromId($res[0]['Id']);
    }



    //! @return Information about the registration processing
    public function info() : string {
        return $this->loadColumn("Info");
    }


    //! @return The CarSkinRegistration object that should be processed next (can be NULL)
    public static function nextRegistration2BProcessed() : ?CarSkinRegistration {
        $res = \Core\Database::fetchRaw("SELECT Id FROM CarSkinRegistrations WHERE Processed < Requested ORDER BY Id ASC LIMIT 1;");
        if (count($res) == 0) return NULL;
        return CarSkinRegistration::fromId($res[0]['Id']);
    }


    /**
     * @param $existing_only If TRUE (default) NULL will be returned, if the packaged file does not exist.
     * @return The full qualified file path to the packaged skin
     */
    public function packagedDownloadLink($existing_only = TRUE) {
        $path = \Core\Config::RelPathHtdata . "/owned_carskin_packages/{$this->packagedFileName()}";
        if (!$existing_only) return $path;
        return (is_file($path)) ? $path : NULL;
    }


    //! @return The name of the skin package
    public function packagedFileName() : string {
        return "{$this->carSkin()->skin()}_{$this->id()}.7z";
    }


    /**
     * @param $existing_only If TRUE (default) NULL will be returned, if the packaged file does not exist.
     * @return The full qualified file path to the packaged skin
     */
    public function packagedFilePath($existing_only = TRUE) {
        $path = \Core\Config::AbsPathHtdata . "/owned_carskin_packages/{$this->packagedFileName()}";
        if (!$existing_only) return $path;
        return (is_file($path)) ? $path : NULL;
    }


    //! @return A DateTime object of when the registration has been processed
    public function processed() : \DateTime {
        $t = $this->loadColumn("Processed");
        return new \DateTime($t);
    }


    /**
     * Executes that registration of the CarSkin
     * @return TRUE on success, else FALSE
     */
    public function processRegistration() : bool {

        // delete old data
        $old_skin_dir = \Core\Config::AbsPathHtdata . "/content/cars/" . $this->carSkin()->car()->model() . "/skins/" . $this->carSkin()->skin();
        \Core\Helper::rmdirs($old_skin_dir);

        // update skin name (for ac-server-wrapper, to recognize updated skins)
        $new_skin_name = "acswui_{$this->carSkin()->owner()->id()}_{$this->carSkin()->id()}_{$this->id()}";
        $this->carSkin()->setSkin($new_skin_name);

        // define registration as processed
        $this->storeColumns(['Processed'=>\Core\Database::timestamp(new \DateTime("now"))]);
        $this->storeColumns(['Info'=>""]);

        // check CarSkin
        $cs = $this->carSkin();
        if ($cs === NULL) {
            \Core\Log::error("Cannot find $cs!");
            $this->processRegistrationAddInfo(_("Registration failed because of internal error.") . "\n");
            return FALSE;
        }

        // source directory with files from owner
        $skin_src_dir =\Core\Config::AbsPathData . "/htcache/owned_skins/" . $cs->id();

        // ensure content destination does exist
        $skin_dst_dir = \Core\Config::AbsPathHtdata . "/content/cars/" . $cs->car()->model() . "/skins/" . $cs->skin();
        if (!is_dir($skin_dst_dir)) {
            mkdir ($skin_dst_dir, 0775, FALSE);
        }
        if (!is_dir($skin_dst_dir)) {
            \Core\Log::error("Cannot create directory '$skin_dst_dir'!");
            $this->processRegistrationAddInfo(_("Registration failed because of internal error.") . "\n");
            return FALSE;
        }

        // copy preview
        $skin_preview_src_file = $skin_src_dir . "/preview.jpg";
        $skin_preview_dst_file = $skin_dst_dir . "/preview.jpg";
        if ($this->processRegistrationCopyPreview($skin_preview_src_file, $skin_preview_dst_file) !== TRUE) {
            return FALSE;
        }

        // create html images
        if ($this->processRegistrationHtmlImgs($skin_preview_src_file) !== TRUE) {
            return FALSE;
        }

        // clean temp dir
        // clear before operation, this enables debugging afterwards
        if ($this->processRegistrationClearTempDir() !== TRUE) {
            return FALSE;
        }

        // delete existing packages
        $package_html_dir = \Core\Config::AbsPathHtdata . "/owned_carskin_packages";
        $regex_pattern = "#{$this->carSkin()->skin()}.*\.7z#";
        foreach (scandir($package_html_dir) as $f) {
            if (preg_match($regex_pattern, $f) !== 1) continue;
            if (unlink("$package_html_dir/$f") !== TRUE) {
                \Core\Log::error("Cannot delete '$package_html_dir/$f'!");
                $this->processRegistrationAddInfo(_("Registration failed because of internal error.") . "\n");
                return FALSE;
            }
        }

        // create package
        if ($this->processRegistrationCreatePackage() !== TRUE) {
            return FALSE;
        }


        // set to not-deprecated
        $this->carSkin()->setDeprecated(FALSE);


        $this->processRegistrationAddInfo(_("Car successfully registered\n"));
        return TRUE;
    }


    private function processRegistrationAddInfo(string $info) {
        $current_info = $this->loadColumn("Info");
        $current_info .= $info;
        $this->storeColumns(['Info'=>$current_info]);
    }


    private function processRegistrationClearTempDir() : bool {
        $package_temp_dir = \Core\Config::AbsPathData . "/htcache/owned_skin_registration_temp";
        \Core\Helper::cleandir($package_temp_dir);
        return TRUE;
    }


    private function processRegistrationCopyPreview($skin_preview_src_file, $skin_preview_dst_file) : bool {

        // check if source exists
        if (!is_file($skin_preview_src_file)) {
            \Core\Log::warning("File not found '$skin_preview_src_file'!");
            $this->processRegistrationAddInfo(_("Registration failed because 'preview.jpg' not found.") . "\n");
            return FALSE;
        }

        // check if file is actually a jpeg image
        if (imagecreatefromjpeg($skin_preview_src_file) === FALSE) {
            $this->processRegistrationAddInfo(_("Registration failed because 'preview.jpg' cannot be read.") . "\n");
            return FALSE;
        }

        // check filesize
        if (filesize($skin_preview_src_file) > 2**20) {
            $this->processRegistrationAddInfo(_("Registration failed because size of 'preview.jpg' is greater than 1MiB") . "\n");
            return FALSE;
        }

        // acutal copy of preview
        if (!copy($skin_preview_src_file, $skin_preview_dst_file)) {
            \Core\Log::error("Failed to copy '$skin_preview_src_file' to '$skin_preview_dst_file'");
            $this->processRegistrationAddInfo(_("Registration failed because of internal error") . "\n");
            return FALSE;
        }

        return TRUE;
    }



    private function processRegistrationCreatePackage() : bool {
        $cs = $this->carSkin();

        // create directories
        $package_temp_dir = \Core\Config::AbsPathData . "/htcache/owned_skin_registration_temp";
        $package_temp_dir_skin = $package_temp_dir . "/content/cars/{$cs->car()->model()}/skins/{$cs->skin()}";
        if (mkdir($package_temp_dir_skin, 0775, TRUE) !== TRUE) {
            \Core\Log::error("Could not create directory '$package_temp_dir_skin'!");
            $this->processRegistrationAddInfo(_("Registration failed because of internal error") . "\n");
            return FALSE;
        }

        // create ui_skin.json
        $ui_skin = array();
        $ui_skin['skinname'] = $cs->name();
        if ($cs->owner() === NULL) {
            \Core\Log::error("Owner is NULL for {$cs}");
            $this->processRegistrationAddInfo(_("Registration failed because of internal error") . "\n");
            return FALSE;
        }
        $ui_skin['drivername'] = $cs->owner()->name();
        $country_key = $cs->owner()->getParam("UserCountry");
        try {
            $country_name = \Core\Config::Countries[$country_key];
        } catch (\Exception $e) {
            \Core\Log::warning("Country key '$country_key' is not known");
            $country_name = "";
        }
        $ui_skin['country'] = $country_name;
        //! @todo Retrieve tem name here, when new teams system is implemented
        $ui_skin['team'] = "";
        $ui_skin['number'] = $cs->number();
        $fd = fopen($package_temp_dir_skin . "/ui_skin.json", "w");
        fwrite($fd, json_encode($ui_skin, JSON_PRETTY_PRINT));
        fwrite($fd, "\n");
        fclose($fd);
        chmod($package_temp_dir_skin . "/ui_skin.json", 0660);

        // copy files
        $skin_source_dir = \Core\Config::AbsPathData . "/htcache/owned_skins/{$cs->id()}";
        foreach ($cs->files() as $f) {
            $src = "{$skin_source_dir}/{$f}";
            $dst = "{$package_temp_dir_skin}/{$f}";
            copy($src, $dst);
        }

        // pack
        $package_file_name = $this->packagedFileName();
        $cmd = "7z a \"$package_temp_dir/$package_file_name\" \"$package_temp_dir/*\"";
        $cmd_output = array();
        $cmd_return = 0;
        if (exec($cmd, $cmd_output, $cmd_return) === FALSE) {
            \Core\Log::error("Could not execute '$cmd' return:\n" . implode("\n", $cmd_output));
            $this->processRegistrationAddInfo(_("Registration failed because of internal error") . "\n");
            return FALSE;
        }

        // move packaged file
        $src = $package_temp_dir . "/" . $package_file_name;
        $dst = $this->packagedFilePath(FALSE);
        if (rename($src, $dst) !== TRUE) {
            \Core\Log::error("Could not move '$src' to '$dst'");
            $this->processRegistrationAddInfo(_("Registration failed because of internal error") . "\n");
            return FALSE;
        }


        return TRUE;
    }



    private function processRegistrationHtmlImgs($skin_preview_src_file) : bool {
        $width_target = 300;
        $height_target = 200;

        // load source image
        $img_src = imagecreatefromjpeg($skin_preview_src_file);

        // create no-hover image
        $img = imagecreatetruecolor($width_target, $height_target);
        if ($img === FALSE) {
            \Core\Log::error("Failed to create image");
            $this->processRegistrationAddInfo(_("Registration failed because of internal error") . "\n");
            return FALSE;
        }
        imagesavealpha($img, TRUE);
        $trans_colour = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $trans_colour);

        // copy source into no-hover-image
        $width_src = imagesx($img_src);
        $height_src = imagesy($img_src);
        $scale = $this->calculateImageRescaleFactor($width_src, $height_src,
                                                    $width_target, $height_target,
                                                    TRUE);
        $width_dst = (int) ($width_src * $scale);
        $height_dst = (int) ($height_src * $scale);
        $dst_x = intdiv($width_target - $width_dst, 2);
        $dst_y = intdiv($height_target - $height_dst, 2);
        $succ = imagecopyresampled($img, $img_src,
                                   $dst_x, $dst_y,
                                   0, 0,
                                   $width_dst, $height_dst,
                                   $width_src, $height_src);
        if ($succ !== TRUE) {
            \Core\Log::error("Failed to copy image");
            $this->processRegistrationAddInfo(_("Registration failed because of internal error") . "\n");
            return FALSE;
        }

        // save no-hover-image
        $dst = \Core\Config::AbsPathHtdata . "/htmlimg/car_skins/{$this->carSkin()->id()}.png";
        $succ = imagepng($img, $dst);
        if ($succ !== TRUE) {
            \Core\Log::error("Failed to save image '$dst'");
            $this->processRegistrationAddInfo(_("Registration failed because of internal error") . "\n");
            return FALSE;
        }

        // load brand badge and merge to image
        $brand_batch_file = \Core\Config::AbsPathHtdata . "/content/cars/{$this->carSkin()->car()->model()}/ui/badge.png";
        if (!is_file($brand_batch_file)) {
            \Core\Log::debug("Cannot finde badge '$brand_batch_file'!");
        } else {
            $img_badge = imagecreatefrompng($brand_batch_file);

            // copy badge into no-hover-image
            $width_src = imagesx($img_badge);
            $height_src = imagesy($img_badge);
            $scale = $this->calculateImageRescaleFactor($width_src, $height_src,
                                                        floor($width_target / 2), floor($height_target / 1.75),
                                                        FALSE);
            $width_dst = (int) ($width_src * $scale);
            $height_dst = (int) ($height_src * $scale);
            $dst_x = intdiv($width_target - $width_dst, 2);
            $dst_y = intdiv($height_target - $height_dst, 2);
            $succ = imagecopyresampled($img, $img_badge,
                                    $dst_x, $dst_y,
                                    0, 0,
                                    $width_dst, $height_dst,
                                    $width_src, $height_src);
            if ($succ !== TRUE) {
                \Core\Log::error("Failed to copy image");
            }
        }

        // save hover-image
        $dst = \Core\Config::AbsPathHtdata . "/htmlimg/car_skins/{$this->carSkin()->id()}.hover.png";
        $succ = imagepng($img, $dst);
        if ($succ !== TRUE) {
            \Core\Log::error("Failed to save image '$dst'");
        }


        return TRUE;
    }


    //! @return A DateTime object of when the registration has been requested
    public function requested() : \DateTime {
        $t = $this->loadColumn("Requested");
        return new \DateTime($t);
    }
}
