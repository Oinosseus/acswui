<?php

namespace Core;

/**
 * This class represents an item in entry_list.ini for the acServer
 */
class EntryListItem {

    private $CarSkin = NULL;
    private $RSerRegistration = NULL;
    private $Users = NULL;
    private $TeamCar = NULL;
    private $FixedSetup = NULL;
    private $ForceDriver = NULL;
    private $ForceRSerClass = NULL;
    // private $Spectator = FALSE;

    /**
     * @param $skin The CarSkin object that shall be used for the entry list item
     * @param $rser_registration If given, this information will be added to the entry list
     */
    public function __construct(\DbEntry\CarSkin $skin,
                                ?\DbEntry\RSerRegistration $rser_registration=NULL) {
        $this->CarSkin = $skin;
        $this->RSerRegistration = $rser_registration;
    }


    // @param $fixed_setup A string with the fixed setup for this entry list item
    public function addFixedSetup(string $fixed_setup) {
        $this->FixedSetup = $fixed_setup;
    }


    /**
     * Assign driver(s) to this car.
     * This could be either multiple User objects,
     * or one TeamCar object.
     *
     * When adding a TeamCar while previously users or a different TeamCar were added, this generates an error.
     * When adding a User while previously a TeamCar was assigned, this generates an error.
     *
     * To add multiple users, call this multiple times.
     *
     * If $driver is NULL, nothing is done.
     *
     * @param $driver Either User or TeamCar object.
     */
    public function addDriver(\DbEntry\User|\DbEntry\TeamCar|NULL $driver) {

        // ignore this
        if ($driver === NULL) {
            return;

        // add a user
        } else if (is_a($driver, "\DbEntry\User")) {
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
     * Forces the entry to this RSerClass
     */
    public function forceClass(\DbEntry\RSerClass $class) {
        $this->ForceRSerClass = $class;
    }


    /**
     * When this is called, the actual users are ignored and
     * driver-name and Steam64GUID is overwritten
     * @param $driver_name The name of the driver that shall be visible in the entry-list
     * @param $steam64guid The Steam64GUID that shall be set in the entry-list (can be any string - also multiple GUIDs)
     */
    public function forceDriver(string $driver_name, string $steam64guid) {
        $this->ForceDriver = [$driver_name, $steam64guid];
    }


    // //! @param §spectator Defines if SPECTATOR in entry list shall be set or not
    // public function setSpectator(bool $spectator) {
    //     $this->Spectator = $spectator;
    // }


    //! @return A list of User objects of the occupation (can be empty)
    public function users() : array {
        return $this->Users;
    }


    /**
     * Write the item to a file
     * @param $f Is an writeable-opened finle handler
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

        if ($this->ForceRSerClass)
            fwrite($f, "RSerClass={$this->ForceRSerClass->id()}\n");
        else if ($this->RSerRegistration)
            fwrite($f, "RSerClass={$this->RSerRegistration->class()->id()}\n");
        else
            fwrite($f, "RSerClass=0\n");

        if ($this->RSerRegistration)
            fwrite($f, "RSerRegistration={$this->RSerRegistration->id()}\n");
        else
            fwrite($f, "RSerRegistration=0\n");

        // throws error from AC-Server-Wrapper
        // if ($this->Spectator) {
        //     fwrite($f, "SPECTATOR_MODE=1\n");
        // } else {
        //     fwrite($f, "SPECTATOR_MODE=0\n");
        // }

        if ($this->FixedSetup) {
            // write fixed setup file info
            $fs_file = "fixed_setup_{$id}.ini";
            fwrite($f, "FIXED_SETUP={$fs_file}\n");

            // create fixed setup file
            $fd_dir = dirname(stream_get_meta_data($f)['uri']);
            $fs_f = fopen("$fd_dir/../setups/$fs_file", "w");
            fwrite($fs_f, $this->FixedSetup);
            fwrite($fs_f, "\n");
            fclose($fs_f);

        }
    }
}
