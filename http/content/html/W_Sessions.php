<?php

namespace Content\Html;

class W_Sessions extends \core\HtmlContent {

    private $CanControl = array(); // key=SlotId, value=True/False

    public function __construct() {
        parent::__construct(_("Sessions"),  "");
        $this->requirePermission("Sessions_View");
    }


    public function getHtml() {
        for ($i=1; $i <= \Core\Config::ServerSlotAmount; ++$i)
            $this->CanControl[$i] = \Core\UserManager::loggedUser()->permitted("Sessions_Control_Slot$i");

        $html = "";

        if (array_key_exists("Action", $_POST)) {
            $slot = \Core\ServerSlot::fromId($_POST["SlotId"]);
            if ($this->CanControl[$slot->id()]) {

                if ($_POST['Action'] == "StopSlot") {
                    $slot->stop();
                    sleep(2);

                } else if ($_POST['Action'] == "StartSlot") {
                    $trasck = \DbEntry\Track::fromId($_POST['Track']);
                    $preset = \DbEntry\ServerPreset::fromId($_POST['ServerPreset']);
                    $car_class = \DbEntry\CarClass::fromId($_POST['CarClass']);
                    $slot->start($trasck, $car_class, $preset);
                    sleep(2);
                }
            }
        }


        for ($i=1; $i <= \Core\Config::ServerSlotAmount; ++$i) {
            $slot = \Core\ServerSlot::fromId($i);
            $html .= "<h1>" . $slot->parameterCollection()->child('AcServerGeneralName')->valueLabel() . "</h1>";

            $html .= $this->newHtmlForm("POST");
            $html .= "<input type=\"hidden\" name=\"SlotId\" value=\"" . $slot->id() . "\">";

            if ($slot->online()) {
                $session = $slot->currentSession();
                if ($session) {
                    $html .= _("Session") . ": " . $session->htmlName() . "<br>";
                    $html .= _("Start") . ": " . \Core\UserManager::currentUser()->formatDateTime($session->timestamp()) . "<br>";
                    if ($session->serverPreset()) $html .= _("Server Preset") . ": " . $session->serverPreset()->name() . "<br>";
                    if ($session->carClass()) $html .= _("Car Class") . ": " . $session->carClass()->htmlName() . "<br>";
                    $html .= $session->track()->html();
                }
                if ($this->CanControl[$slot->id()])
                    $html .= "<br><button type=\"submit\" name=\"Action\" value=\"StopSlot\">" . _("Stop") . "</button>";

            } else if ($this->CanControl[$slot->id()]) {

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
