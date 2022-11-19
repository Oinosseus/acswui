<?php

namespace Core;

/**
 * This class represents an item in entry_list.ini for the acServer
 */
class EntryList {

    private $ListItems = array();

    //! CacheUsers[User->id()] == User
    // private $CacheUsers = array();

    //! $CacheCarSkins[CarSkin->id()] = CarSkin
    private $CacheCarSkins = array();

    //! remmeber if TVCar has been added
    private $TVCarAdded = FALSE;

    public function __construct() {
    }


    //! @return The string representation of the class
    public function __toString() {
        $n = $this->count();
        return "EntryList[{$n} Entries]";
    }


    /**
     * Add an EntryListItem
     * @param $eli An EntryListItem object
     */
    public function add(EntryListItem $eli) {
        // if ($eli->user() !== NULL) $this->CacheUsers[$eli->user()->id()] = $eli->user();
        $this->CacheCarSkins[$eli->carSkin()->id()] = $eli->carSkin();
        $this->ListItems[] = $eli;
    }


    /**
     * This will add the TV car to the entry list.
     *
     * When TVCarEna in ACswui settings is not activated, this call will have no effect.
     * When the TV-Car already has been added, this has no effect.
     */
    public function addTvCar() {

        // check if TVCar is activated
        if (\Core\ACswui::getParam('TVCarEna') && !$this->TVCarAdded) {
            $this->TVCarAdded = TRUE;  // if it failes, it will also fail in future, so adding can be assumed

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
                    $eli = new EntryListItem($carskin);
                    $eli->forceDriver("ACswui TV Car", \Core\ACswui::getParam('TVCarGuids'));
                    $eli->setSpectator(TRUE);
                    $this->add($eli);
                }
            }
        }
    }


    //! @return TRUE if the requested CarSkin is in the EntryList
    public function containsCarSkin(\DbEntry\CarSkin $car_skin) {
        return array_key_exists($car_skin->id(), $this->CacheCarSkins);
    }


    // //! @return TRUE if the requested User is in the EntryList
    // public function containsUser(\DbEntry\User $user) {
    //     return array_key_exists($user->id(), $this->CacheUsers);
    // }


    //! @return The amount of EntryListItems
    public function count() {
        return count($this->ListItems);
    }


    //! @return An array of EntryListItem objects
    public function entries() : array {
        return $this->ListItems;
    }


    /**
     * Automatically creates EntryListItem objects.
     * Available CarSkins from the given CarClass are used.
     * CarSkins from already existing EntryListItem object will not be used.
     * Each CarSkin object will be unique.
     *
     * @param $cc The CarClass object where to get the cars from
     * @param $max_count The number of entries that shall be in the EntryList afterwards
     */
    public function fillSkins(\DbEntry\CarClass $cc, int $max_count) {

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
            if ($this->count() >= $max_count) break;
            $eli = new \Core\EntryListItem($cskin);
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
