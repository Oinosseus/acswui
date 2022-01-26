<?php

namespace Parameter;

/**
 * Select multiplie enum items.
 */
class ParamEnumMulti extends ParamEnum {

    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description);
    }


    final public function getHtmlInput() {
        $html = "";

        $key = $this->key();
        $value = $this->formatValue($this->value());
        $value = explode(";", $value);
        $size = count($this->EnumItemList);
        if ($size > 4) $size = 4;
        $html .= "<select name=\"ParameterValue_" . $key . "[]\" size=\"$size\" multiple>";
        foreach ($this->EnumItemList as $enum_item) {
            $selected = (in_array($enum_item->value(), $value)) ? "selected=\"selected\"" : "";
            $html .= "<option value=\"" . $enum_item->value() . "\" $selected>" . $enum_item->label() . "</option>";
        }
        $html .= "</select>";

        return $html;
    }


    public function formatValue($value) {
        $clean_value = "";
        if (is_string($value) && strlen($value) > 0) {
            foreach (explode(";", $value) as $v) {
                if (array_key_exists($v, $this->EnumItemHash)) {
                    if (strlen($clean_value)) $clean_value .= ";";
                    $clean_value .= $v;
                }
            }
        }
        return $clean_value;
    }


    //! @return An array of the currently selected enum item values
    public function valueList() {
        if (strlen($this->value()) > 0) {
            return explode(";", $this->value());
        } else {
            return [];
        }
    }


    public function value2Label($value) {
        $label = "";
        $value = $this->formatValue($this->value());

        $n = 1;
        if ($value != "") {
            foreach (explode(";", $value) as $v) {
                if (strlen($label) > 0) $label .= ",";

                // add linebreaks
                if (strlen($label . $this->EnumItemHash[$v]->label()) > ($n * 30)) {
                    $label.= "<br>";
                    ++$n;
                } else {
                    $label .= " ";
                }

                $label .= $this->EnumItemHash[$v]->label();
            }
        }

        return $label;
    }


    public function storeHttpRequest() {
        parent::storeHttpRequest();
        $key = $this->key();

        // my inherit value
        $this->InheritValue = (array_key_exists("ParameterInheritValueCheckbox_$key", $_REQUEST)) ? TRUE : FALSE;

        // my value
        if (array_key_exists("ParameterValue_$key", $_REQUEST)) {
            $val = $_REQUEST["ParameterValue_$key"];
            $val = implode(";", $val);
            $this->setValue($val);
        } else {
            $this->setValue([]);
        }
    }
}
