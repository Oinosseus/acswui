<?php


class EntryListCar {

    private $Car = NULL;
    private $SkinIndex = 0;

    //! @param $car A Car object
    public function __construct($car) {
        $this->Car = $car;
    }

    public function model() {
        return $this->Car->model();
    }

    public function skin() {
        $skins = $this->Car->skins();
        if ($this->SkinIndex >= count($skins)) return "";
        $skin = $skins[$this->SkinIndex];
        $this->SkinIndex += 1;
        return $skin->skin();
    }
}



class EntryListCarEntries {
    private $Entries = array();
    private $Index = 0;

    //! @param $cars list of Car objects
    public function __construct($cars) {
        foreach($cars as $car) {
            $this->Entries[] = new EntryListCar($car);
        }
    }

    public function getNextEntry() {

        // wrap index
        if ($this->Index >= count($this->Entries)) $this->Index = 0;

        $entry = $this->Entries[$this->Index];

        // increment index
        ++$this->Index;

        return $entry;
    }
}


class EntryList {

    private $CarClass = NULL;
    private $Track = NULL;

    public function __construct(CarClass $carclass, Track $track) {
        $this->CarClass = $carclass;
        $this->Track = $track;
    }

    public function writeToFile(string $filepath) {
        global $acswuiConfig;
        global $acswuiLog;

        // open file
        $fd = fopen($filepath, 'w');
        if ($fd === False) {
            $acswuiLog->logError("Cannot open '$filepath' for writing!");
            return;
        }

        // write entries
        $entries = new EntryListCarEntries($this->CarClass->cars());
        for ($entry_idx = 0; $entry_idx < $this->Track->pitboxes(); ++$entry_idx) {

            $entry = $entries->getNextEntry();

            fwrite($fd, "[CAR_$entry_idx]\n");
            fwrite($fd, "MODEL=" . $entry->model() . "\n");
            fwrite($fd, "SKIN=" . $entry->skin() . "\n");
            fwrite($fd, "SPECTATOR_MODE=0\n");
            fwrite($fd, "DRIVERNAME=\n");
            fwrite($fd, "TEAM=\n");
            fwrite($fd, "GUID=\n");
            fwrite($fd, "BALLAST=0\n");
            fwrite($fd, "RESTRICTOR=0\n");
            fwrite($fd, "\n");
        }

        // close file
        fclose($fd);
    }



}

?>
