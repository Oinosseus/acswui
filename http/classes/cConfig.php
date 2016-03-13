<?php
  class cConfig {

    // basic constants
    private $DefaultTemplate = "acswui";
    private $LogPath = '../http-logs/';
    private $LogDebug = "false";
    private $RootPassword = '$2y$10$NBuYODTqKuPme/Uf4bRamufE/L5hHyyR9feakEoKrsHfkkGIuATRu';

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
