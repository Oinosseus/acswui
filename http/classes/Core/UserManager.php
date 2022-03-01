<?php

namespace Core;

class UserManager {
    private static $CurrentLoggedUser = NULL;

    //! forbid direct construction
    private function __construct() {}


    /**
     * In contrary to loggedUser() this will always return a valid User object.
     * Even if currently no user is logged in, this will return a default user
     * @return A User object that represents the current user (never NULL)
     */
    public static function currentUser() {
        if (UserManager::$CurrentLoggedUser === NULL) {
            return \DbEntry\User::guestUser();
        } else {
            return UserManager::$CurrentLoggedUser;
        }
    }


    //! @return TRUE if root user is currently logged in
    public static function loggedIsRoot() {
        $user = UserManager::loggedUser();
        if ($user !== NULL) return $user->isRoot();
        return FALSE;
    }


    //! @return The User object of the currently logged in user (can be NULL)
    public static function loggedUser() {
        return UserManager::$CurrentLoggedUser;
    }


    //! Set a user as logged in
    private static function login(int $user_id) {
        UserManager::$CurrentLoggedUser = \DbEntry\User::fromId($user_id);
        $_SESSION['CurrentLoggedUserId'] = UserManager::$CurrentLoggedUser->id();

        // update last login time
        $t_now = new \DateTime("now", new \DateTimezone(Config::LocalTimeZone));
        $login_time = Database::dateTime2timestamp($t_now);
        Database::update("Users", $user_id, ['LastLogin'=>$login_time]);
    }


    //! Set the current user as logged out
    private static function logout() {
        UserManager::$CurrentLoggedUser = NULL;
        $_SESSION['CurrentLoggedUserId'] = NULL;
    }


    //! @return A <a> html element for login or logout (depends if a user is currently logged in)
    public static function htmlLogInOut() {
        $html = "";

        if (UserManager::loggedUser()) {
            $html .= "<a href=\"index.php?UserManager=Logout\">" . _("Logout") . " " . UserManager::$CurrentLoggedUser->name() . "</a>";
        } else {
            $soid = new \SteamOpenID\SteamOpenID(UserManager::steamOpenIDReturnTo());
            $html .= "<a href=\"" . $soid->getAuthUrl() . "\" class=\"UserManagerLoginLink\">";
            $html .= "<img src=\"https://community.akamai.steamstatic.com/public/images/signinthroughsteam/sits_01.png\" title=\"" . _("Login via Steam") . "\">";
            $html .= "</a>";
        }
        return $html;
    }


    //! initialize
    public static function initialize() {

        // response from SteamOpenID
        $client = new \SteamOpenID\SteamOpenID(UserManager::steamOpenIDReturnTo());
        $steam64guid = NULL;
        if ($client->hasResponse()) {
            try {
                $steam64guid = $client->validate();
            } catch (\Exception $e) {
                \Core\Log::warning($e->getMessage());
            }
        }

        // logout
        if (array_key_exists("UserManager", $_GET) && $_GET['UserManager'] == "Logout") {
            UserManager::logout();

        // root login
        } else if (array_key_exists("UserManager", $_GET) && $_GET['UserManager'] == "RootLogin" && array_key_exists("RootPassword", $_POST)) {
            if (password_verify($_POST['RootPassword'], \Core\Config::RootPassword)) {
                UserManager::login(0);
                \Core\Log::debug("Root login");
            } else {
                \Core\Log::warning("Preventing attempted root login with wrong password!");
            }

        // response from Steam OpenID
        } else if ($steam64guid !== NULL) {
            $res = \Core\Database::fetch("Users", ["Id"], ["Steam64GUID"=>$steam64guid]);
            if (count($res) > 1) {
                \Core\Log::error("Multiple users with Steam64GUI '$steam64guid'!");
            } else if (count($res) == 1) {
                UserManager::login($res[0]['Id']);
            }

        // try login from session
        } else if (array_key_exists('CurrentLoggedUserId', $_SESSION)) {
            if ($_SESSION['CurrentLoggedUserId'] !== NULL) {
                UserManager::login($_SESSION['CurrentLoggedUserId']);
            }
        }

    }


    //! @return TRUE if the requested permission is granted to the current user (else FALSE)
    public static function permitted(string $permission) {

        // sanity check
        if (!in_array($permission, \DbEntry\Group::listPermissions())) {
            \Core\Log::error("Unknown permission: '$permission'!");
            return FALSE;
        }

        // get current logged user
        $user = UserManager::loggedUser();
        if ($user === NULL) {  // unlogged user
            $group = \DbEntry\Group::fromName(\Core\Config::GuestGroup);
            if ($group === NULL) return FALSE;
            return $group->grants($permission);

        } else {  // logged user
            return $user->permitted($permission);
        }

        \Core\Log::error("Script executionh should never end up here :-(");
        return FALSE;
    }


    private static function steamOpenIDReturnTo() {
        $url  = (array_key_exists('HTTPS', $_SERVER) && $_SERVER['HTTPS'] != "") ? "https://" : "http://";
        $url .= (array_key_exists('HTTP_HOST', $_SERVER)) ? $_SERVER['HTTP_HOST'] : "";
        $url .= (array_key_exists('SCRIPT_NAME', $_SERVER)) ? $_SERVER['SCRIPT_NAME'] : "";
        return $url;
    }
}
