<?php

namespace Content\Html;

class W_Sessions extends \core\HtmlContent {

    private $CanControl = FALSE;

    public function __construct() {
        parent::__construct(_("Sessions"),  "");
        $this->requirePermission("Sessions_View");
    }


    public function getHtml() {
        $this->CanControl = \Core\UserManager::loggedUser()->permitted("Sessions_Control");
        $html = "";

        if ($this->CanControl && array_key_exists("Action", $_POST)) {
            $slot = \Core\ServerSlot::fromId($_POST["SlotId"]);

            if ($_POST['Action'] == "StopSlot") {
                $slot->stop();

            } else if ($_POST['Action'] == "StartSlot") {
                $trasck = \DbEntry\Track::fromId($_POST['Track']);
                $preset = \DbEntry\ServerPreset::fromId($_POST['ServerPreset']);
                $car_class = \DbEntry\CarClass::fromId($_POST['CarClass']);
                $slot->start($trasck, $car_class, $preset);
            }



        }
        if ($this->CanControl && array_key_exists("StartSlot", $_POST)) {
            $slot = \Core\ServerSlot::fromId($_POST["StartSlot"]);
        }


        for ($i=1; $i <= \Core\Config::ServerSlotAmount; ++$i) {
            $slot = \Core\ServerSlot::fromId($i);
            $html .= "<h1>" . $slot->parameterCollection()->child('AcServerGeneralName')->valueLabel() . "</h1>";

            $html .= $this->newHtmlForm("POST");
            $html .= "<input type=\"hidden\" name=\"SlotId\" value=\"" . $slot->id() . "\">";

            if ($slot->online()) {
                $html .= "<button type=\"submit\" name=\"Action\" value=\"StopSlot\">" . _("Stop") . "</button>";
            } else {

                // car class
                $html .= "<select name=\"CarClass\">";
                foreach (\DbEntry\CarClass::listClasses() as $cc) {
                    $html .= "<option value=\"" . $cc->id() . "\">" . $cc->name() . "</option>";
                }
                $html .= "</select>";
                $html .= "<br>";

                // track select
                $html .= "<select name=\"Track\">";
                foreach (\DbEntry\Track::listTracks() as $t) {
                    $html .= "<option value=\"" . $t->id() . "\">" . $t->name() . "</option>";
                }
                $html .= "</select>";
                $html .= "<br>";

                // select preset
                $html .= "<select name=\"ServerPreset\">";
                foreach (\DbEntry\ServerPreset::listPresets() as $p) {
                    $html .= "<option value=\"" . $p->id() . "\">" . $p->name() . "</option>";
                }
                $html .= "</select>";
                $html .= "<br>";

                // start
                $html .= "<button type=\"submit\" name=\"Action\" value=\"StartSlot\">" . _("Start") . "</button>";
            }

            $html .= "</form>";
        }

        return $html;
    }
}