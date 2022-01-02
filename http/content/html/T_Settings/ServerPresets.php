<?php

namespace Content\Html;

class ServerPresets extends \core\HtmlContent {

    private $CurrentPreset = NULL;


    public function __construct() {
        parent::__construct(_("Presets"),  "");
        $this->requirePermission("Settings_Presets_View");
    }


    public function getHtml() {
        $html = "";

        // get requested preset
        if (array_key_exists('ServerPreset', $_REQUEST)) {
            $preset = \DbEntry\ServerPreset::fromId($_REQUEST['ServerPreset']);
            if ($preset !== NULL && $preset->isDeriveable(\Core\UserManager::loggedUser())) {
                $this->CurrentPreset = $preset;
            }
        }

        // process actions
        if (array_key_exists('Action', $_POST)) {
            $current_user = \Core\UserManager::loggedUser();

            if ($_POST['Action'] == "SavePreset" && $this->CurrentPreset !== NULL) {

                // prevent editing root preset
                if ($current_user === NULL) {
                    \Core\Log::warning("Prevent editing preset '" . $this->CurrentPreset->id() . "' from not logged user");
                } else if ($this->CurrentPreset->parent() === NULL && !$current_user->isRoot()) {
                    \Core\Log::warning("Prevent editing preset '" . $this->CurrentPreset->id() . "' from not user '" . $current_user->id() . "'");
                } else if (!$this->CurrentPreset->parent()->isDeriveable($current_user)) {
                    \Core\Log::warning("Prevent editing preset '" . $this->CurrentPreset->id() . "' from not user '" . $current_user->id() . "'");
                } else {
                    $this->CurrentPreset->parameterCollection()->storeHttpRequest();
                    $this->CurrentPreset->save();
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
            }
        }

        // create new derived preset
        if (array_key_exists('DeriveServerPreset', $_GET)) {
            $parent_preset = \DbEntry\ServerPreset::fromId($_GET['DeriveServerPreset']);
            if ($parent_preset !== NULL) {
                $current_user = \Core\UserManager::loggedUser();
                if ($current_user === NULL) {
                    \Core\Log::warning("Prevent from creating a new preset, derived from '$parent_preset' for unlogged user.");
                } else if (!$parent_preset->isDeriveable($current_user)) {
                    \Core\Log::warning("Prevent from creating a new preset, derived from '$parent_preset' for user '$current_user'.");
                } else {
                    $this->CurrentPreset = \DbEntry\ServerPreset::derive($parent_preset);
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
        $html .= $pc->getHtml();
        $html .= "<br><br>";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"SavePreset\">" . _("Save Preset") . "</button>";
        $html .= "</form>";

        $html .= $pc->getHtmlTree();

        return $html;
    }


    private function managementTable() {
        $html = "";

        $html .= "<h1>" . _("Preset Management") . "</h1>";

        $html .= $this->newHtmlForm("POST");
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
        if ($root_preset->isDeriveable(\Core\UserManager::loggedUser()))
            $html .= "<td><a href=\"" . $this->url(['DeriveServerPreset'=>$root_preset->id()]) . "\" title=\"" . _("Create new derived Preset") . "\">&#x21e9;</a></td>";
        else
            $html .= "<td></td>";
        $html .= "</tr>";

        // all other presets
        $html .= $this->mangementTableRow(\DbEntry\ServerPreset::fromId(0));

        $html .= "</table>";
        $html .= "<br><br>";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"SaveManagementTable\">" . _("Apply Deleted Presets") . "</button>";
        $html .= "</form>";

        return $html;
    }


    private function mangementTableRow(\DbEntry\ServerPreset $parent, string $indent="") {
        $html = "";

        for ($child_index=0; $child_index < count($parent->children()); ++$child_index) {
            $child = $parent->children()[$child_index];

            if ($child_index == (count($parent->children()) -  1)) {
                if (count($child->children())) $prechars = "&#x2517;&#x2578;";
                else $prechars = "&#x2516;&#x2574;";
            } else {
                if (count($child->children())) $prechars = "&#x2523;&#x2578;";
                else $prechars = "&#x2520;&#x2574;";
            }

            $is_deriveable = $child->isDeriveable(\Core\UserManager::loggedUser());
            $class_current_preset = ($this->CurrentPreset && $this->CurrentPreset->id() == $child->id()) ? "class=\"CurrentPreset\"" : "";

            $html .= "<tr $class_current_preset>";
            $html .= "<td>" . $indent . $prechars . "<a href=\"" . $this->url(['ServerPreset'=>$child->id()]) . "\">" . $child->name() . "</a></td>";
            $html .= "<td>" . $this->newHtmlTableRowDeleteCheckbox("DeleteServerPreset" . $child->id()) . "</td>";
            $html .= "<td>";
            if ($is_deriveable) $html .= "<a href=\"" . $this->url(['DeriveServerPreset'=>$child->id()]) . "\" title=\"" . _("Create new derived Preset") . "\">&#x21e9;</a>";
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
