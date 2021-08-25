<?php

namespace Core;

class UserManager {
    private static $CurrentLoggedUser = NULL;

    //! forbid direct construction
    private function __construct() {}


    //! @return The User object of the currently logged in user (can be NULL)
    public static function loggedUser() {
        return UserManager::$CurrentLoggedUser;
    }


    //! Set a user as logged in
    private static function login(int $user_id) {
        UserManager::$CurrentLoggedUser = \DbEntry\User::fromId($user_id);
        $_SESSION['CurrentLoggedUserId'] = UserManager::$CurrentLoggedUser->id();
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
        $client = new \SteamOpenID\SteamOpenID(UserManager::steamOpenIDReturnTo());

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
        } else if ($client->hasResponse()) {
            $steam64guid = NULL;
            try {
                $steam64guid = $client->validate();
            } catch (Exception $e) {
                \Core\Log::warning($e->getMessage());
            }

            if ($steam64guid) {
                $res = \Core\Database::fetch("Users", ["Id"], ["Steam64GUID"=>$steam64guid]);
                if (count($res) > 1) {
                    \Core\Log::error("Multiple users with Steam64GUI '$steam64guid'!");
                } else if (count($res) == 1) {
                    UserManager::login($res[0]['Id']);
                }
            }

        // try login from session
        } else if (array_key_exists('CurrentLoggedUserId', $_SESSION)) {
            if ($_SESSION['CurrentLoggedUserId'] !== NULL) {
                UserManager::login($_SESSION['CurrentLoggedUserId']);
            }
        }

    }


    private static function steamOpenIDReturnTo() {
        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "") ? "https://" : "http://";
        $url .= $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
        return $url;
    }
}
