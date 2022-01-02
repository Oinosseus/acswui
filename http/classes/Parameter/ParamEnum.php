<?php

namespace Parameter;

final class ParamEnum extends Parameter {

    private $EnumItemList = array();
    private $EnumItemHash = array(); // key=enum-item-value, value=EnumItem


    final protected function cloneXtraAttributes($base) {
        $this->EnumItemHash = $base->EnumItemHash;
        $this->EnumItemList = $base->EnumItemList;
    }


    final public function getHtmlInput() {
        $html = "";

        $key = $this->key();
        $value = $this->value();
        $html .= "<select name=\"ParameterValue_$key\">";
        foreach ($this->EnumItemList as $enum_item) {
            $selected = ($value == $enum_item->value()) ? "selected" : "";
            $html .= "<option value=\"" . $enum_item->value() . "\" $selected>" . $enum_item->label() . "</option>";
        }
        $html .= "</select>";

        return $html;
    }


    final public function formatValue($value) {
        if (!array_key_exists($value, $this->EnumItemHash)) return NULL;
        else return $this->EnumItemHash[$value]->value();
    }


    final public function value2Label($value) {
        return $this->EnumItemHash[$value]->label();
    }


    public function addEnumItem(EnumItem $enum_item) {
        if (array_key_exists($enum_item->value(), $this->EnumItemHash)) {
            \Core\Log::warning("Ignoring duplicated EnumItem value '" .
                               $enum_item->value() . "' at '" .
                               $this->key() . "'");
        }
        $this->EnumItemHash[$enum_item->value()] = $enum_item;
        $this->EnumItemList[] = $enum_item;
    }
}
