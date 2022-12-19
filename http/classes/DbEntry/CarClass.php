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
    private $BestTimes = array();
    private $RestrictorMap = NULL;
    private $ValidCarIds = NULL;
//     private $OccupationMap = NULL;

    private $DrivenLength = NULL;
    private $DrivenLaps = NULL;

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
    public function ballast($car) : int {

        if ($this->BallastMap === NULL || !array_key_exists($car->id(), $this->BallastMap)) {

            $this->BallastMap = array();
            foreach (\Core\Database::fetch("CarClassesMap", ['Car', 'Ballast'], ['CarClass'=>$this->id()]) as $row) {
                $this->BallastMap[$row['Car']] = $row['Ballast'];
            }

            // catch rare situation
            if (!array_key_exists($car->id(), $this->BallastMap)) {
                \Core\Log::error("Cannot find car ID" . $car->id() . " in car class ID" . $this->id() . "!");
            }
        }

        return (int) $this->BallastMap[$car->id()];
    }


    //! @return The maximum ballast that is applied to any car in this class
    public function ballastMax() {
        $ballast_max = 0;
        foreach ($this->cars() as $car) {
            if ($this->ballast($car) > $ballast_max) $ballast_max = $this->ballast($car);
        }
        return $ballast_max;
    }


    //! @return An array of Lap objects with best laptiumes of this carclass on a certain track
    public function bestLaps(Track $track) {

        if (!array_key_exists($track->id(), $this->BestTimes)) {
            $this->BestTimes[$track->id()] = array();

            // get from records file
            $records_file = \Core\Config::AbsPathData . "/htcache/records_carclass.json";
            if (file_exists($records_file)) {
                $records = json_decode(file_get_contents($records_file), TRUE);
                if (array_key_exists($this->id(), $records) && array_key_exists($track->id(), $records[$this->id()])) {
                    foreach ($records[$this->id()][$track->id()] as $lap_id) {
                        $this->BestTimes[$track->id()][] = Lap::fromId($lap_id);
                    }
                }

            // get from database
            } else {

                $found_driver_ids = array();
                //! @todo Find better query to have unique Laps.User in the response. This could increase speed significantly
                $query = "SELECT Laps.Id, Laps.User FROM `Laps` INNER JOIN Sessions ON Sessions.Id = Laps.Session INNER JOIN CarSkins On CarSkins.Id = Laps.CarSkin INNER JOIN CarClassesMap ON CarSkins.Car = CarClassesMap.Car WHERE Sessions.Track = " . $track->id() . " AND Laps.Cuts = 0 AND CarClassesMap.CarClass = " . $this->id() . " ORDER BY Laptime ASC;";
                foreach (\Core\Database::fetchRaw($query) as $row) {
                    $user_id = $row['User'];
                    if (!in_array($user_id, $found_driver_ids)) {
                        $found_driver_ids[] = $user_id;
                        $lap = Lap::fromId($row['Id']);
                        $this->BestTimes[$track->id()][] = $lap;
                    }
                }
            }

        }

        return $this->BestTimes[$track->id()];
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


    /**
     * Create a new car class in the database
     * @param $name An arbitrary name for the new class
     * @return The CarClass object of the new class
     */
    public static function createNew($name) {
        $id = \Core\Database::insert("CarClasses", ['Name'=>$name]);
        return CarClass::fromId($id);
    }


    /**
     * Compares two CarClass objects against their driven length
     * This is intended for usort() of arrays with Lap objects
     * @param $o1 CarClass object
     * @param $o2 CarClass object
     */
    public static function compareDrivenLength(CarClass $o1, CarClass $o2) {
        if ($o1->drivenLength() < $o2->drivenLength()) return 1;
        if ($o1->drivenLength() > $o2->drivenLength()) return -1;
        return 0;
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


    //!n @return Amount of meters driven on this track
    public function drivenLength() {
        if ($this->DrivenLength === NULL) {
            $this->DrivenLength = 0;
            $query = "SELECT Sessions.Track FROM Laps JOIN Sessions ON Laps.Session = Sessions.Id WHERE Sessions.CarClass = {$this->id()};";
            foreach (\Core\Database::fetchRaw($query) as $row) {
                $track = \DbEntry\Track::fromId($row['Track']);
                $this->DrivenLength += $track->length();
            }
        }
        return $this->DrivenLength;
    }


    //! @return Amount of laps turned with this CarClass
    public function drivenLaps() {
        if ($this->DrivenLaps === NULL) {
            $id = $this->id();
            $res = \Core\Database::fetchRaw("SELECT COUNT(Laps.Id) as DrivenLaps FROM Laps JOIN Sessions ON Laps.Session = Sessions.Id WHERE Sessions.CarClass = $id");
            $this->DrivenLaps = $res[0]['DrivenLaps'];
        }
        return $this->DrivenLaps;
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
     * Group a list of CarSkin objects by car classes.
     * @param $carskins A list of CarSkin objects
     * @return An associative array [CarClass1Id=>[CarSkin1,CarSkin4], CarClass2Id=>[CarSkin2, CarSkin5], NULL=>[CarSkin3, CarSkin6]]
     */
    public static function groupCarSkins(array $carskins) {
        $group_array = array();
        $grouped_carskins = array();

        // group by each car class
        foreach (CarClass::listClasses() as $carclass) {
            foreach ($carskins as $cs) {
                if ($carclass->validCar($cs->car())) {
                    if (!array_key_exists($carclass->id(), $group_array)) $group_array[$carclass->id()] = array();
                    $group_array[$carclass->id()][] = $cs;
                    $grouped_carskins[] = $cs;
                }
            }
        }

        // group un-classed carskins
        foreach ($carskins as $cs) {
            if (!in_array($cs, $grouped_carskins)) {
                if (!array_key_exists(NULL, $group_array)) $group_array[NULL] = array();
                $group_array[NULL][] = $cs;
                $grouped_carskins[] = $cs;
            }
        }

        return $group_array;
    }


    /**
     * @param $car The requeted Car object
     * @return The harmonized Power/Weight respecting Ballast and Restrictor [g/W]
     */
    public function harmonizedPowerRatio($car) {
        $power = $car->harmonizedPower($this->restrictor($car));
        $pr = 0;
        if ($power > 0) $pr = 1e3 * $this->weight($car) / $power;
        return $pr;
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


    //! @return An html string with the name and a link
    public function htmlName() {
        return "<a href=\"index.php?HtmlContent=CarClass&Id=" . $this->id() . "\">" . $this->name() . "</a>";
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
        $new_name = substr($new_name, 0, 50);
        \Core\Database::update("CarClasses", $this->id(), ["Name"=>$new_name]);
        $this->Name = $new_name;
    }


    /**
     * @param $car The requeted Car object
     * @return The necessary restrictor for a certain car in the class
     */
    public function restrictor($car) : int {

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

        return (int) $this->RestrictorMap[$car->id()];
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
        return in_array($car->id(), $this->validCarIds());
    }


    //! @return A list of Database IDs of the car table which are in this car class
    public function validCarIds() {
        if ($this->ValidCarIds === NULL) {
            $this->ValidCarIds = array();
            $res = \Core\Database::fetch("CarClassesMap", ['Car'], ['CarClass'=>$this->id()]);
            foreach ($res as $row) {
                $this->ValidCarIds[] = (int) $row['Car'];
            }
        }
        return $this->ValidCarIds;
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
