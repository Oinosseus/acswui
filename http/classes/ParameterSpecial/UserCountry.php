<?php

namespace ParameterSpecial;

class UserCountry extends \Parameter\ParamEnum {

    public function __construct(?\Parameter\Deriveable $base,
                                ?\Parameter\Collection $parent,
                                string $key = "",
                                string $label = "",
                                string $description = "") {
        parent::__construct($base, $parent, $key, $label, $description, "", "");

        new \Parameter\EnumItem($this, "_", "");
        foreach (\Core\Config::Countries as $key=>$name) {
            new \Parameter\EnumItem($this, $key, $name);
        }
        $this->setValue("_");
    }


    final protected function cloneXtraAttributes($base) {
    }


    public function getHtmlInput() {
        $html = "";

        $key = $this->key();
        $value = $this->value();

//         if ($value !== "_") {
        $url = \Core\Config::RelPathHtdata;
        $html .= "<img src=\"$url/flagpedia/$value.svg\" class=\"ParameterUserCountry\">";
//         }

        $html .= "<select name=\"ParameterValue_$key\" onchange=\"toggleParamUserCountry('$key')\" id=\"ParameterUserCountry$key\">";
        foreach ($this->EnumItemList as $enum_item) {
            $selected = ($value == $enum_item->value()) ? "selected" : "";
            $html .= "<option value=\"" . $enum_item->value() . "\" $selected>" . $enum_item->label() . "</option>";
        }
        $html .= "</select>";

        return $html;
    }


    public function value2Label($value) {
        $html = "";

        if ($value !== "_") {
            $country_name = \Core\Config::Countries[$value];
            $url = \Core\Config::RelPathHtdata;
            $html .= "<img src=\"$url/flagpedia/$value.svg\" class=\"ParameterUserCountry\" title=\"$country_name\">";
        }

        return $html;;
    }
}
