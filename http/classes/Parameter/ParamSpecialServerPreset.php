<?php

namespace Parameter;

/**
 * Select a ServerPreset
 * The available presets are automatically detected
 */
final class ParamSpecialServerPreset extends ParamEnum {

    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description);


        foreach (\DbEntry\ServerPreset::listPresets(FALSE) as $t) {
            new EnumItem($this, $t->id(), $t->name());
        }

        // set to first allowed preset
        $id = \DbEntry\ServerPreset::listPresets(TRUE)[0]->id();
        $this->setValue($id);
    }


    protected function cloneXtraAttributes($base) {
        // overload to prevent copying enum itmes
    }


    public function getHtmlInput() {
        $html = "";

        $key = $this->key();
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


    //! This function will check for HTTP POST/GEST form data and store the data into the collection
    public function storeHttpRequest() {
        parent::storeHttpRequest();
        $key_snake = $this->key();

        // no idea why this is needed -> just copyed from Parameter base class
        $this->InheritValue = (array_key_exists("ParameterInheritValueCheckbox_$key_snake", $_REQUEST)) ? TRUE : FALSE;

        // my value
        if (array_key_exists("ParameterValue_$key_snake", $_REQUEST)) {
            $val = (int) $_REQUEST["ParameterValue_$key_snake"];
            $preset = \DbEntry\ServerPreset::fromId($val);

            // set only when allowed -> (this is the difference to the base class implementation
            if ($preset && $preset->allowed()) $this->setValue($val);
        }

    }
}
