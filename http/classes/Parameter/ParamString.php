<?php

namespace Parameter;

final class ParamString extends Parameter {

    final public function getHtmlInput() {
        $html = "";

        $key = $this->key();
        $value = $this->value();
        $html .= "<input type=\"text\" name=\"ParameterValue_$key\" value=\"$value\">";

        return $html;
    }


    final public function formatValue($value) {
        return "$value";
    }


    final public function value2Label($value) {
        return $value;
    }
}
