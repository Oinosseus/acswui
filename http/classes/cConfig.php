<?php
  class cConfig {

    // basic constants
    private $DefaultTemplate = "acswui";
    private $LogPath = '../http-logs/';
    private $LogDebug = "true";
    private $RootPassword = '$2y$10$1cFoa/UkkjFqJWupGMY98.XVRKBD2FkghTMDP.yNCPQDTv8rpn9J.';

    // database constants
    private $DbType = "MySQL";
    private $DbHost = "localhost";
    private $DbDatabase = "acswui";
    private $DbPort = "3306";
    private $DbUser = "acswui";
    private $DbPasswd = "acswui";

    // this allows read-only access to private properties
    public function __get($name) {
      return $this->$name;
    }
  }
?>
