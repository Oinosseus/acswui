<?php
  class cConfig {

    private $DefaultTemplate = "acswui";
    private $LogPath = "../http-logs/";
    private $LogDebug = "true";
    private $RootPassword = "b'\$2y\$10\$CirsTpfN9UV86IZA81ltZ.cbSAwxY28io9xNcr92UMRkm2g.Jlp/a'";

    // this allows read-only access to private properties
    public function __get($name) {
      return $this->$name;
    }
  }
?>
