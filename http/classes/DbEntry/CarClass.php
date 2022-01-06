<?php

namespace DbEntry;

/**
 * Cached wrapper to databse CarClasses table element
 */
class CarClass extends DbEntry {

//     private $Name = NULL;
    private $Cars = NULL;
//     private $Description = NULL;

    private $BallastMap = NULL;
    private $RestrictorMap = NULL;
//     private $OccupationMap = NULL;

    /**
     * Construct a new object
     * @param $id Database table id
     */
    public function __construct(int $id) {
        parent::__construct("CarClasses", $id);
    }


    /**
     * Add a new car to the car class
     * @param $car The new Car object
     */
    public function addCar(Car $car) {

        $res = \Core\Database::fetch("CarClassesMap", ['Id'], ['Car'=>$car->id(), 'CarClass'=>$this->id()]);
        if (count($res) !== 0) {
            \Core\Log::warning("Ignoring adding existing car map Car::Id=" . $car->id() . ", CarClass::Id=" . $this->id());
            return;
        }
        \Core\Database::insert("CarClassesMap", ['Car'=>$car->id(), 'CarClass'=>$this->id()]);

        if ($this->Cars !== NULL) $this->Cars[] = $car;
    }


    /**
     * @param $car The requeted Car object
     * @return The necessary ballast for a certain car in the class
     */
    public function ballast($car) {

        if ($this->BallastMap === NULL || !array_key_exists($car->id(), $this->BallastMap)) {

            $this->BallastMap = array();
            foreach (\Core\Database::fetch("CarClassesMap", ['Car', 'Ballast'], ['CarClass'=>$this->id()]) as $row) {
                $this->BallastMap[$row['Car']] = $row['Ballast'];
            }

            // catch rare situation
            if (!array_key_exists($car->id(), $this->BallastMap)) {
                \Core\Log::error("Cannot find car ID" . $car->id() . " in car class ID" . $this->Id . "!");
            }
        }

        return $this->BallastMap[$car->id()];
    }


    //! @return The maximum ballast that is applied to any car in this class
    public function ballastMax() {
        $ballast_max = 0;
        foreach ($this->cars() as $car) {
            if ($this->ballast($car) > $ballast_max) $ballast_max = $this->ballast($car);
        }
        return $ballast_max;
    }


    //! @return A list of Car objects (ordered by car name)
    public function cars() {

        // update cache
        if ($this->Cars === NULL) {
            // create cache
            $this->Cars = array();
            $query = "SELECT Cars.Id FROM CarClassesMap";
            $query .= " INNER JOIN Cars ON Cars.Id=CarClassesMap.Car";
            $query .= " WHERE CarClassesMap.CarClass=" . $this->id();
            $query .= " ORDER BY Cars.Name ASC";
            foreach (\Core\Database::fetchRaw($query) as $row) {
                $this->Cars[] = Car::fromId($row['Id']);
            }
        }

        return $this->Cars;
    }


//     private function clearCache() {
//         $this->Name = NULL;
//         $this->Cars = NULL;
//
//         $this->BallastMap = NULL;
//         $this->RestrictorMap = NULL;
//         $this->OccupationMap = NULL;
//
//         CarClass::$CarClassesList = NULL;
//     }

    /**
     * Create a new car class in the database
     * @param $name An arbitrary name for the new class
     * @return The CarClass object of the new class
     */
    public static function createNew($name) {
        $id = \Core\Database::insert("CarClasses", ['Name'=>$name]);
        return CarClass::fromId($id);
    }


    //! Delete this car class from the database
    public function delete() {
        // delete maps
        $res = \Core\Database::fetch("CarClassesMap", ['Id'], ['CarClass'=>$this->id()]);
        foreach ($res as $row) {
            \Core\Database::delete("CarClassesMap", $row['Id']);
        }

        // delete car class
        $this->deleteFromDb();
    }


    //! @return Description of the CarClass
    public function description() {
        return $this->loadColumn("Description");
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("CarClasses", "CarClass", $id);
    }


//     private function getCarMapId($car) {
//         global $acswuiDatabase, $acswuiLog;
//         $res = $acswuiDatabase->fetch_2d_array("CarClassesMap", ['Id'], ['Car'=>$car->id(), 'CarClass'=>$this->Id]);
//         if (count($res) !== 1) {
//             $acswuiLog->logError("Invalid request for Car::Id=" . $car->id() . ", CarClass::Id=" . $this->Id);
//             return;
//         }
//         return $res[0]['Id'];
//     }


    /**
     * @param $car The requeted Car object
     * @return The harmonized Power/Weight respecting Ballast and Restrictor [g/W]
     */
    public function harmonizedPowerRatio($car) {
        return 1e3 * $this->weight($car) / $car->harmonizedPower($this->restrictor($car));
    }


    /**
     * @param $include_link Include a link
     * @param $show_label Include a label
     * @param $show_img Include a preview image
     * @return Html content for this object
     */
    public function html(bool $include_link = TRUE, bool $show_label = TRUE, bool $show_img = TRUE) {

        // try to find first available CarSkin
        $cars = $this->cars();
        $preview_path = NULL;
        $hover_path = NULL;
        if (count($cars)) {
            $car = $cars[0];
            $skins = $car->skins();
            if (count($skins)) {
                $skin = $skins[0];
                $preview_path = \Core\Config::RelPathHtdata . "/htmlimg/car_skins/" . $skin->id() . ".png";
                $hover_path = \Core\Config::RelPathHtdata . "/htmlimg/car_skins/" . $skin->id() . ".hover.png";
            }
        }

        $img_id = "CarClass" . $this->id();
        $html = "";

        if ($show_label) $html .= "<label for=\"$img_id\">" . $this->name() . "</label>";

        if ($show_img) {
            if ($preview_path !== NULL) {
                $html .= "<img class=\"HoverPreviewImage\" src=\"$preview_path\" id=\"$img_id\" alt=\"". $this->name() . "\" title=\"" . $this->name() . "\">";
            } else {
                $html .= "<br>No car skin for " . $this . " found!<br>";
            }
        }

        if ($include_link) $html = "<a href=\"index.php?HtmlContent=CarClass&Id=" . $this->id() . "\">$html</a>";

        $html = "<div class=\"DbEntryHtml\">$html</div>";
        return $html;
    }


    //! @return An array of all available CarClass objects in alphabetical order
    public static function listClasses() {
        $ret = array();
        $res = \Core\Database::fetch("CarClasses", ['Id'], [], "Name");
        foreach ($res as $row) {
            $ret[] = CarClass::fromId($row['Id']);
        }
        return $ret;
    }

    //! @return Name of the CarClass
    public function name() {
        return $this->loadColumn("Name");
    }

//     /**
//      * @param $car The requested Car object (All valid cars when NULL is given)
//      * @return A list of CarClassOccupation objects
//      */
//     public function occupations(Car $car = NULL) {
//         global $acswuiDatabase;
//
//         if ($this->OccupationMap !== NULL) return $this->OccupationMap[$car->id()];
//
//         // initilaize array
//         $this->OccupationMap = array();
//         foreach ($this->cars() as $c) {
//             $this->OccupationMap[$c->id()] = array();
//         }
//
//         // update with occupations
//         $query = "SELECT CarClassOccupationMap.Id, CarSkins.Car FROM CarClassOccupationMap";
//         $query .= " INNER JOIN CarSkins ON CarSkins.Id=CarClassOccupationMap.CarSkin";
//         $query .= " WHERE CarClassOccupationMap.CarClass = " . $this->Id;
//         foreach ($acswuiDatabase->fetch_raw_select($query) as $row) {
//             $this->OccupationMap[$row['Car']][] = new CarClassOccupation($row['Id']);
//         }
//
//         if ($car !== NULL) {
//             return $this->OccupationMap[$car->id()];
//         } else {
//             $all_occupations = array();
//             foreach ($this->OccupationMap as $occupations) {
//                 $all_occupations  = array_merge($all_occupations, $occupations);
//             }
//             return $all_occupations;
//         }
//     }

//     /**
//      * @param $user The user object that wants to occupy
//      * @param $skin The skin that shall be used for occupation (when ==NULL, the occupation is released)
//      */
//     public function occupy(User $user, CarSKin $skin = NULL) {
//         global $acswuiDatabase;
//         global $acswuiLog;
//
//         // release occupation
//         if ($skin === NULL) {
//             $res = $acswuiDatabase->fetch_2d_array("CarClassOccupationMap", ['Id'],
//                             ['User'=>$user->id(), 'CarClass'=>$this->Id]);
//             foreach ($res as $row) {
//                 $acswuiDatabase->delete_row("CarClassOccupationMap", $row['Id']);
//             }
//             return;
//         }
//
//         // check if skin is valid
//         $skin_is_valid = FALSE;
//         foreach ($this->cars() as $car) {
//             if ($skin->car()->id() == $car->id()) $skin_is_valid = TRUE;
//         }
//         if ($skin_is_valid !== TRUE) {
//             $acswuiLog->logError("Invalid car occupation");
//             return;
//         }
//
//         // check if skin is preserved
//         if ($skin->steam64GUID() != "" && $skin->steam64GUID() != $user->steam64GUID()) {
//             $acswuiLog->logWarning("Ignore occupying preserved skin.");
//             return;
//         }
//
//         // check if car and skin is already occupied
//         $res = $acswuiDatabase->fetch_2d_array("CarClassOccupationMap", ['Id'],
//                         ['CarSkin'=>$skin->id(), 'CarClass'=>$this->Id]);
//         if (count($res) !== 0) {
//             $acswuiLog->logWarning("Ignoring overlapping car occupation");
//             return;
//         }
//
//         // check if user has already occupated
//         $res = $acswuiDatabase->fetch_2d_array("CarClassOccupationMap", ['Id'],
//                         ['User'=>$user->id(), 'CarClass'=>$this->Id]);
//         if (count($res) == 0) {
//             $acswuiDatabase->insert_row("CarClassOccupationMap",
//                 ['CarClass'=>$this->Id, 'User'=>$user->id(), 'CarSkin'=>$skin->id()]);
//         } else if (count($res) == 1) {
//             $acswuiDatabase->update_row("CarClassOccupationMap", $res[0]['Id'],
//                 ['CarClass'=>$this->Id, 'User'=>$user->id(), 'CarSkin'=>$skin->id()]);
//         } else {
//             $acswuiLog->logWarning("Overlapping car occupations for car class=" . $this->Id . " user=" . $user->id());
//             foreach ($res as $row) {
//                 $acswuiDatabase->delete_row("CarClassOccupationMap", $row['Id']);
//             }
//             $acswuiDatabase->insert_row("CarClassOccupationMap",
//                 ['CarClass'=>$this->Id, 'User'=>$user->id(), 'CarSkin'=>$skin->id()]);
//         }
//
//         $this->clearCache();
//     }

    /**
     * Remove a car from the car class
     * @param $car The Car object to be removed
     */
    public function removeCar(Car $car) {

        // remove car
        $query = "DELETE FROM CarClassesMap WHERE CarClass = " . $this->id() . " AND Car = " . $car->id();
        \Core\Database::query($query);

        // clear cache
        $this->Cars = NULL;
    }


    /**
     * Change the name of the car class
     * @param $new_name The new name of the car class
     */
    public function rename($new_name) {
        \Core\Database::update("CarClasses", $this->id(), ["Name"=>$new_name]);
        $this->Name = $new_name;
    }


    /**
     * @param $car The requeted Car object
     * @return The necessary restrictor for a certain car in the class
     */
    public function restrictor($car) {

        if ($this->RestrictorMap === NULL || !array_key_exists($car->id(), $this->RestrictorMap)) {

            $this->RestrictorMap = array();
            foreach (\Core\Database::fetch("CarClassesMap", ['Car', 'Restrictor'], ['CarClass'=>$this->id()]) as $row) {
                $this->RestrictorMap[$row['Car']] = $row['Restrictor'];
            }

            // catch rare situation
            if (!array_key_exists($car->id(), $this->RestrictorMap)) {
                \Core\Log::error("Cannot find car ID" . $car->id() . " in car class ID" . $this->id() . "!");
            }
        }

        return $this->RestrictorMap[$car->id()];
    }


    /**
     * Set A new ballast value for a cetain car in the class
     * @param $car The requested Car object
     * @param $ballast The new ballast value (0...9999)
     */
    public function setBallast(Car $car, int $ballast) {

        // invalidate cache
        $this->BallastMap = NULL;

        if ($ballast < 0 || $ballast > 9999) {
            \Core\Log::error("Invalid ballast value: " . $ballast);
            return;
        }

        $query = "UPDATE CarClassesMap SET Ballast = $ballast WHERE CarClass = " . $this->id() . " AND Car = " . $car->id();
        \Core\Database::query($query);
    }


    /**
     * Set A new restrictor value for a cetain car in the class
     * @param $car The requested Car object
     * @param $restrictor The new restrictor value (0...100)
     */
    public function setRestrictor(Car $car, int $restrictor) {

        // invalidate cache
        $this->RestrictorMap = NULL;

        if ($restrictor < 0 || $restrictor > 100) {
            \Core\Log::error("Invalid restrictor value: " . $restrictor);
            return;
        }

        $query = "UPDATE CarClassesMap SET Restrictor = $restrictor WHERE CarClass = " . $this->id() . " AND Car = " . $car->id();
        \Core\Database::query($query);
    }


    /**
     * Set A new description for the CarClass
     * @param $description The new description
     */
    public function setDescription(string $description) {
        $fields = array();
        $fields['Description'] = $description;
        \Core\Database::update("CarClasses", $this->id(), $fields);
        $this->Description = $description;
    }


    /**
     * Check if a certain Car is contained in this CarClass
     * @return True if the requested car object is part of this car class
     */
    public function validCar(Car $car) {
        foreach ($this->cars() as $c) {
            if ($c->id() != $car->id()) continue;
            return TRUE;
        }
        return FALSE;
    }


//     /**
//      * Check if a certain Lap is driven by a car valid for this CarClass
//      * @param $lap The requested Lap object
//      * @return True if the requested lap object is valid for this carclass
//      */
//     public function validLap(Lap $lap) {
//
//         $carskin = $lap->carSkin();
//         if ($carskin === NULL) return FALSE;
//
//         foreach ($this->cars() as $c) {
//
//             // check car
//             if ($c->id() != $carskin->car()->id()) continue;
//
//             // check ballast
//             if ($lap->ballast() < $this->ballast($c)) continue;
//
//             // check restrictor
//             if ($lap->restrictor() < $this->restrictor($c)) continue;
//
//             return TRUE;
//         }
//         return FALSE;
//     }


    /**
     * @param $car The requeted Car object
     * @return The weight of the car within the class (including ballast)
     */
    public function weight($car) {
        return $car->weight() + $this->ballast($car);
    }
}

?>
