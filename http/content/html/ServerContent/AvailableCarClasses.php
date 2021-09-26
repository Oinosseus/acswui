<?php

namespace Content\Html;

class AvailableCarClasses extends \core\HtmlContent {

    private $CanEdit = FALSE;

    public function __construct() {
        parent::__construct(_("Car Classes"), _("Available Car Classes"), 'ServerContent');
        $this->requirePermission("ViewServerContent_CarClasses");
    }

    public function getHtml() {
        $this->CanEdit = \Core\UserManager::permitted("CarClass_Edit");
        $html = "";

        // create new CarClass
        if ($this->CanEdit && array_key_exists("Action", $_POST) && $_POST['Action'] == "NewCarClass") {
            \DbEntry\CarClass::createNew(_("New Car Class"));
        }

        foreach (\DbEntry\CarClass::listClasses() as $cc) {
            $html .= $cc->html();
        }

        if ($this->CanEdit) {
            $html .= "<form method=\"post\">";
            $html .= "<input type=\"hidden\" name=\"Action\" value=\"NewCarClass\">";
            $html .= "<button type=\"submit\">" . _("New Car Class") . "</button>";
            $html .= "</form>";
        }

        return $html;
    }
}
