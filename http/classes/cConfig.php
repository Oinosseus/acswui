<?php

class cConfig {

    // this defines the standard template
    private $DefaultTemplate = "Acswui";

    // this is where logfiles are stored
    // for security reasons this should not be a directory that is accessible by http
    private $LogPath = "../http-logs/";

    // set this to true if debug output should be activated
    private $LogDebug = true;


    // this allows read-only access to private properties
    public function __get($name) {
        return $this->$name;
    }

}

?>
