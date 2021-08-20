<?php

namespace Core;

class LoginManager {
    private static $CurrentLoggedUser = NULL;

    //! forbid direct construction
    private function __construct() {}


    //! @return The User object of the currently logged in user (can be NULL)
    public static function loggedUser() {
        return LoginManager::$CurrentLoggedUser;
    }


    //! @return The html code with the login link or the login information
    public static function htmlLogin() {
        $html = "<div class=\"UserLogin\">";

        if (LoginManager::loggedUser()) {
            $html .= _("Logged in as") . " <strong>" . LoginManager::loggedUser()->name() . "</strong>";
        } else {
            $soid = new \SteamOpenID\SteamOpenID(LoginManager::steamOpenIDReturnTo());
            $html .= "Login with <a href=\"" . $soid->getAuthUrl() . "\"><img src=\"https://community.akamai.steamstatic.com/public/images/signinthroughsteam/sits_01.png\"></a>";
        }

        $html .= "</div>";
        return $html;
    }


    //! initialize
    public static function initialize() {

        // response from Steam OpenID
        $client = new \SteamOpenID\SteamOpenID(LoginManager::steamOpenIDReturnTo());
        if ($client->hasResponse()) {
            $steam64guid = NULL;
            try {
                $steam64guid = $client->validate();
            } catch (Exception $e) {
                \Core\Log::warning($e->getMessage());
            }

            if ($steam64guid) {
                $res = \Core\Database::fetch("Users", ["Id"], ["Steam64GUID"=>$steam64guid]);
                if (count($res) > 1) {
                    \Core\Log::warning("Multiple users with Steam64GUI '$steam64guid'!");
                } else if (count($res) == 1) {
                    LoginManager::$CurrentLoggedUser = \DbEntry\User::fromId($res[0]['Id']);
                    $_SESSION['CurrentLoggedUserId'] = LoginManager::$CurrentLoggedUser->id();
                    return;
                }
            }
        }

        // try login from session
        if (array_key_exists('CurrentLoggedUserId', $_SESSION) && $_SESSION['CurrentLoggedUserId']) {
            LoginManager::$CurrentLoggedUser = \DbEntry\User::fromId($_SESSION['CurrentLoggedUserId']);
            return;
        }

        // login failed
        $_SESSION['CurrentLoggedUserId'] = NULL;
    }


    private static function steamOpenIDReturnTo() {
        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "") ? "https://" : "http://";
        $url .= $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
        return $url;
    }
}
