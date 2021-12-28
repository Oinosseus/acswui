<?php

namespace Parameter;

final class ParamBool extends Parameter {

    final public function getHtmlInput() {
        $html = "";

        $key_snake = $this->keySnake();
        $checked = ($this->value()) ? "checked" : "";
        $html .= "<input type=\"checkbox\" name=\"ParameterValue_$key_snake\" $checked>";

        return $html;
    }


    final public function formatValue($value) {
        return $value && TRUE;
    }


    //! This function will check for HTTP POST/GEST form data and store the data into the collection
    public function storeHttpRequest() {
        parent::storeHttpRequest();
        $key_snake = $this->keySnake();
        $new_val = (array_key_exists("ParameterValue_$key_snake", $_REQUEST)) ? TRUE : FALSE;
        $this->setValue($new_val);
    }


    final public function value2Label($value) {
        return ($value && TRUE) ? "&#x2611;" : "&#x2610;";
    }
}
