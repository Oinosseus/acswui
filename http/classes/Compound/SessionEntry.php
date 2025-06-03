<?php

declare(strict_types=1);
namespace Compound;

/**
 * This Class is a overlay structure for drivers that can be either:
 * * single User objects, or
 * * TeamCar objects with multiple users assigned
 */
class SessionEntry {

    private $Session = NULL;
    private $TeamCar = NULL;
    private $User = NULL;
    private $CarSkin = NULL;
    private $Drivers = NULL;

    /**
     * Create a new object from User and/or TeamCar
     *
     * If teamcar is not NULL, then user is igniored.
     *
     * @param $session The session where the entry was mounted (necessary to find team-drivers)
     * @param $teamcar Can be A TeamCar object or the teamcardatabase Id (or NULL if User)
     * @param $user Can be A User object or the user database Id (or NULL if TeamCar)
     * @param $carskin The Carskin which was used for the entry (can be NULL)
     */
    public function __construct(\DbEntry\Session $session,
                                \DbEntry\TeamCar|NULL $teamcar,
                                \DbEntry\User|NULL $user,
                                ?\DbEntry\CarSkin|NULL $carskin=NULL) {

        $this->Session = $session;

        if ($teamcar !== NULL && $teamcar !== 0) {

            if (is_a($teamcar, "\\DbEntry\\TeamCar")) $this->TeamCar = $teamcar;
            else if ($teamcar > 0) $this->TeamCar = \DbEntry\TeamCar::fromId($teamcar);
            else throw new \TypeError("Unexpected type " . gettype($teamcar));

        } else if ($user !== NULL && $user !== 0) {

            if (is_a($user, "\\DbEntry\\User")) $this->User = $user;
            else if ($user > 0) $this->User = \DbEntry\User::fromId($user);
            else throw new \TypeError("Unexpected type ." . gettype($user));
        }

        $this->CarSkin = $carskin;

        // sanity check
        if ($this->User === NULL && $this->TeamCar === NULL) {
            \Core\Log::warning("Both, user ($user) and teamcar ($teamcar) are invalid!");
        }
    }


    //! @return The driven carskin
    public function carSkin() : ?\DbEntry\CarSkin {
        return $this->CarSkin;
    }


    //! @return A User or TeamCar object, which represents the entry
    public function entry() : \DbEntry\User|\DbEntry\TeamCar {
        if ($this->TeamCar) return $this->TeamCar;
        else if ($this->User) return $this->User;
        else return NULL;  // this is not expected
    }


    //! @return A HTML string, representing the driver
    public function getHtml() : string {
        $html = "";
        $user_list = $this->users();

        // image
        if ($this->TeamCar) {
            if (count($user_list) > 1) {
                $html .= $this->TeamCar->team()->html(TRUE, FALSE, TRUE, FALSE) . " ";
            } else {
                $html .= "<small>" . $this->TeamCar->team()->html(TRUE, FALSE, FALSE, TRUE) . "</small> ";
            }
        // } else {
        //     $html .= _("Invalid Entry") . " ";
        }

        // drivers list
        $users_array = array();
        foreach ($user_list as $u) {
            $users_array[] = $u->parameterCollection()->child("UserCountry")->valueLabel() . " " . $u->html();
        }
        if (count($user_list) > 1) {
            $html .= "<div><small>" . implode(", ", $users_array) . "</small></div>";
        } else {
            $html .= "<div>" . implode(", ", $users_array) . "</div>";
        }

        return "<div class=\"CompoundDriver\">{$html}</div>";;
    }


    //! @return A string with that the SessionEntry can be identified
    public function id() {
        $sid = $this->Session->id();
        $uid = ($this->User === NULL) ? 0 : $this->User->id();
        $tid = ($this->TeamCar === NULL) ? 0 : $this->TeamCar->id();
        return "\\Compound\\SessionEntry[$sid,$uid,$tid]";
    }


    //! @return The name of the single driver or the TeamCar
    public function name() : string {
        $name = "";
        if ($this->User !== NULL) $name .= $this->User->name();
        if ($this->TeamCar !== NULL) $name .= $this->TeamCar->name();
        return $name;
    }


    //! @return A list of User objects that are assigned as driver
    public function users() : array {

        // create cache
        if ($this->Drivers === NULL) {

            // create a new list of User objects
            $this->Drivers = array();

            // add single driver
            if ($this->User !== NULL) $this->Drivers[] = $this->User;

            // add team drivers
            if ($this->TeamCar !== NULL) {

                // find all actual drivers
                $query = "SELECT DISTINCT(User) FROM Laps WHERE Session={$this->Session->id()} AND TeamCar={$this->TeamCar->id()};";
                foreach (\COre\Database::fetchRaw($query) as $row) {
                    $this->Drivers[] = \DbEntry\User::fromId((int) $row['User']);
                }
            }
        }

        return $this->Drivers;
    }
}
