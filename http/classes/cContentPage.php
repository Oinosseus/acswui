<?php

abstract class cContentPage {

    public $ContentName        = "";
    public $ParentContentClass = "";
    public $TextDomain         = "messages";

    abstract public function getHtml();

}

?>
