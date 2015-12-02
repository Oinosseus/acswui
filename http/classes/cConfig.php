<?php
  class cConfig {

    private $DefaultTemplate = "acswui";
    private $LogPath = "../http-logs/";
    private $LogDebug = "true";

    // this allows read-only access to private properties
    public function __get($name) {
      return $this->$name;
    }
  }
?>
