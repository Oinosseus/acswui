<?php

namespace Core;

/**
 * This class represents an item in entry_list.ini for the acServer
 */
class EntryListItem {

    private $CarSkin = NULL;
    private $User = NULL;
    private $Ballast = 0;
    private $Restrictor = 0;
    private $ForceGUIDs = NULL;

    public function __construct(\DbEntry\CarSkin $skin,
                                \DbEntry\User $user=NULL,
                                int $ballast=0,
                                int $restrictor=0) {
        $this->CarSkin = $skin;
        $this->User = $user;
        $this->Ballast = $ballast;
        $this->Restrictor = $restrictor;
    }


    //! @return The CarSkin object of the occupation
    public function carSkin() {
        return $this->CarSkin;
    }


    /**
     * This sets the GUID for the car item in the entry list.
     * When using this function, the assigned User object is ignored.
     */
    public function forceGUIDs(string $guids) {
        $this->ForceGUIDs = $guids;
    }


    //! @return The User object of the occupation (can be NULL)
    public function user() {
        return $this->User;
    }


    /**
     * Write the item to a file
     * @param $f Is an writeable-opneded finle handler
     * @param $id An incremental identifier for each car entry
     */
    public function writeToFile($f, int $id) {
        fwrite($f, "[CAR_$id]\n");
        fwrite($f, "MODEL={$this->CarSkin->car()->model()}\n");
        fwrite($f, "SKIN={$this->CarSkin->skin()}\n");
        fwrite($f, "SPECTATOR_MODE=0\n");

        if ($this->ForceGUIDs !== NULL) {
            fwrite($f, "DRIVERNAME=ACswui TV Cam\n");
            fwrite($f, "GUID={$this->ForceGUIDs}\n");
        } else if ($this->User === NULL) {
            fwrite($f, "DRIVERNAME=\n");
            fwrite($f, "GUID=\n");
        } else {
            fwrite($f, "DRIVERNAME={$this->getUserNameFromDb()}\n");
            fwrite($f, "GUID={$this->User->steam64GUID()}\n");
        }

        fwrite($f, "TEAM=\n");

        fwrite($f, "BALLAST={$this->Ballast}\n");
        fwrite($f, "RESTRICTOR={$this->Restrictor}\n");
    }


    private function getUserNameFromDb() {
        if (!$this->User) return "";
        $res = \Core\Database::fetch("Users", ['Name'], ['Id'=>$this->User->id()]);
        if (count($res)) return $res[0]['Name'];
        return "";
    }


    public function setBallast(int $ballast) {
        $this->Ballast = $ballast;
    }


    public function setRestrictor(int $restrictor) {
        $this->Restrictor = $restrictor;
    }
}
