<?php

namespace Parameter;

final class ParamFloat extends ParamInt {

    final public function getHtmlInput(string $html_id_prefix = "") {
        $html = "";

        $key = $html_id_prefix . $this->key();
        $value = $this->value();
        $min = ($this->MinVal !== NULL) ? "min=\"$this->MinVal\"" : "";
        $max = ($this->MaxVal !== NULL) ? "max=\"$this->MaxVal\"" : "";

        $html .= "<input type=\"number\" name=\"ParameterValue_$key\" value=\"$value\" $min $max step=\"0.01\">";

        return $html;
    }


    final public function formatValue($value) {
        $value = str_replace(",", ".", $value);
        return sprintf("%0.2F", $value);
    }

    public function value() {
        return (float) parent::value();
    }
}
