<?php

namespace Parameter;

final class ParamBool extends Parameter {

    final public function getHtmlInput(string $html_id_prefix = "") {
        $html = "";

        $key = $html_id_prefix . $this->key();
        $checked = ($this->value()) ? "checked" : "";
        $html .= "<input type=\"checkbox\" name=\"ParameterValue_$key\" $checked>";

        return $html;
    }


    final public function formatValue($value) {
        return $value && TRUE;
    }


    //! This function will check for HTTP POST/GEST form data and store the data into the collection
    public function storeHttpRequest(string $html_id_prefix = "") {
        parent::storeHttpRequest();
        $key = $html_id_prefix . $this->key();
        $new_val = (array_key_exists("ParameterValue_$key", $_REQUEST)) ? TRUE : FALSE;
        $this->setValue($new_val);
    }


    final public function value2Label($value) {
        return ($value && TRUE) ? "&#x2611;" : "&#x2610;";
    }
}
