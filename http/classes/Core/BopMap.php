<?php

namespace Core;

/**
 * Stores Ballance Of Performance data
 * BOP can be assigned to:
 *  - cars (for car-class BOP)
 *  - drivers (for skill BOP)
 *  - teams (for skill BOP)
 */
class BopMap {

    private $BopRSerClassBallast = array();
    private $BopRSerClassRestrictor = array();
    private $BopCarBallast = array();
    private $BopCarRestrictor = array();
    private $BopUserBallast = array();
    private $BopUserRestrictor = array();
    private $BopTeamcarBallast = array();
    private $BopTeamcarRestrictor = array();

    private $MaxBallast = 0;


    //! @return the maximum ballast that is assigned
    public function maxBallast() : int {
        return $this->MaxBallast;
    }


    /**
     * Set a ballast and restrictor BOP.
     *
     * Finally the BOP for a car will be added to the BOP of driver/teamcar (whatever is higher)
     * Multiple parameters can be given at the same time (The ballast/restrictor will be assigned to all of them)
     *
     * @param $ballast The BOP weight
     * @param $restrictor The BOP restrictor
     * @param $driver A Car, User or TeamCar where the BOP shall be assigned to. If NULL, the BOP is assigned to foregin drivers
     */
    public function update(int $ballast, int $restrictor,
                           \DbEntry\RSerClass|\DbEntry\Car|\DbEntry\User|\DbEntry\TeamCar $driver = NULL) {

        // BOP Car
        if (is_a($driver, "\DbEntry\Car")) {
            $this->BopCarBallast[$driver->model()] = $ballast;
            $this->BopCarRestrictor[$driver->model()] = $restrictor;

        // BOP User
        } else if (is_a($driver, "\DbEntry\User")) {
            $this->BopUserBallast[$driver->steam64GUID()] = $ballast;
            $this->BopUserRestrictor[$driver->steam64GUID()] = $restrictor;

        // BOP TeamCar
        } else if (is_a($driver, "\DbEntry\TeamCar")) {
            $this->BopTeamcarBallast[$driver->id()] = $ballast;
            $this->BopTeamcarRestrictor[$driver->id()] = $restrictor;

        // BOP RSerClass
        } else if (is_a($driver, "\DbEntry\RSerClass")) {
            $this->BopRSerClassBallast[$driver->id()] = $ballast;
            $this->BopRSerClassRestrictor[$driver->id()] = $restrictor;

        // BOP foreigners
        } else if ($driver === NULL) {
            $this->BopUserBallast["OTHER"] = $ballast;
            $this->BopUserRestrictor["OTHER"] = $restrictor;
        }

        // remember maximum ballast
        if ($ballast > $this->MaxBallast) $this->MaxBallast = $ballast;
    }


    /**
     * Export the BOP settings to a acswui_udp_plugin_*.ini file
     * The file must be opened and writeable
     * @param $file_handle The handle to an opened, writeable file
     */
    public function writeACswuiUdpPluginIni($file_handle) {

        $exports = array();
        $exports['BopRSerClassBallast'] = $this->BopCarBallast;
        $exports['BopRSerClassRestrictor'] = $this->BopCarRestrictor;
        $exports['BopCarBallast'] = $this->BopCarBallast;
        $exports['BopCarRestrictor'] = $this->BopCarRestrictor;
        $exports['BopUserBallast'] = $this->BopUserBallast;
        $exports['BopUserRestrictor'] = $this->BopUserRestrictor;
        $exports['BopTeamcarBallast'] = $this->BopTeamcarBallast;
        $exports['BopTeamcarRestrictor'] = $this->BopTeamcarRestrictor;

        foreach ($exports as $key=>$data) {
            fwrite($file_handle, "\n[{$key}]\n");
            foreach ($data as $bop_key=>$bop_value)
                fwrite($file_handle, "{$bop_key}={$bop_value}\n");
        }

    }
}
