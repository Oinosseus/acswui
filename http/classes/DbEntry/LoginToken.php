<?php

namespace DbEntry;

/**
 * Cached wrapper to databse table
 */
class LoginToken extends DbEntry {


    /**
     * @param $id Database table id
     */
    protected function __construct($id) {
        parent::__construct("LoginTokens", $id);
    }


    /**
     * Create a new Login Token for the current logged user.
     * The token will be created in the database and saved with cookie on the remote client
     * @return The new created LoginToken object
     */
    public static function createNew() {
        $user = \Core\UserManager::loggedUser();
        if ($user === NULL) {
            \Core\Log::error("Cannot create token for not logged user!");
            return NULL;
        }

        // create unique token
        for ($token = bin2hex(random_bytes(25)); LoginToken::fromToken($token) !== NULL; );
        $timestamp = \Core\Database::dateTime2timestamp(\Core\Core::now());
        $password = bin2hex(random_bytes(25));

        $db_columns = array();
        $db_columns['User'] = $user->id();
        $db_columns['Token'] = $token;
        $db_columns['Password'] = password_hash($password,  PASSWORD_DEFAULT);
        $db_columns['Timestamp'] = $timestamp;

        $id = \Core\Database::insert("LoginTokens", $db_columns);
        setcookie("ACswuiLoginToken",
                  $token,
                  time() + 3600 * 24 * \Core\ACswui::getPAram("UserLoginTokenExpire"),
                  "/",
                  $_SERVER['HTTP_HOST'],
                  TRUE,
                  TRUE);
        setcookie("ACswuiLoginPassword",
                  $password,
                  time() + 3600 * \Core\ACswui::getPAram("UserLoginTokenExpire"),
                  "/",
                  $_SERVER['HTTP_HOST'],
                  TRUE,
                  TRUE);

        return LoginToken::fromId($id);
    }


    /**
     * Deletes a token.
     * This should be called when a user actively performs a logout.
     */
    public function delete() {
        $this->deleteFromDb();
    }


    //! @return TRUE if the LoginToken is expired
    public function expired() {
        $expire_hours = $this->user()->getParam("UserLoginTokenExpire");
        $expire_dt = \Core\Core::now()->sub(new \DateInterval("PT{$expire_hours}H"));
        return $this->timestamp() < $expire_dt;
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("LoginTokens", "LoginToken", $id);
    }


    /**
     * @return A LoginToken object created from a token (can be NULL if token is invalid)
     */
    public static function fromToken(string $token) {
        $res = \Core\Database::fetch("LoginTokens", ['Id'], ['Token'=>$token]);
        if (count($res) == 1) {
            return LoginToken::fromId($res[0]['Id']);
        } else {
            return NULL;
        }
    }


    /**
     * Reads the token from saved cookie at the remote client.
     * If the token could not be verified (by Identification), the NULL is returned.
     *
     * @return A LoginToken object or NULL
     */
    public static function fromCurrentUser () {
        if (!array_key_exists("ACswuiLoginToken", $_COOKIE)) return NULL;

        // get LoginToken object
        $token = $_COOKIE["ACswuiLoginToken"];
        $lt = LoginToken::fromToken($token);
        if ($lt === NULL) return NULL;

        // verify identification
        if (!$lt->valid()) return NULL;

        return $lt;
    }


    //! @return A DateTime object of the Timestamp
    public function timestamp() {
        return \Core\Database::timestamp2DateTime($this->timestampStr());
    }


    //! @return The timestamp of the token as string
    public function timestampStr() {
        return $this->loadColumn("Timestamp");
    }


    //! @return The assigned User object
    public function user() {
        return User::fromId((int) $this->loadColumn("User"));
    }


    //! @return verifies if current remote client is allowed to use the token
    public function valid() {
        if (!array_key_exists("ACswuiLoginPassword", $_COOKIE)) return FALSE;
        $password = $_COOKIE["ACswuiLoginPassword"];
        if (!password_verify($password, $this->loadColumn("Password"))) return FALSE;
        if ($this->expired()) return FALSE;
        return TRUE;
    }
}
