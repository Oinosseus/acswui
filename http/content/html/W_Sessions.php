<?php

namespace Content\Html;

class W_Sessions extends \core\HtmlContent {

    private $CanControl = array(); // key=SlotId, value=True/False
    private $CanKill = array(); // key=SlotId, value=True/False

    public function __construct() {
        parent::__construct(_("Sessions"),  "");
        $this->requirePermission("Sessions_View");
    }


    public function getHtml() {
        $current_user = \Core\UserManager::currentUser();
        for ($i=1; $i <= \Core\Config::ServerSlotAmount; ++$i) {
            $this->CanControl[$i] = $current_user->permitted("Sessions_Control_Slot$i");
            $this->CanKill[$i] = $current_user->permitted("Sessions_Kill_Slot$i");
        }


        $html = "";

        if (array_key_exists("Action", $_POST)) {
            $slot = \Core\ServerSlot::fromId($_POST["SlotId"]);

            if ($this->CanControl[$slot->id()] && $_POST['Action'] == "StopSlot") {
                $slot->stop();
                sleep(2);
                $html .= $this->getHtmlShowSlotStatus();

            } else if ($this->CanKill[$slot->id()] && $_POST['Action'] == "KillSlot") {
                $html .= $this->getHtmlAskKillSlot($slot);

            } else if ($this->CanKill[$slot->id()] && $_POST['Action'] == "KillSlotReallyDo") {
                $slot->stop(TRUE);
                sleep(2);
                $html .= $this->getHtmlShowSlotStatus();

            } else if ($this->CanControl[$slot->id()] && $_POST['Action'] == "StartSlot") {

                // basic data
                $track = \DbEntry\Track::fromId($_POST['Track']);
                $preset = \DbEntry\ServerPreset::fromId($_POST['ServerPreset']);
                $car_class = \DbEntry\CarClass::fromId($_POST['CarClass']);

                // create EntryList
                $el = new \Core\EntryList();
                $el->addTvCar();
                $el->fillSkins($car_class, $track->pitboxes());
                $el->reverse();

                // cretae BopMap
                $bm = new \Core\BopMap();
                foreach ($car_class->cars() as $c) {
                    $b = $car_class->ballast($c);
                    $r = $car_class->restrictor($c);
                    $bm->update($b, $r, $c);
                }

                $slot->start($track, $preset, $el, $bm);
                sleep(2);
                \Core\Discord::messageManualStart($slot, $track, $car_class, $preset);
                $html .= $this->getHtmlShowSlotStatus();

            } else {
                \Core\Log::warning("Denying {$current_user} from Action={$_POST['Action']}");
            }

        } else {
            $html .= $this->getHtmlShowSlotStatus();
        }



        return $html;
    }


    private function getHtmlAskKillSlot(\Core\ServerSlot $slot) {
        if ($slot == NULL) return "";

        // create HTML
        $html = "";
        $html .= "<strong>E{$slot->name()}</strong><br>";
        $html .= _("Really KILL the server?");
        $html .= "<br><br>";
        $html .= "<div class=\"KillSlotForm\">";

        // delete button
        $html .= $this->newHtmlForm("POST");
        $html .= "<input type=\"hidden\" name=\"SlotId\" value=\"{$slot->id()}\">";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"KillSlotReallyDo\">" . _("Kill") . "</button>";
        $html .= "</form>";

        $html .= " ";

        // cancel button
        $html .= $this->newHtmlForm("GET");
        $html .= "<button type=\"submit\">" . _("Cancel") . "</button>";
        $html .= "</form>";

        $html .= "</div>";

        return $html;
    }


    private function getHtmlShowSlotStatus() {
        $html = "";

        for ($i=1; $i <= \Core\Config::ServerSlotAmount; ++$i) {
            $slot = \Core\ServerSlot::fromId($i);
            $html .= "<h1>" . $slot->parameterCollection()->child('AcServerGeneralName')->valueLabel() . "</h1>";

            $html .= $this->newHtmlForm("POST");
            $html .= "<input type=\"hidden\" name=\"SlotId\" value=\"" . $slot->id() . "\">";

            if ($slot->online()) {

                // CM Join Link
                $html .= $slot->htmlJoin(). "<br>";

                $session = $slot->currentSession();
                if ($session) {

                    $html .= "<div class=\"Infolet\">";
                    $html .= _("Session") . ": " . $session->htmlName() . "<br>";
                    $html .= _("Start") . ": " . \Core\UserManager::currentUser()->formatDateTime($session->timestamp()) . "<br>";
                    if ($session->serverPreset()) $html .= _("Server Preset") . ": " . $session->serverPreset()->name() . "<br>";
                    $html .= $session->track()->html();
                    $html .= "</div>";

                    // list online drivers
                    foreach ($slot->driversOnline() as $entry) {
                        $html .= "<div class=\"Infolet\">";
                        $html .= $entry->getHtml();
                        if ($entry->carSkin()) {
                            $html .= "<br>" . $entry->CarSkin()->html();
                        }
                        $html .= "</div>";
                    }
                }
                if ($this->CanControl[$slot->id()])
                    $html .= "<br><button type=\"submit\" name=\"Action\" value=\"StopSlot\">" . _("Stop") . "</button>";
                if ($this->CanKill[$slot->id()])
                    $html .= " <button type=\"submit\" name=\"Action\" value=\"KillSlot\">" . _("Kill") . "</button>";

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
