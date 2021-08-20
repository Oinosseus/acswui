<?php

namespace DbEntry;

/**
 * Cached wrapper to car databse Tracks table element
 */
class User extends DbEntry { #implements JsonSerializable {

    private $Steam64GUID = NULL;
    private $Color = NULL;
    private $Privacy = NULL;
    private $Locale = NULL;

    private static $UserList = NULL;
    private static $CurrentLoggedUser = NULL;


    /**
     * @param $id Database table id
     */
    protected function __construct($id) {
        parent::__construct("Users", $id);
    }


    //! @return The User object of the currently logged in user (can be NULL)
    public static function currentLogged() {
        return User::$CurrentLoggedUser;
    }


    //! @return The color code for this user as string (eg #12ab9f)
    public function color() {
        if ($this->Color === NULL) {
            $this->Color = $this->loadColumn("Color");
            if ($this->Color == "") $this->Color = "#000000";
        }
        return $this->Color;
    }


    //! Tries to login a user (should be called once from index page)
    public static function initialize() {

        // response from Steam OpenID
        $client = new \SteamOpenID\SteamOpenID(User::steamOpenIDReturnTo());
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
                    User::$CurrentLoggedUser = User::fromId($res[0]['Id']);
                    $_SESSION['CurrentLoggedUserId'] = User::$CurrentLoggedUser->id();
                    return;
                }
            }
        }

        // try login from session
        if (array_key_exists('CurrentLoggedUserId', $_SESSION) && $_SESSION['CurrentLoggedUserId']) {
            User::$CurrentLoggedUser = User::fromId($_SESSION['CurrentLoggedUserId']);
            return;
        }

        // login failed
        $_SESSION['CurrentLoggedUserId'] = NULL;
    }


    /**
     * @return The username that shall be displayed on HTML output (depending on privacy settings)
     */
    public function name() {
        return ($this->privacyFulfilled()) ? $this->loadColumn("Name") : "***";
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("Users", "User", $id);
    }


    //! @return The html code with the login link or the login information
    public static function htmlLogin() {
        $html = "<div class=\"UserLogin\">";

        if (User::currentLogged()) {
            $html .= _("Logged in as") . " <strong>" . User::currentLogged()->name() . "</strong>";
        } else {
            $soid = new \SteamOpenID\SteamOpenID(User::steamOpenIDReturnTo());
            $html .= "Login with <a href=\"" . $soid->getAuthUrl() . "\"><img src=\"https://community.akamai.steamstatic.com/public/images/signinthroughsteam/sits_01.png\"></a>";
        }

        $html .= "</div>";
        return $html;
    }


    //! @return The preferred locale of the user
    public function locale() {
        if ($this->Locale === NULL) {
            $this->Locale = $this->loadColumn('Locale');
        }
        return $this->Locale;
    }


    /**
     * Set a new color for the user
     * @param $color E.g. '#12ab98'
     */
    public function setColor(string $color) {

        if (strlen($color) !== 7 || substr($color, 0, 1) != "#") {
            \Core\Log::error("Malformed color: '$color'!");
            return;
        }

        \Core\Database::update("Users", $this->id(), ['Color'=>$color]);
        $this->Color = $color;
    }



    public function setLocale(string $new_locale) {

        // sanity check
        if (!in_array($new_locale, \Core\Config::Locales)) {
            $new_locale = "";
        }
        if ($new_locale === $this->Locale) return;

        // update DB
        \Core\Database::update("Users", $this->id(), ['Locale'=>$new_locale]);
        $this->Locale = $new_locale;
    }



    public function setPrivacy(int $privacy) {

        if ($privacy < -1 || $privacy > 1) {
            \Core\Log::error("Invalid privacy value: $privacy!");
            return;
        }

        \Core\Database::update("Users", $this->id(), ['Privacy'=>$privacy]);
        $this->Privacy = $privacy;
    }


    /**
     * Following values are possible:
     * 0 (Private): Absolute privacy (not showing any personal information to public
     * 1 (Community): Only show personal information to logged in users
     * 2 (Public): Allow anyone to see personal information
     * @return The privacy level for this user
     */
    public function privacy() {
        if ($this->Privacy == NULL) $this->Privacy = (int) $this->loadColumn("Privacy");
        return $this->Privacy;
    }


    //! @return TRUE if the requsted privacy level is currenlty fulfuilled
    public function privacyFulfilled() {

        $current_user = User::currentLogged();
        $privacy = $this->privacy();

        if ($current_user && $current_user->id() == $this->id()) {
            return TRUE;
        } else if ($privacy > 2) {
            return TRUE;
        } else if ($privacy == 0 && $current_user) {
            return TRUE;
        }

        return FALSE;
    }


    //! @return The Steam64GUID of the user
    public function steam64GUID() {
        if ($this->Steam64GUID === NULL) $this->Steam64GUID = $this->loadColumn('Steam64GUID');
        return $this->Steam64GUID;
    }


    private static function steamOpenIDReturnTo() {
        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "") ? "https://" : "http://";
        $url .= $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
        return $url;
    }


    //! @return A list of all users
    public static function listUsers() {

        if (User::$UserList === NULL) {
            User::$UserList = array();
            foreach (\Core\Database::fetch("Users", ['Id']) as $u) {
                User::$UserList[] = User::fromId($u['Id']);
            }
        }

        return User::$UserList;
    }
}

?>
