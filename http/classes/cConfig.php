<?php
  class cConfig {

    private $DefaultTemplate = "acswui";
    private $LogPath = "../http-logs/";
    private $LogDebug = "true";
    private $RootPassword = "b'$2y$10$Wr7cyTjVC3CbZFVu7lZtTeupFS4ixXAo0s.McxL9y9pKEWLfnYxVS'";

    // this allows read-only access to private properties
    public function __get($name) {
      return $this->$name;
    }
  }
?>
