<?php

abstract class cContentPage {

    public $ContentName        = "";
    public $ParentContentClass = "";

    abstract public function getHtml();

}

?>
