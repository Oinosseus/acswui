<?php

namespace ParameterSpecial;

/**
 * Select a ServerPreset
 * The available presets are automatically detected
 */
final class ServerPreset extends \Parameter\ParamEnum {

    public function __construct(?\Parameter\Deriveable $base,
                                ?\Parameter\Collection $parent,
                                string $key = "",
                                string $label = "",
                                string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description);


        foreach (\DbEntry\ServerPreset::listPresets(FALSE) as $t) {
            new \Parameter\EnumItem($this, $t->id(), $t->name());
        }

        // set to first allowed preset
        $allowed_presets = \DbEntry\ServerPreset::listPresets(TRUE);
        if (count($allowed_presets) > 0) $this->setValue($allowed_presets[0]->id());
        else $this->setValue($this->EnumItemList[0]->value());
    }


    protected function cloneXtraAttributes($base) {
        // overload to prevent copying enum itmes
    }


    public function getHtmlInput(string $html_id_prefix = "") {
        $html = "";

        $key = $html_id_prefix . $this->key();
        $value = $this->value();
        $html .= "<select name=\"ParameterValue_$key\">";
        foreach ($this->EnumItemList as $enum_item) {
            $selected = ($value == $enum_item->value()) ? "selected" : "";
            $disabled = (\DbEntry\ServerPreset::fromId($enum_item->value())->allowed()) ? "" : "disabled=\"yes\"";
            $html .= "<option value=\"" . $enum_item->value() . "\" $selected $disabled>" . $enum_item->label() . "</option>";
        }
        $html .= "</select>";

        return $html;
    }


    //! @return The according ServerPreset object
    public function serverPreset() {
        return \DbEntry\ServerPreset::fromId($this->value());
    }


    //! This function will check for HTTP POST/GEST form data and store the data into the collection
    public function storeHttpRequest(string $html_id_prefix = "") {
        parent::storeHttpRequest();
        $key = $html_id_prefix . $this->key();

        // no idea why this is needed -> just copyed from Parameter base class
        $this->InheritValue = (array_key_exists("ParameterInheritValueCheckbox_$key", $_REQUEST)) ? TRUE : FALSE;

        // my value
        if (array_key_exists("ParameterValue_$key", $_REQUEST)) {
            $val = (int) $_REQUEST["ParameterValue_$key"];
            $preset = \DbEntry\ServerPreset::fromId($val);

            // set only when allowed -> (this is the difference to the base class implementation
            if ($preset && $preset->allowed()) $this->setValue($val);
        }

    }
}
