<?php
  class cConfig {

    private $DefaultTemplate = "acswui";
    private $LogPath = "../http-logs/";
    private $LogDebug = "true";
    private $RootPassword = '$2y$10$wksgzsvgQ0Lfcl3LS4oVyuVwboFz35gU4dCFb789cSLeLfcWLbjBq';

    // this allows read-only access to private properties
    public function __get($name) {
      return $this->$name;
    }
  }
?>
