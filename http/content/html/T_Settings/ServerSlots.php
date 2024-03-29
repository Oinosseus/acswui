<?php

namespace Content\Html;

class ServerSlots extends \core\HtmlContent {

    private $CurrentSlot = NULL;
    private $CanEdit = FALSE;


    public function __construct() {
        parent::__construct(_("Slots"),  "");
        $this->requirePermission("Settings_Slots_View");
    }


    public function getHtml() {
        $html = "";

        // check edit permission
        $this->CanEdit = \Core\UserManager::loggedUser()->permitted("Settings_Slots_Edit");

        // retrieve requested slot
        if (array_key_exists('ServerSlot', $_REQUEST)) {
            $this->CurrentSlot = \Core\ServerSlot::fromId($_REQUEST['ServerSlot']);
        }
        if (array_key_exists('Action', $_REQUEST)) {
            if ($_REQUEST['Action'] == "SaveServerSlot" && $this->CanEdit) {
                $this->CurrentSlot->parameterCollection()->storeHttpRequest();
                $this->CurrentSlot->save();
                $this->reload(["ServerSlot"=>$this->CurrentSlot->id()]);
            }
        }


        // slot Overview
        $html .= $this->managementTable();


        // settings
        if ($this->CurrentSlot !== NULL) {
            $html .= "<h1>" . $this->CurrentSlot->name() . "</h1>";
            $html .= $this->newHtmlForm("POST");
            $pc = $this->CurrentSlot->parameterCollection();
            $html .= $pc->getHtml($this->CurrentSlot->id() !== 0, !$this->CanEdit);
//             $html .= "<br><br><br>" . $pc->getHtmlTree();

            if ($this->CanEdit) {
                $html .= "<br><br>";
                $html .= "<button type=\"submit\" name=\"Action\" value=\"SaveServerSlot\">" . _("Save Slot Settings") . "</button>";
            }

            $html .= "</form>";
        }


        // port overview
        if ($this->CurrentSlot !== NULL && $this->CurrentSlot->id() != 0) {
            $html .= "<h1>" . _("Port Overview") . "</h1>";
            $html .= "<p>" . _("The following diagramm illustrates the communication sockets which are used locally and via WAN. All involved ports and protocols are shown. Save settings to get an updated picture") . "</p>";
            $html .= "<p>" . _("Be aware, that the internet TCP port of Real penalty is defined automatically from the acServer HTTP port + 27.") . "</p>";
            $html .= file_get_contents(\Core\Config::AbsPathData . "/htcache/slot_ports_optimized.svg");

            $html .= "<script>";
            $html .= "document.addEventListener('DOMContentLoaded', function () {\n";
            $html .= "  var svg = document.getElementById('ServerSlotPortsSvg');\n";
            $html .= "  svg.removeAttribute('width');\n";
            $html .= "  svg.removeAttribute('height');\n";
            $html .= "  document.getElementById('ServerSlotPortsAcServerUdpR').innerHTML = 'UDP_R=" . $this->CurrentSlot->parameterCollection()->child('AcServerPortsPluginUdpR')->valueLabel() . "';\n";
            $html .= "  document.getElementById('ServerSlotPortsAcswuiUdpL1').innerHTML = 'UDP_L1=" . $this->CurrentSlot->parameterCollection()->child('ACswuiPortsPluginUdpL')->valueLabel() . "';\n";
            $html .= "  document.getElementById('ServerSlotPortsAcserverUdp').innerHTML = 'UDP=" . $this->CurrentSlot->parameterCollection()->child('AcServerPortsInetUdp')->valueLabel() . "';\n";
            $html .= "  document.getElementById('ServerSlotPortsAcserverTcp').innerHTML = 'TCP=" . $this->CurrentSlot->parameterCollection()->child('AcServerPortsInetTcp')->valueLabel() . "';\n";
            $html .= "  document.getElementById('ServerSlotPortsAcserverHttp').innerHTML = 'HTTP=" . $this->CurrentSlot->parameterCollection()->child('AcServerPortsInetHttp')->valueLabel() . "';\n";
            $html .= "  document.getElementById('ServerSlotPortsAcServerWrapper').innerHTML = 'HTTP=" . $this->CurrentSlot->parameterCollection()->child('AcServerWrapperHttpPort')->valueLabel() . "';\n";

            if ($this->CurrentSlot->parameterCollection()->child('RPGeneralEnable')->value()) {
                $html .= "  document.getElementById('ServerSlotPortsRpUdpL').innerHTML = 'UDP_L=" . $this->CurrentSlot->parameterCollection()->child('RPPortsPluginUdpL')->valueLabel() . "';\n";
                $html .= "  document.getElementById('ServerSlotPortsRpUdpR1').innerHTML = 'UDP_R1=" . $this->CurrentSlot->parameterCollection()->child('RPPortsPluginUdpR')->valueLabel() . "';\n";
                $html .= "  document.getElementById('ServerSlotPortsRpUdpR2').innerHTML = 'UDP_R2=" . $this->CurrentSlot->parameterCollection()->child('RPPortsPluginUdpR2')->valueLabel() . "';\n";
                $html .= "  document.getElementById('ServerSlotPortsAcswuiUdpL2').innerHTML = 'UDP_L2=" . $this->CurrentSlot->parameterCollection()->child('ACswuiPortsPluginUdpL2')->valueLabel() . "';\n";
                $html .= "  document.getElementById('ServerSlotPortsRpUdp').innerHTML = 'UDP=" . $this->CurrentSlot->parameterCollection()->child('RPPortsInetUdp')->valueLabel() . "';\n";
                $html .= "  document.getElementById('ServerSlotPortsRpTcp').innerHTML = 'TCP=" . (27 + $this->CurrentSlot->parameterCollection()->child('AcServerPortsInetHttp')->value()) . "';\n";
                $html .= "  document.getElementById('WithoutRP').style.display = 'none';\n";
            } else {
                $html .= "  document.getElementById('WithRP').style.display = 'none';\n";
            }

            if ($this->CurrentSlot->parameterCollection()->child("AcServerWrapperEnable")->value()) {
                $html .= "  document.getElementById('gAcServerWrapper').style.display = 'visible';\n";
            } else {
                $html .= "  document.getElementById('gAcServerWrapper').style.display = 'none';\n";
            }

            $html .= "})";
            $html .= "</script>";
        }


        return $html;
    }


    private function managementTable() {
        $html = "";

        $html .= "<h1>" . _("Slot Management") . "</h1>";

        $html .= "<table id=\"SlotManagementTable\">";
        $html .= "<tr>";
        $html .= "<th>"  . _("Server Slot") . "</th>";
        $html .= "<th>"  . _("Name") . "</th>";
        $html .= "</tr>";

        // root slot
        $root_slot = \Core\ServerSlot::fromId(0);
        $class_current_slot = ($this->CurrentSlot && $this->CurrentSlot->id() == $root_slot->id()) ? "class=\"CurrentSlot\"" : "";
        $html .= "<tr $class_current_slot>";
        $html .= "<td><a href=\"" . $this->url(['ServerSlot'=>$root_slot->id()]) . "\">" . $root_slot->name() . "</a></td>";
        $html .= "<td>" . $root_slot->parameterCollection()->child("AcServerGeneralName")->value() . "</td>";
        $html .= "</tr>";

        // user defined slots
        for ($i=1; $i <= \Core\Config::ServerSlotAmount; ++$i) {
            $slot = \Core\ServerSlot::fromId($i);
            $class_current_slot = ($this->CurrentSlot && $this->CurrentSlot->id() == $slot->id()) ? "class=\"CurrentSlot\"" : "";
            $prechars = ($i == \Core\Config::ServerSlotAmount) ? "&#x2516;&#x2574;" : "&#x2520;&#x2574;";
            $html .= "<tr $class_current_slot>";
            $html .= "<td>" . $prechars . "<a href=\"" . $this->url(['ServerSlot'=>$i]) . "\">" . $slot->name() . "</a></td>";
            $html .= "<td>" . $slot->parameterCollection()->child("AcServerGeneralName")->value() . "</td>";
            $html .= "</tr>";
        }

        $html .= "</table>";
        $html .= "</form>";

        return $html;
    }


}
