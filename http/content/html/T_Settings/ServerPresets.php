<?php

namespace Content\Html;

class ServerPresets extends \core\HtmlContent {

    private $CurrentPreset = NULL;
    private $CanEdit = False;


    public function __construct() {
        parent::__construct(_("Presets"),  "");
        $this->requirePermission("Settings_Presets_View");
    }


    public function getHtml() {
        $this->CanEdit = \Core\UserManager::loggedUser()->permitted("Settings_Presets_Edit");
        $html = "";

        // get requested preset
        if (array_key_exists('ServerPreset', $_GET)) {
            $preset = \DbEntry\ServerPreset::fromId($_GET['ServerPreset']);
            if ($preset !== NULL) {
                $this->CurrentPreset = $preset;
            }
        }

        // process actions
        if (array_key_exists('Action', $_POST)) {
            $current_user = \Core\UserManager::loggedUser();

            if ($_POST['Action'] == "SavePreset" && $this->CurrentPreset !== NULL) {

                // prevent editing root preset
                if ($this->CanEdit) {
                    $this->CurrentPreset->parameterCollection()->storeHttpRequest();
                    $this->CurrentPreset->save();
                } else {
                    \Core\Log::warning("Prevent editing preset '" . $this->CurrentPreset->id() . "' from user '" . $current_user->id() . "'");
                }

            } else if ($_POST['Action'] == "SaveManagementTable") {

                // delete server presets
                function check_delete($presets) {
                    foreach ($presets as $p) {
                        $post_key = "DeleteServerPreset" . $p->id();
                        if (array_key_exists($post_key, $_POST)) {
                            $p->delete();
                        }
                        check_delete($p->children());
                    }
                }
                check_delete(\DbEntry\ServerPreset::fromId(0)->children());

                // it can happen that the current preset has been deleted
                $this->CurrentPreset = NULL;
            }


        // create new derived preset
        } else if (array_key_exists('DeriveServerPreset', $_GET)) {
            $parent_preset = \DbEntry\ServerPreset::fromId($_GET['DeriveServerPreset']);
            if ($parent_preset !== NULL) {
                $current_user = \Core\UserManager::loggedUser();
                if ($this->CanEdit) {
                    $this->CurrentPreset = NULL;
                    \DbEntry\ServerPreset::derive($parent_preset);
                } else {
                    \Core\Log::warning("Prevent from creating a new preset, derived from '$parent_preset' for user '$current_user'.");
                }
            }
        }

        $html .= $this->managementTable();



        if ($this->CurrentPreset !== NULL)
            $html .= $this->viewPreset($this->CurrentPreset);




        return $html;
    }



    private function viewPreset($preset) {
        $html = "<h1>" . $preset->name() . "</h1>";
        $html .= $this->newHtmlForm("POST");
        $pc = $this->CurrentPreset->parameterCollection();

        if ($this->CurrentPreset->parent() === NULL || !$this->CanEdit) {
            $html .= $pc->getHtml(FALSE, TRUE);
        } else {
            $html .= $pc->getHtml(count($preset->children()) == 0);
        }

        $html .= "<br><br>";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"SavePreset\">" . _("Save Preset") . "</button>";
        $html .= "</form>";


        // schedule
        $html .= "<h1>" . _("Schedule") . "</h1>";
        $html .= "<h2>" . _("Long Schedule") . "</h2>";
        $html .= "<p>" . _("Save preset to update the schedule.") . "</p>";
        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<th colspan=\"2\">" . _("Start") . "</th>";
        $html .= "<th colspan=\"2\">" . _("Entry") . "</th>";
        $html .= "<th colspan=\"2\">" . _("Duration") . "</th>";
        $html .= "</tr>";
        $preset_offset = new \Core\TimeInterval();
        $preset_offset_delay = new \Core\TimeInterval();
        foreach ($this->CurrentPreset->schedule() as [$interval, $uncertainty, $type, $name]) {
            $html .= "<tr>";

            $html .= "<td>";
            $html .= \Core\UserManager::currentUser()->formatSessionSchedule($preset_offset);
            $html .= "</td><td>";
            $html .= "(+" . \Core\UserManager::currentUser()->formatSessionSchedule($preset_offset_delay) . ")";
            $html .= "</td>";
            $preset_offset->add($interval);
            $preset_offset_delay->add($uncertainty);

            if ($type == \DbEntry\Session::TypeInvalid) $type = "";
            else $type = \DbEntry\Session::type2Char($type);
            $html .= "<td>" . $type . "</td>";
            $html .= "<td>" . $name . "</td>";

            $html .= "<td>";
            $html .= \Core\UserManager::currentUser()->formatSessionSchedule($interval);
            $html .= "</td><td>";
            $html .= "(+" . \Core\UserManager::currentUser()->formatSessionSchedule($uncertainty) . ")";
            $html .= "</td>";

            $html .= "</tr>";
        }
        $html .= "</table>";


        // short schedule
        $html .= "<h2>" . _("Short Schedule") . "</h2>";
        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<th>" . _("Start") . "</th>";
        $html .= "<th>" . _("Entry") . "</th>";
        $html .= "<th>" . _("Duration") . "</th>";
        $html .= "</tr>";
        $preset_offset = new \Core\TimeInterval();
        $preset_offset_delay = new \Core\TimeInterval();
        foreach ($this->CurrentPreset->schedule() as [$interval, $uncertainty, $type, $name]) {

            if ($type != \DbEntry\Session::TypeInvalid) {
                $html .= "<tr>";
                $html .= "<td>" . \Core\UserManager::currentUser()->formatSessionSchedule($preset_offset) . "</td>";
                $html .= "<td>$name</td>";
                $html .= "<td>" . \Core\UserManager::currentUser()->formatSessionSchedule($interval) . "</td>";
                $html .= "</tr>";
            }

            $preset_offset->add($interval);
            $preset_offset_delay->add($uncertainty);
        }
        $html .= "</table>";


//         $html .= "<br><br><br>";
//         $html .= $pc->getHtmlTree();

        return $html;
    }


    private function managementTable() {
        $html = "";

        $html .= "<h1>" . _("Preset Management") . "</h1>";

        $html .= $this->newHtmlForm("POST", "ServerPresetManagementForm");
        $html .= "<table id=\"PresetManagementTable\">";
        $html .= "<tr>";
        $html .= "<th>"  . _("Server Preset") . "</th>";
        $html .= "<th colspan=\"3\">"  . _("Manage") . "</th>";
        $html .= "</tr>";

        // root preset
        $root_preset = \DbEntry\ServerPreset::fromId(0);
        $class_current_preset = ($this->CurrentPreset && $this->CurrentPreset->id() == $root_preset->id()) ? "class=\"CurrentPreset\"" : "";
        $html .= "<tr $class_current_preset>";
        $html .= "<td><a href=\"" . $this->url(['ServerPreset'=>$root_preset->id()]) . "\">" . $root_preset->name() . "</a></td>";
        $html .= "<td></td>";
        $html .= "<td>";
        if ($this->CanEdit) {
            $html .= "<a href=\"" . $this->url(['DeriveServerPreset'=>$root_preset->id()]) . "\" title=\"" . _("Create new derived Preset") . "\">&#x21e9;</a>";
        }
        $html .= "</td>";
        $html .= "</tr>";

        // all other presets
        $html .= $this->mangementTableRow(\DbEntry\ServerPreset::fromId(0));

        $html .= "</table>";
        $html .= "<br><br>";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"SaveManagementTable\" form=\"ServerPresetManagementForm\">" . _("Save Preset Management") . "</button>";
        $html .= "</form>";

        return $html;
    }


    private function mangementTableRow(\DbEntry\ServerPreset $parent, string $indent="") {
        $html = "";

        for ($child_index=0; $child_index < count($parent->children()); ++$child_index) {
            $child = $parent->children()[$child_index];
            $child_id = $child->id();

            if ($child_index == (count($parent->children()) -  1)) {
                if (count($child->children())) $prechars = "&#x2517;&#x2578;";
                else $prechars = "&#x2516;&#x2574;";
            } else {
                if (count($child->children())) $prechars = "&#x2523;&#x2578;";
                else $prechars = "&#x2520;&#x2574;";
            }

            $class_current_preset = ($this->CurrentPreset && $this->CurrentPreset->id() == $child->id()) ? "class=\"CurrentPreset\"" : "";

            $html .= "<tr $class_current_preset>";
            $html .= "<td>" . $indent . $prechars . "<a href=\"" . $this->url(['ServerPreset'=>$child->id()]) . "\">" . $child->name() . "</a></td>";
            $html .= "<td>" . $this->newHtmlTableRowDeleteCheckbox("DeleteServerPreset" . $child->id()) . "</td>";
            $html .= "<td>";
            if ($this->CanEdit) $html .= "<a href=\"" . $this->url(['DeriveServerPreset'=>$child->id()]) . "\" title=\"" . _("Create new derived Preset") . "\">&#x21e9;</a>";
            $html .= "</td>";
            $html .= "</tr>";

            $next_indent = $indent;
            if ($child_index == (count($parent->children()) -  1))
                $next_indent .= "&nbsp;&nbsp;";
            else
                $next_indent .= "&#x2503;&nbsp;";

            $html .= $this->mangementTableRow($child, $next_indent);
        }

        return $html;
    }


}
