<?php

declare(strict_types=1);
namespace Compound;

/**
 * This Class offers a summary for multiple SessionPenalty objects
 */
class SessionPenaltiesSum {

    private $Penalties = array();

    /**
     * This will request \DbEntry\SessionPenalty::listPenalties()
     *
     * When $driver is a User object, then the user is ignored for TeamCars (only penalties for single driver)
     *
     * @param $session The requested session
     * @param $driver If set, only penalties for a certain driver are listed
     */
    public function __construct(\DbEntry\Session $session,
                                \Compound\Driver $driver=NULL) {
        $this->Penalties = \DbEntry\SessionPenalty::listPenalties($session, $driver);
    }

}
