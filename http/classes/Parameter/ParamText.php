<?php

namespace Parameter;

final class ParamText extends Parameter {

    final public function getHtmlInput() {
        $html = "";

        $key = $this->key();
        $value = $this->value();
        $html .= "<textarea name=\"ParameterValue_$key\">$value</textarea>";

        return $html;
    }


    final public function formatValue($value) {
        return "$value";
    }


    final public function value2Label($value) {
        return $value;
    }
}
