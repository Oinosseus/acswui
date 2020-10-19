<?php

abstract class cContentPage {

    // public properties
    public $ContentName        = "";
    public $ParentContentClass = "";
    public $TextDomain         = "messages";
    public $PageTitle          = "";
    public $RequireRoot        = false;
    public $RequirePermissions = [];
    public $MenuName           = "";

    // private properties
    private $menu = Null;


    public function setMenu($m) {
        $this->menu = $m;
    }

    public function getRelPath() {

        // get global data
        global $acswuiLog;

        // check if menu available
        if ($this->menu === Null) {
            $acswuiLog->logError('$menu is not available, probably setMenu() not called before!');
            return "";
        } else {
            return "contents/" . $this->menu->ContentDirectory;
        }
    }

    abstract public function getHtml();

}

?>
