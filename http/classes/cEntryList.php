<?php



class EntryListItem {
    private $CClass = NULL;
    private $Skin = NULL;

    public function  __construct(CarClass $cc, CarSkin $skin) {
        $this->CClass = $cc;
        $this->Skin = $skin;
    }

    public function skinId() {
        return $this->Skin->id();
    }

    public function carId() {
        return $this->Skin->car()->id();
    }

    public function writeFile($fd, int $car_id) {
            $model = $this->Skin->car()->model();
            $skin = $this->Skin->skin();
            $ballast = $this->CClass->ballast($this->Skin->car());
            $restrictor = $this->CClass->restrictor($this->Skin->car());

            fwrite($fd, "[CAR_$car_id]\n");
            fwrite($fd, "MODEL=$model\n");
            fwrite($fd, "SKIN=$skin\n");
            fwrite($fd, "SPECTATOR_MODE=0\n");
            fwrite($fd, "DRIVERNAME=\n");
            fwrite($fd, "TEAM=\n");
            fwrite($fd, "GUID=\n");
            fwrite($fd, "BALLAST=$ballast\n");
            fwrite($fd, "RESTRICTOR=$restrictor\n");
            fwrite($fd, "\n");
    }
}

class EntryListItemOccupied extends EntryListItem {
    private $Occupation = NULL;

    public function __construct(CarClassOccupation $occupation) {
        parent::__construct($occupation->class(), $occupation->skin());
        $this->Occupation = $occupation;
    }

    public function writeFile($fd, int $car_id) {
            $model = $this->Occupation->skin()->car()->model();
            $skin = $this->Occupation->skin()->skin();
            $drivername = $this->Occupation->user()->login();
            $guid = $this->Occupation->user()->steam64GUID();
            $ballast = $this->Occupation->class()->ballast($this->Occupation->skin()->car());
            $restrictor = $this->Occupation->class()->restrictor($this->Occupation->skin()->car());

            fwrite($fd, "[CAR_$car_id]\n");
            fwrite($fd, "MODEL=$model\n");
            fwrite($fd, "SKIN=$skin\n");
            fwrite($fd, "SPECTATOR_MODE=0\n");
            fwrite($fd, "DRIVERNAME=$drivername\n");
            fwrite($fd, "TEAM=\n");
            fwrite($fd, "GUID=$guid\n");
            fwrite($fd, "BALLAST=$ballast\n");
            fwrite($fd, "RESTRICTOR=$restrictor\n");
            fwrite($fd, "\n");
    }
}


class CarSkinList {
    private $AvailableCarSkins = NULL;

    public function __construct(CarClass $carclass) {
        global $acswuiLog;
        $this->AvailableCarSkins = array();

        foreach ($carclass->cars() as $car) {
            $skinlist = array();
            foreach ($car->skins() as $skin) {
                $skinlist[] = $skin->id();
            }
            if (count($skinlist) > 0) {
                $this->AvailableCarSkins[$car->id()] = $skinlist;
            } else {
                $msg = "No skins for car '";
                $msg .= $car->name();
                $msg .= "' ID ";
                $msg .= $car->id();
                $acswuiLog->logWarning($msg);
            }
        }
    }

    public function listAvailableCarIds() {
        return array_keys($this->AvailableCarSkins);
    }

    //! Remove a speficied skin from the list (car is removed when no skins left)
    public function popCarSkin(CarSkin $skin) {

        // delete skin
        $car_ids_to_be_popped = array();
        foreach (array_keys($this->AvailableCarSkins) as $car_id) {
            if ($skin->car()->id() != $car_id) continue;
            $skinlist = array();
            foreach ($this->AvailableCarSkins[$car_id] as $skin_id) {
                if ($skin->id() != $skin_id) $skinlist[] = $skin_id;
            }
            $this->AvailableCarSkins[$car_id] = $skinlist;
            if (count($skinlist) == 0) $car_ids_to_be_popped[] = $car_id;
        }

        // delete cars without available skins
        $AvailableCarSkins = array();
        foreach (array_keys($this->AvailableCarSkins) as $car_id) {
            if (!in_array($car_id, $car_ids_to_be_popped))
                $AvailableCarSkins[$car_id] = $this->AvailableCarSkins[$car_id];
        }
        $this->AvailableCarSkins = $AvailableCarSkins;
    }

    //! Return the next available skin of a car
    public function yieldCarSkin(int $car_id) {
        $skin = new CarSkin($this->AvailableCarSkins[$car_id][0]);
        $this->popCarSkin($skin);
        return $skin;
    }

    public function listHtml() {
        $html = "";
        foreach (array_keys($this->AvailableCarSkins) as $car_id) {
            $html .= "CAR[$car_id]:(";
            foreach ($this->AvailableCarSkins[$car_id] as $skin_id) {
                $html .= $skin_id . ", ";
            }
            $html .= ") ";
        }
        return $html;
    }
}


class EntryList {

    private $CarClass = NULL;
    private $Track = NULL;
    private $SeatOccupation = NULL;

    private $AvailableCarSkinList = NULL;
    private $EntryItemsList = NULL;

    /**
     * @param $seat_occupations When set to FALSE, seat occupations are ignored (default = TRUE)
     */
    public function __construct(CarClass $carclass, Track $track, bool $seat_occupations = TRUE) {
        $this->CarClass = $carclass;
        $this->Track = $track;
        $this->SeatOccupation = $seat_occupations;
    }

    private function leastRepresentedAvailableCarskin() {

        // initialize list
        $car_id_amount = array();
        foreach ($this->AvailableCarSkinList->listAvailableCarIds() as $car_id) {
            $car_id_amount[$car_id] = 0;
        }

        // count
        foreach ($this->EntryItemsList as $entry) {
            if (array_key_exists($entry->carId(), $car_id_amount))
                $car_id_amount[$entry->carId()] += 1;
        }

        // find least represented car
        $min_count = NULL;
        $min_car_id = NULL;
        foreach (array_keys($car_id_amount) as $car_id) {
            $amount = $car_id_amount[$car_id];
            if ($min_count === NULL || $amount < $min_count) {
                $min_count = $amount;
                $min_car_id = $car_id;
            }
        }
        if ($min_car_id === NULL) return NULL;

        // get next skin
        return $this->AvailableCarSkinList->yieldCarSkin($min_car_id);

    }

    public function writeToFile(string $filepath) {
        global $acswuiConfig;
        global $acswuiLog;

        $this->AvailableCarSkinList = new CarSkinList($this->CarClass);
//         echo $this->AvailableCarSkinList->listHtml() . "<br>";

        // list that contains all entriy items in correct order
        $this->EntryItemsList = array();

        // add all seat occupations
        if ($this->SeatOccupation) {
            foreach ($this->CarClass->occupations() as $ocu) {
                $this->EntryItemsList[] = new EntryListItemOccupied($ocu);
                $this->AvailableCarSkinList->popCarSkin($ocu->skin());
            }
        }
//         echo $this->AvailableCarSkinList->listHtml() . "<br>";


        // TODO How to handle more occupations than pitboxes???
        if (count($this->EntryItemsList) > $this->Track->pitboxes()) {
            $msg = "Try to generate an entry list with more occupations than pitboxes.\n";
            $msg .= "CarClass '" . $this->CarClass->name() . "' (ID " . $this->CarClass->id() . ", " . count($this->EntryItemsList) . " occupations)";
            $msg .= "Track '" . $this->Track->name() . "' (ID " . $this->Track->id() . ", " . $this->Track->pitboxes() . " pits)";
            $acswuiLog->logWarning($msg);
        }

        // fill entries, try equalizing car diversity
        while (count($this->EntryItemsList) < $this->Track->pitboxes()) {
            $skin = $this->leastRepresentedAvailableCarskin();
            if ($skin === NULL) break;
            $this->EntryItemsList[] = new EntryListItem($this->CarClass, $skin);
        }

        // randomize entries to have random pit assignment
        shuffle($this->EntryItemsList);

        // write entries
        $fd = fopen($filepath, 'w');
        if ($fd === False) {
            $acswuiLog->logError("Cannot open '$filepath' for writing!");
            return;
        }
        for ($i = 0; $i < count($this->EntryItemsList); ++$i) {
            $this->EntryItemsList[$i]->writeFile($fd, $i);
        }
        fclose($fd);
    }



}

?>
