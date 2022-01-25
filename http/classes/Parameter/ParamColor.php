<?php

namespace Parameter;

class ParamColor extends Parameter {

    protected function cloneXtraAttributes($base) {
    }


    public function getHtmlInput() {
        $html = "";
        $key = $this->key();
        $value = $this->value();
        $html .= "<input type=\"color\" value=\"$value\" name=\"ParameterValue_$key\">";
        return $html;
    }


    public function formatValue($value) {
        if (strlen($value) < 7) return NULL;
        return sprintf("#%06x", hexdec(substr($value, 1, 7)));
    }


    public function value2Label($value) {
        $value = $this->value();
        return "<div style=\"background-color: $value; margin-right:auto; margin-left:auto; width: 2em; height:1em;\">&nbsp;</div>";
    }
}
