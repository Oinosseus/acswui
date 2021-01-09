<?php

class CollisionType {
    const Env = 0;
    const Car = 1;
}



/**
 * Common base class for CollisionEnv and CollisionCar
 */
abstract class Collision {
    private $Id = NULL;
    private $Type = NULL;
    private $Session = NULL;
    private $CarSkin = NULL;
    private $User = NULL;
    private $Speed = NULL;
    private $OtherUser = NULL;
    private $OtherCarSkin = NULL;
    private $Timestamp = NULL;


    /**
     * @param $id database table row Id
     * @param $type CollisionType
     * @param $session The according Session object (if already known)
     */
    public function __construct(int $id, $type, Session $session=NULL) {
        $this->Id = $id;
        $this->Type = $type;
        $this->Session = $session;
    }


    //! @return The according CarSkin object
    public function carSkin() {
        if ($this->CarSkin === NULL) $this->updateDb();
        return $this->CarSkin;
    }


    //! @return The CarSkin object of the collided user (NULL for CollisionEnv)
    public function otherCarSkin() {
        if ($this->Type !== CollisionType::Car) return NULL;
        if ($this->OtherCarSkin === NULL) $this->updateDb();
        return $this->OtherCarSkin;
    }


    //! @return The User object of the collided user (NULL for CollisionEnv)
    public function otherUser() {
        if ($this->Type !== CollisionType::Car) return NULL;
        if ($this->OtherUser === NULL) $this->updateDb();
        return $this->OtherUser;
    }


    //! @return The according Session object
    public function session() {
        if ($this->Session === NULL) $this->updateDb();
        return $this->Session;
    }


    //! @return The collision speed
    public function speed() {
        if ($this->Speed === NULL) $this->updateDb();
        return $this->Speed;
    }


    //! @return Either CollisionType::Car or CollisionType::Env
    public function type() {
        return $this->Type;
    }


    private function updateDb() {
        global $acswuiDatabase;
        global $acswuiLog;

        // CollisionEnv
        if ($this->Type == CollisionType::Env) {
            $cols = ['Id', 'Session', 'CarSkin', 'User', 'Speed', 'Timestamp'];
            $res = $acswuiDatabase->fetch_2d_array("CollisionEnv", $cols, ['Id'=>$this->Id]);
            if (count($res) !== 1) {
                $acswuiLog->logError("Cannot find CollisionEnv.Id=" . $this->Id);
                return;
            }

            if ($this->Session !== NULL && $res[0]['Session'] != $this->Session->id()) {
                $msg = "Session mismatch, expect Session=" . $this->Session->id();
                $msg .= ", got Session=" . $res[0]['Session'];
                $acswuiLog->logError($msg);
                return;
            }

            if ($this->Session === NULL) {
                $this->Session = new Session($res[0]['Id']);
            }
            $this->CarSkin = new CarSkin($res[0]['CarSkin']);
            $this->User = new User($res[0]['User']);
            $this->Speed = $res[0]['Speed'];
            $this->OtherUser = NULL;
            $this->OtherCarSkin = NULL;
            $this->Timestamp = new DateTime($res[0]['Timestamp']);


        // CollisionCar
        } else if ($this->Type == CollisionType::Car) {
            $cols = ['Id', 'Session', 'CarSkin', 'User', 'Speed', 'OtherUser', 'OtherCarSkin', 'Timestamp'];
            $res = $acswuiDatabase->fetch_2d_array("CollisionCar", $cols, ['Id'=>$this->Id]);
            if (count($res) !== 1) {
                $acswuiLog->logError("Cannot find CollisionCar.Id=" . $this->Id);
                return;
            }

            if ($this->Session !== NULL && $res[0]['Session'] != $this->Session->id()) {
                $msg = "Session mismatch, expect Session=" . $this->Session->id();
                $msg .= ", got Session=" . $res[0]['Session'];
                $acswuiLog->logError($msg);
                return;
            }

            if ($this->Session === NULL) {
                $this->Session = new Session($res[0]['Id']);
            }
            $this->CarSkin = new CarSkin($res[0]['CarSkin']);
            $this->User = new User($res[0]['User']);
            $this->Speed = $res[0]['Speed'];
            $this->OtherUser = new User($res[0]['OtherUser']);
            $this->OtherCarSkin = new CarSkin($res[0]['OtherCarSkin']);
            $this->Timestamp = new DateTime($res[0]['Timestamp']);


        // unknown collision type
        } else {
            $msg = "Unknown CollisionType '" + $this->Type . "'";
            $acswuiLog->logError($msg);
        }
    }


    //! @return The according User object
    public function user() {
        if ($this->User === NULL) $this->updateDb();
        return $this->User;
    }
}



/*
 * Cached wrapper to database CollisionEnv table
 */
class CollisionEnv extends Collision {

    /**
     * @param $id database table row Id
     * @param $session The according Session object (if already known)
     */
    public function __construct(int $id) {
        parent::__construct($id, CollisionType::Env);
    }
}



/*
 * Cached wrapper to database CollisionEnv table
 */
class CollisionCar extends Collision {

    /**
     * @param $id database table row Id
     * @param $session The according Session object (if already known)
     */
    public function __construct(int $id, Session $session=NULL) {
        parent::__construct($id, CollisionType::Car, $session);
    }
}


?>
