<?php

namespace Core;

/**
 * This class represents an item in entry_list.ini for the acServer
 */
class EntryListItem {

    private $CarSkin = NULL;
    private $Users = NULL;
    private $TeamCar = NULL;
    private $ForceDriver = NULL;
    private $Spectator = FALSE;

    public function __construct(\DbEntry\CarSkin $skin) {
        $this->CarSkin = $skin;
    }


    /**
     * Assign driver(s) to this cars.
     * This could be either multiple User objects,
     * or one TeamCar object.
     *
     * When adding a TeamCar while previously users or a different TeamCar were added, this generates an error.
     * When adding a User while previously a TeamCar was assigned, this generates an error.
     *
     * To add multiple users, call this multiple times.
     *
     * @param $driver Either User or TeamCar object.
     */
    public function addDriver(\DbEntry\User|\DbEntry\TeamCar $driver) {

        // add a user
        if (is_a($driver, "\DbEntry\User")) {
            if ($this->TeamCar) {
                \Core\Log::error("Cannot add a user, because a team-car is already assigned!");
            } else {
                if ($this->Users === NULL) $this->Users = array();
                $this->Users[] = $driver;
            }

        // add a team car
        } else if (is_a($driver, "\DbEntry\TeamCar")) {
            if ($this->Users !== NULL) {
                \Core\Log::error("Cannot add a TeamCar, because User is already assigned!");
            } else if ($this->TeamCar !== NULL) {
                \Core\Log::error("Cannot add a TeamCar, because another TeamCar is already assigned!");
            } else {
                $this->TeamCar = $driver;
            }

        //! just for debugging
        } else {
            \Core\Log::error("Unexpected class: '" . get_class($driver) . "'!");
        }
    }


    //! @return The CarSkin object of the occupation
    public function carSkin() : \DbEntry\CarSkin {
        return $this->CarSkin;
    }


    /**
     * When this is called, the actual users are ignored and
     * driver-name and Steam64GUID is overwritten
     * @param $driver_name The name of the driver that shall be visible in the entry-list
     * @param $steam64guid The Steam64GUID that shall be set in the entry-list (can be any string - also multiple GUIDs)
     */
    public function forceDriver(string $driver_name, string $steam64guid) {
        $this->ForceDriver = [$driver_name, $steam64GUID];
    }


    //! @param §spectator Defines if SPECTATOR in entry list shall be set or not
    public function setSpectator(bool $spectator) {
        $this->Spectator = $spectator;
    }


    //! @return A list of User objects of the occupation (can be empty)
    public function users() : array {
        return $this->Users;
    }


    /**
     * Write the item to a file
     * @param $f Is an writeable-opneded finle handler
     * @param $id An incremental identifier for each car entry
     */
    public function writeToFile($f, int $id) {

        // fillout driver/guid
        $drivername = "";
        $guid = "";
        if ($this->ForceDriver !== NULL) {        // forced
            $drivername = $this->ForceDriver[0];
            $guid = $this->ForceDriver[1];
        } else if ($this->Users !== NULL) {        // from User
            foreach ($this->Users as $u) {
                if (strlen($drivername) > 0) $drivername .= ";";
                $drivername .= $u->name();
                if (strlen($guid) > 0) $guid .= ";";
                $guid .= $u->steam64GUID();
            }
        } else if ($this->TeamCar !== NULL) {    // from TeamCar
            foreach ($this->TeamCar->drivers() as $tmm) {
                $u = $tmm->user();
                if (strlen($drivername) > 0) $drivername .= ";";
                $drivername .= $u->name();
                if (strlen($guid) > 0) $guid .= ";";
                $guid .= $u->steam64GUID();
            }
        }

        // export
        fwrite($f, "[CAR_$id]\n");
        fwrite($f, "MODEL={$this->CarSkin->car()->model()}\n");
        fwrite($f, "SKIN={$this->CarSkin->skin()}\n");
        fwrite($f, "DRIVERNAME={$drivername}\n");
        fwrite($f, "GUID={$guid}\n");
        if ($this->TeamCar) {
            fwrite($f, "TEAM={$this->TeamCar->team()->name()}\n");
            fwrite($f, "TeamCarId={$this->TeamCar->id()}\n");
        } else {
            fwrite($f, "TEAM=\n");
            fwrite($f, "TeamCarId=0\n");
        }
        if ($this->Spectator) {
            fwrite($f, "SPECTATOR_MODE=1\n");
        } else {
            fwrite($f, "SPECTATOR_MODE=0\n");
        }
    }
}
