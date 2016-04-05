<?php
  class cConfig {

    // basic constants
    private $DefaultTemplate = "acswui";
    private $LogPath = '../http-logs/';
    private $LogDebug = "false";
    private $RootPassword = '$2y$10$5faNoe/1BMZEfCr9AW3N5OSLQtodDC2FUB3P.ZKGkWSnXbQhfHI.C';
    private $GuestGroup = 'Visitor';

    // database constants
    private $DbType = "MySQL";
    private $DbHost = "localhost";
    private $DbDatabase = "acswui";
    private $DbPort = "3306";
    private $DbUser = "acswui";
    private $DbPasswd = "acswui";

    // server_cfg
    private $SrvCfg_Server = ['NAME' => 'acswui', 'MAX_CLIENTS' => '15', 'RACE_OVER_TIME' => '20', 'ALLOWED_TYRES_OUT' => '3', 'UDP_PORT' => '9600', 'TCP_PORT' => '9600', 'HTTP_PORT' => '8081', 'PASSWORD' => 'acswui', 'LOOP_MODE' => '0', 'REGISTER_TO_LOBBY' => '1', 'PICKUP_MODE_ENABLED' => '1', 'SLEEP_TIME' => '1', 'VOTING_QUORUM' => '75', 'VOTE_DURATION' => '20', 'BLACKLIST_MODE' => '0', 'CLIENT_SEND_INTERVAL_HZ' => '15', 'ADMIN_PASSWORD' => 'acswui_admin', 'QUALIFY_MAX_WAIT_PERC' => '120'];
    private $SrvCfg_DynTrack = [];
    private $SrvCfg_Booking = [];
    private $SrvCfg_Practice = [];
    private $SrvCfg_Qualify = [];
    private $SrvCfg_Race = [];
    private $SrvCfg_Weather = [];

    // this allows read-only access to private properties
    public function __get($name) {
      return $this->$name;
    }
  }
?>
