<?php

namespace Parameter;

final class Collection extends Deriveable {



    //! @return TRUE if this collection (or any child collection)contains any accessable parameters
    public function containsAccessableParameters() {
        //! @todo check if this function is expensive (due to hierarchical/recursive iterations)

        foreach ($this->children() as $child) {
            if ($child instanceof Parameter) {
                if ($child->accessability() > 0) return TRUE;
            } else if ($child instanceof Collection) {
                if ($child->containsAccessableParameters()) return TRUE;
            } else {
                \Core\Log::error("Unexpected type!");
            }
        }

        return FALSE;
    }


    /**
     * Create an HTML string with all parameter settings as form elements
     * @param $hide_accessability_controls When TRUE (default FALSE), the constrols for derived accessability are hidden (intended for collections that shall not be derived)
     * @param $read_only When set to TRUE (default FALSE), inputs for editing are ommitted (when TRUE, $hide_accessability_controls is automatically TRUE)
     * @return An HTML string
     */
    public function getHtml(bool $hide_accessability_controls = FALSE, bool $read_only = FALSE) {
        $html = "";

        $level = $this->maxChildLevels();

        if ($level >= 0 && $level <= 1) {
            $html .= $this->getContainerLevel1($hide_accessability_controls, $read_only);

        } else if ($level == 2) {
            $html .= $this->getContainerLevel2($hide_accessability_controls, $read_only);

        } else if ($level == 3) {
            $html .= $this->getContainerLevel3($hide_accessability_controls, $read_only);

        } else if ($level == 4) {
            $html .= $this->getContainerLevel4($hide_accessability_controls, $read_only);

        } else {
            \Core\Log::error("Collection with level > 4 are currently not implemented :-(");

        }


        return $html;
    }




    private function getContainerLevel4(bool $hide_accessability_controls, bool $read_only) {
        $html = "";
        $html .= "<div class=\"CollectionContainerLevel4\">";

        // tabs
        $html .= "<nav>";
        $checked = "checked=\"checked\"";
        foreach ($this->children() as $collection) {
            if (!($collection instanceof Collection)) continue;
            if (!$collection->containsAccessableParameters()) continue;
            $html .= "<div>";
            $key = $collection->key();
            $id = "ParameterCollectionTabLabel$key";
            $name = "ParameterCollectionTabRadios" . $this->key();
            $html .= "<input type=\"radio\" id=\"$id\" name=\"$name\" $checked/>";
            $html .= "<label for=\"$id\" onclick=\"toggleParameterCollectionTabVisibility('" . $this->key() . "', '$key')\">";
            $html .= $collection->label();
            $html .= "</label>";
            $checked = "";
            $html .= "</div>";
        }
        $html .= "</nav>";

        // content
        $html .= "<div class=\"CollectionContainerLevel4ChildContainer\" id=\"CollectionContainerLevel4ChildContainer" . $this->key() . "\">";
        $display = "inline-block;";
        foreach ($this->children() as $collection) {

            // It is assumed that level 4 collection do only contain other collections as children.
            // Direct parameter children in Level4 collections is currently not supported.
            if (!($collection instanceof Collection)) continue;
            if (!$collection->containsAccessableParameters()) continue;

            $html .= "<div class=\"CollectionContainerLevel4ChildContainerTab\" id=\"CollectionContainerLevel4ChildContainerTab" . $collection->key() . "\" style=\"display:$display\">";

            // direct parameters
            $html_childparams = "";
            foreach ($collection->children() as $parameter) {
                if (!($parameter instanceof Parameter)) continue;
                if ($parameter->accessability() < 1) continue;
                $html_childparams .= $collection->getHtmlParameter($parameter, $hide_accessability_controls, $read_only);
            }
            if ($html_childparams != "") {
                $html .= "<div class=\"CollectionContainerLevel4ParameterContainer\">";
                $html .= $html_childparams;
                $html .= "</div>";
            }

            // sub collections
            foreach ($collection->children() as $collection) {
                if (!($collection instanceof Collection)) continue;
                $html .= $collection->getContainerLevel2($hide_accessability_controls, $read_only);
            }

            $html .= "</div>";
            $display = "none";
        }

        $html .= "</div>";
        $html .= "</div>";
        return $html;
    }


    private function getContainerLevel3(bool $hide_accessability_controls, bool $read_only) {
        $html = "";
        $html .= "<div class=\"CollectionContainerLevel3\">";

        $html .= "<div class=\"CollectionContainerLevel3Label\" title=\"" . $this->description() . "\">" . $this->label() . "</div>";
        $html .= "<div class=\"CollectionContainerLevel3ChildContainer\">";

        $html .= "<div class=\"CollectionContainerLevel3ParameterContainer\">";
        foreach ($this->children() as $parameter) {
            if (!($parameter instanceof Parameter)) continue;
            if ($parameter->accessability() < 1) continue;
            $html .= $this->getHtmlParameter($parameter, $hide_accessability_controls, $read_only);
        }
        $html .= "</div>";

        foreach ($this->children() as $collection) {
            if (!($collection instanceof Collection)) continue;
            $html .= $collection->getContainerLevel2($hide_accessability_controls, $read_only);
        }

        $html .= "</div>";
        $html .= "</div>";
        return $html;
    }


    private function getContainerLevel2(bool $hide_accessability_controls, bool $read_only) {
        $html = "";
        if ($this->containsAccessableParameters()) {
            $html .= "<div class=\"CollectionContainerLevel2\">";
            $html .= "<div class=\"CollectionContainerLevel2Label\" title=\"" . $this->description() . "\">" . $this->label() . "</div>";
            $html .= "<div class=\"CollectionContainerLevel2ChildContainer\">";

            // list direct parameter children
            $html .= "<div class=\"CollectionContainerLevel2ParameterContainer\">";
            foreach ($this->children() as $parameter) {
                if (!($parameter instanceof Parameter)) continue;
                if ($parameter->accessability() < 1) continue;
                $html .= $this->getHtmlParameter($parameter, $hide_accessability_controls, $read_only);
            }
            $html .= "</div>";

            // list collection children
            foreach ($this->children() as $collection) {
                if (!($collection instanceof Collection)) continue;
                if (!$collection->containsAccessableParameters()) continue;  // hide collections with only invisible children
                $html .= $collection->getContainerLevel1($hide_accessability_controls, $read_only);
            }

            $html .= "</div>";
            $html .= "</div>";
        }
        return $html;
    }



    private function getContainerLevel1(bool $hide_accessability_controls, bool $read_only) {
        $html = "";
        $html .= "<div class=\"CollectionContainerLevel1\">";
        $html .= "<div class=\"CollectionContainerLevel1Label\" title=\"" . $this->description() . "\">" . $this->label() . "</div>";

        // list direct parameter children
        $html .= "<div class=\"CollectionContainerLevel1ParameterContainer\">";
        foreach ($this->children() as $parameter) {
            if (!($parameter instanceof Parameter)) continue;
            if ($parameter->accessability() < 1) continue;
            $html .= $this->getHtmlParameter($parameter, $hide_accessability_controls, $read_only);
        }
        $html .= "</div>";

        $html .= "</div>";
        return $html;
    }



    private function getHtmlParameter(Parameter $param, bool $hide_accessability_controls, bool $read_only = FALSE) {
        $html = "";

        // skip invisible items
        if ($param->accessability() < 1) return "";

        // key snake for ID and Name attributes
        $key = $param->key();

        // parameter label
        $param_val = print_r($param->value(), TRUE);
        $html .= "<div class=\"ParameterLabel\" title=\"$key=$param_val\">" . $param->label() . "</div>";

        // value
        $param_value_span = 1;
        if ($param->unit() == "") ++$param_value_span;
        if ($param->base() == NULL || $param->accessability() < 2) ++$param_value_span;
        if ($hide_accessability_controls || $read_only) ++$param_value_span;
        $html .= "<div class=\"ParameterValueSpan$param_value_span\">";
        if ($read_only) {
            $html .= "<div id=\"ParameterValueInput_$key\" title=\"" . $param->description() . "\">" . $param->valueLabel() . "</div>";
        } else {
            if ($param->accessability() == 2) {  // editable input
                $visible = ($param->inheritValue()) ? "style=\"display: none;\"" : "";
                $html .= "<div id=\"ParameterValueInput_$key\" title=\"" . $param->description() . "\" $visible>" . $param->getHtmlInput() . "</div>";
            }
            if ($param->base() !== NULL) {  // inherited value
                $visible = ($param->inheritValue()) ? "" : "style=\"display: none;\"";
                $html .= "<div class=\"ParameterInheritedValue\" id=\"ParameterValueInherited_$key\" title=\"" . $param->description() . "\" $visible>" . $param->base()->valueLabel() . "</div>";
            }
        }
        $html .= "</div>";

        // unit
        if ($param->unit() !== "") {
            $html .= "<div class=\"ParameterUnit\">" . $param->unit() . "</div>";
        }

        // inheritance
        if ($param->base() !== NULL && $param->accessability() == 2) {
            $html .= "<div class=\"ParameterDerivedCheckbox\">";
            $checked = $param->inheritValue() ? "checked" : "";
            $disabled = ($read_only) ? "disabled" : "";
            $html .= "<input type=\"checkbox\" id=\"ParameterInheritValueCheckbox_$key\" name=\"ParameterInheritValueCheckbox_$key\" $checked onclick=\"toggleParameterInheritance('$key')\" $disabled>";
            $html .= "<label for=\"ParameterInheritValueCheckbox_$key\">";
            $html .= "<div class=\"Checked\" title=\"" . _("Value currently inherited from from base parameter") . "\">&#x26af;</div>";
            $html .= "<div class=\"UnChecked\" title=\"" . _("Value currently defined locally") . "\">&#x26ae;</div>";
            $html .= "</label>";
            $html .= "</div>";
        }

        // accessability
        if (!$hide_accessability_controls && !$read_only) {
            $derived_accessability = ($param->base() !== NULL) ? $param->base()->derivedAccessability() : 2;
            $html .= "<div class=\"ParameterDerivedCheckbox\" onclick=\"toggleParameterAccessability('$key', $derived_accessability)\">";
            $display = ($param->derivedAccessability() != 0) ? "style=\"display:none;\"" : "";
            $html .= "<div class=\"ParameterAccessabilityHidden\" id=\"ParameterDerivedAccessabilityHidden_$key\"  title=\"" . _("Hidden for derived parameters") . "\" $display>&#x1f6ab;</div>";
            $display = ($param->derivedAccessability() != 1) ? "style=\"display:none;\"" : "";
            $html .= "<div class=\"ParameterAccessabilityVisible\" id=\"ParameterAccessabilityVisible_$key\" title=\"" . _("Read-only for derived parameters") . "\" $display>&#x1f441;</div>";
            $display = ($param->derivedAccessability() != 2) ? "style=\"display:none;\"" : "";
            $html .= "<div class=\"ParameterAccessabilityEditable\" id=\"ParameterAccessabilityEditable_$key\" title=\"" . _("Derived parameters can change this value") . "\" $display>&#x1f4dd;</div>";
            $html .= "<input type=\"hidden\" id=\"ParameterAccessability_$key\" name=\"ParameterAccessability_$key\" value=\"" . $param->derivedAccessability() . "\">";
            $html .= "</div>";
        }

        return $html;
    }
}
