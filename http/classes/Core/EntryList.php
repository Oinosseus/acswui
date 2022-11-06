<?php

namespace Core;

/**
 * This class represents an item in entry_list.ini for the acServer
 */
class EntryList {

    private $ListItems = array();

    //! CacheUsers[User->id()] == User
    private $CacheUsers = array();

    //! $CacheCarSkins[CarSkin->id()] = CarSkin
    private $CacheCarSkins = array();

    public function __construct() {

        // add TVCar
        if (\Core\ACswui::getParam('TVCarEna')) {

            // get car
            $model_name = \Core\ACswui::getParam('TVCarModel');
            $car = \DbEntry\Car::fromModel($model_name);
            if ($car === NULL) {
                \Core\Log::warning("Model '$model_name' for TV Car not found!");
            } else {

                // get skin
                $skin_name = \Core\ACswui::getParam('TVCarSkin');
                $carskin = \DbEntry\CarSkin::fromSkin($car, $skin_name);
                if ($carskin === NULL) {
                    \Core\Log::warning("Skin '$skin_name' for TV Car model '$model_name' not found!");
                } else {

                    // add entry
                    $eli = new EntryListItem($carskin, NULL, 0, 0);
                    $eli->forceGUIDs(\Core\ACswui::getParam('TVCarGuids'));
                    $this->add($eli);
                }
            }
        }
    }



    //! @return The string representation of the class
    public function __toString() {
        return "EntryList[]";
    }


    /**
     * Add an EntryListItem
     * @param $eli An EntryListItem object
     */
    public function add(EntryListItem $eli) {
        if ($eli->user() !== NULL) $this->CacheUsers[$eli->user()->id()] = $eli->user();
        $this->CacheCarSkins[$eli->carSkin()->id()] = $eli->carSkin();
        $this->ListItems[] = $eli;
    }


    /**
     * Apply ballast and restrictor from Car class to all current entries
     * @param $cc \DbEntry\CarClass to retrieve ballast/restrictor from
     */
    public function  applyCarClass(\DbEntry\CarClass $cc) {

        // list all cars within class
        $car_ids = array();
        foreach ($cc->cars() as $car) $car_ids[] = $car->id();

        // apply to all cars within class
        foreach ($this->ListItems as $eli) {
            $car = $eli->carSkin()->car();
            if (in_array($car->id(), $car_ids)) {
                $ballast = $cc->ballast($car);
                $eli->setBallast($ballast);
                $restrictor = $cc->restrictor($car);
                $eli->setRestrictor($restrictor);
            }
        }
    }


    //! @return TRUE if the requested CarSkin is in the EntryList
    public function containsCarSkin(\DbEntry\CarSkin $car_skin) {
        return array_key_exists($car_skin->id(), $this->CacheCarSkins);
    }


    //! @return TRUE if the requested User is in the EntryList
    public function containsUser(\DbEntry\User $user) {
        return array_key_exists($user->id(), $this->CacheUsers);
    }


    //! @return The amount of EntryListItems
    public function count() {
        return count($this->ListItems);
    }


    //! @return An array of EntryListItem objects
    public function entries() {
        return $this->ListItems;
    }


    /**
     * Automatically creates EntryListItem objects.
     * Available CarSkins from the given CarClass are used.
     * CarSkins from already existing EntryListItem object will not be used.
     * Each CarSkin object will be unique.
     *
     * @param $cc The CarClass object where to get the cars from
     * @param $track The Track object to retrieve the maximum available pitboxes
     * @param $ballast This ballast will be applied to all entries
     * @param $restrictor This restrictor will be applied to all entries
     */
    public function fillSkins(\DbEntry\CarClass $cc, \DbEntry\Track $track, int $ballast=0, int $restrictor=0) {

        // list available skins for each car
        $available_carskins = array(); // $available_carskins[Car->id()] = [CarSkin, CarSkin, ...]
        foreach ($cc->cars() as $car) {
            $available_skins = array();
            foreach ($car->skins() as $cskin) {

                // skip owned skins
                if ($cskin->owner() !== NULL) continue;

                if (!$this->containsCarSKin($cskin)) {
                    $available_skins[] = $cskin;
                }
            }
            $available_carskins[] = $available_skins;
        }

        // serialize the available carskins
        $serial_skin_list = array();
        for ($skin_index=0; TRUE; ++$skin_index) {
            $any_skins_available = FALSE;
            for ($car_index=0; $car_index < count($available_carskins); ++$car_index) {
                if ($skin_index < count($available_carskins[$car_index])) {
                    $serial_skin_list[] = $available_carskins[$car_index][$skin_index];
                    $any_skins_available = TRUE;
                }
            }
            if (!$any_skins_available) break;
        }

        // fill entry list
        foreach ($serial_skin_list as $cskin) {
            if ($this->count() >= $track->pitboxes()) break;
            $eli = new \Core\EntryListItem($cskin, NULL, $ballast, $restrictor);
            $this->add($eli);
        }
    }


    //! reverse entrly list
    public function reverse() {
        $this->ListItems = array_reverse($this->ListItems);
    }


    //! Shuffle the current entries
    public function shuffle() {
        shuffle($this->ListItems);
    }


    /**
     * Write the EntryList to a entry_list.ini file
     * @param $entry_list_ini_path The target file path
     */
    public function writeToFile(string $entry_list_ini_path) {
        $f = fopen($entry_list_ini_path, 'w');
        if ($f === FALSE) {
            \Core\Log::error("Cannot write to file '$file_path'!");
            return;
        }

        $entry_id = 0;
        foreach ($this->ListItems as $eli) {
            $eli->writeToFile($f, $entry_id++);
            fwrite($f, "\n");
        }

        fclose($f);
        @chmod($entry_list_ini_path, 0660);
    }
}
