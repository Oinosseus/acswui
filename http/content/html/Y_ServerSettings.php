<?php

namespace Content\Html;

class Y_ServerSettings extends \core\HtmlContent {

    private $CurrentPreset = NULL;


    public function __construct() {
        parent::__construct(_("Server Settings"),  "");
        $this->requirePermission("ServerSettings_View");
    }


    public function getHtml() {
        $html = "";

        if (array_key_exists('ServerPreset', $_REQUEST)) {
            $preset = \DbEntry\ServerPreset::fromId($_REQUEST['ServerPreset']);
            if ($preset->isDeriveable(\Core\UserManager::loggedUser())) {
                $this->CurrentPreset = $preset;
            }
        }

        $base_preset = \DbEntry\ServerPreset::fromId(0);
        $base_preset->isDeriveable(\Core\UserManager::loggedUser());

        // preset selection
        $html .= $this->newHtmlForm();
        $html .= "<select size=\"7\" class=\"monospace\" name=\"ServerPreset\" onClick=\"form.submit()\">";
        $disabled = ($base_preset->isDeriveable(\Core\UserManager::loggedUser())) ? "" : "disabled";
        $value = $base_preset->id();
        $selected = ($this->CurrentPreset && $this->CurrentPreset->id() == $base_preset->id()) ? "selected" : "";
        $html .= "<option value=\"$value\" $disabled $selected>" . $base_preset->name() . "</option>";
        $html .= $this->presetSelectOption($base_preset);
        $html .= "</select><br>";
        $html .= "</form>";


        if ($this->CurrentPreset !== NULL) {
            $html .= $this->newHtmlForm("POST");

            $pc = $this->CurrentPreset->parameterCollection();
            if (array_key_exists('Action', $_POST) && $_POST['Action'] == "Save") {
                $pc->storeHttpRequest();
                $this->CurrentPreset->save();
            }
            $html .= "Root.maxChildLevels()=" . $pc->maxChildLevels() . "<br>";
            $html .= $pc->getHtml();


            $html .= "<br><br>";
            $html .= "<button type=\"submit\" name=\"Action\" value=\"Save\">Save</button>";
            $html .= "</form>";


            function __helper($collection) {
//                 echo $collection->keySnake() . "<br>";
                foreach ($collection->children() as $child) __helper($child);
            }
            __helper($this->CurrentPreset->parameterCollection());
        }






        return $html;
    }


    private function presetSelectOption(\DbEntry\ServerPreset $parent, string $indent="") {
        $html = "";

        for ($child_index=0; $child_index < count($parent->children()); ++$child_index) {
            $child = $parent->children()[$child_index];

            if ($child_index == (count($parent->children()) -  1))
                $prechars = "&#x2514;&#x2574;";
            else
                $prechars = "&#x251c;&#x2574;";

            $disabled = ($child->isDeriveable(\Core\UserManager::loggedUser())) ? "" : "disabled";
            $value = $child->id();
            $selected = ($this->CurrentPreset && $this->CurrentPreset->id() == $child->id()) ? "selected" : "";

            $html .= "<option value=\"$value\" $disabled $selected>";
            $html .= $indent;
            $html .= $prechars;
            $html .= $child->name();
            $html .= "</option>";

            $next_indent = $indent;
            if ($child_index == (count($parent->children()) -  1))
                $next_indent .= "&nbsp;&nbsp;";
            else
                $next_indent .= "&#x2502;&nbsp;";

            $html .= $this->presetSelectOption($child, $next_indent);
        }

        return $html;
    }
}
