<?php

declare(strict_types=1);
namespace Compound;

/**
 * This Class is a overlay structure for drivers that can be either:
 * * single User objects, or
 * * TeamCar objects with multiple users assigned
 */
class Driver {

    private $User = NULL;
    private $TeamCar = NULL;

    /**
     * Create a new object from User and/or TeamCar
     *
     * If teamcar is not NULL, then user is igniored.
     *
     * @param $teamcar_id Can be A TeamCar object or the teamcardatabase Id (or NULL if User)
     * @param $user_id Can be A User object or the user database Id (or NULL if TeamCar)
     */
    public function __construct(\DbEntry\TeamCar|int|NULL $teamcar,
                                 \DbEntry\User|int|NULL $user) {

        if ($teamcar !== NULL && $teamcar !== 0) {

            if (is_a($teamcar, "\\DbEntry\\TeamCar")) $this->TeamCar = $teamcar;
            else if ($teamcar > 0) $this->TeamCar = \DbEntry\TeamCar::fromId($teamcar);
            else throw new \TypeError("Unexpected type " . gettype($teamcar));

        } else if ($user !== NULL && $user !== 0) {

            if (is_a($user, "\\DbEntry\\User")) $this->User = $user;
            else if ($user > 0) $this->User = \DbEntry\User::fromId($user);
            else throw new \TypeError("Unexpected type ." . gettype($user));
        }
    }


    //! @return A User or TeamCar object, which represents the driver
    public function driver() : \DbEntry\User|\DbEntry\TeamCar {
        if ($this->TeamCar) return $this->TeamCar;
        else if ($this->User) return $this->User;
        else return NULL;  // this is not expected
    }


    //! @return A HTML string, representing the driver
    public function getHtml() : string {
        $html = "";

        // image
        if ($this->TeamCar) {
            $html .= $this->TeamCar->team()->html(TRUE, FALSE, TRUE, FALSE);
        } else if ($this->User) {
            $html .= $this->User->parameterCollection()->child("UserCountry")->valueLabel();
        } else {
            \Core\Log::error("I did not expect to end up here ^~^");
        }

        // drivers list
        $users_array = array();
        foreach ($this->users() as $u) $users_array[] = $u->html();
        $html .= "<div>" . implode(", ", $users_array) . "</div>";

        return "<div class=\"CompoundDriver\">{$html}</div>";;
    }


    //! @return A list of User objects that are assigned as driver
    public function users() : array {
        $ret = array();

        if ($this->User) {
            $ret[] = $this->User;
        }

        if ($this->TeamCar) {
            foreach ($this->TeamCar->drivers() as $tmm) {
                $ret[] = $tmm->user();
            }
        }

        return $ret;
    }
}
