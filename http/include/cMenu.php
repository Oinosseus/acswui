<?php

class cMenu {

    public $Menus = array();
    public $Name = "";
    public $Active = false;
    public $ContentDirectory = "";
    public $ClassName = "";
    public $Url = "";

    public function __construct($name = "", $active = false) {
        $this->Name   = $name;
        $this->Active = $active;
    }

}

?>
