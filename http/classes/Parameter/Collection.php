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


        if ($this->maxChildLevels() == 0) {
            $html .= "<div class=\"ParameterCollection\">";
            $html .= "<div class=\"ParameterCollectionContainerLabel\" title=\"" . $this->description() . "\">" . $this->label() . "</div>";
            $html .= "</div>";

        } else if ($this->maxChildLevels() == 1) {

//             if ($this->containsAccessableParameters()) {  // maybe if this is called at a low-level collection, you always want to see at least something
                $html .= "<div class=\"ParameterCollection\">";
                $html .= "<div class=\"ParameterCollectionContainerLabel\" title=\"" . $this->description() . "\">" . $this->label() . "</div>";
                $html .= "<div class=\"ParameterCollectionContainer\">";

                // list direct parameter children
                $html .= "<div class=\"ParameterContainer\">";
                foreach ($this->children() as $parameter) {
                    if (!($parameter instanceof Parameter)) continue;
                    if ($parameter->accessability() < 1) continue;
                    $html .= $this->getHtmlParameter($parameter, $hide_accessability_controls, $read_only);
                }
                $html .= "</div>";

                $html .= "</div>";
                $html .= "</div>";
//             }


        } else if ($this->maxChildLevels() == 2) {

            if ($this->containsAccessableParameters()) {
                $html .= "<div class=\"ParameterCollection\">";
                $html .= "<div class=\"ParameterCollectionContainerLabel\" title=\"" . $this->description() . "\">" . $this->label() . "</div>";
                $html .= "<div class=\"ParameterCollectionContainer\">";

                // list direct parameter children
                $html .= "<div class=\"ParameterContainer\">";
                foreach ($this->children() as $parameter) {
                    if (!($parameter instanceof Parameter)) continue;
                    $html .= $this->getHtmlParameter($parameter, $hide_accessability_controls, $read_only);
                }
                $html .= "</div>";

                // list collection children
                foreach ($this->children() as $collection) {
                    if (!($collection instanceof Collection)) continue;
                    if (!$collection->containsAccessableParameters()) continue;  // hide collections with only invisible children
                    $html .= "<div class=\"ParameterCollectionSubLabel\" title=\"" . $collection->description() . "\">" . $collection->label() . "</div>";
                    $html .= "<div class=\"ParameterContainer\">";
                    foreach ($collection->children() as $parameter) {
                        if (!($parameter instanceof Parameter)) continue;
                        if ($parameter->accessability() < 1) continue;
                        $html .= $this->getHtmlParameter($parameter, $hide_accessability_controls, $read_only);
                    }
                    $html .= "</div>";
                }

                $html .= "</div>";
                $html .= "</div>";
            }


        } else {

            $html .= "<div class=\"ParameterCollection\">";

            $h = 6 - $this->maxChildLevels();
            if ($h < 1) $h = 1;
            $html .= "<h$h>" . $this->label() . " [" . $this->key() . "]</h$h>";
            $html .= "<small>" . $this->description() . "</small>";

            //! @todo list direct parameters
            $html .= "<div class=\"ParameterCollectionContainerLabel\" title=\"" . $this->description() . "\">" . $this->label() . "</div>";
            $html .= "<div class=\"ParameterCollectionContainer\">";
            $html .= "<div class=\"ParameterContainer\">";
            foreach ($this->children() as $parameter) {
                if (!($parameter instanceof Parameter)) continue;
                if ($parameter->accessability() < 1) continue;
                $html .= $this->getHtmlParameter($parameter, $hide_accessability_controls, $read_only);
            }
            $html .= "</div>";
            $html .= "</div>";
            $html .= "<br>";


            foreach ($this->children() as $collection) {
                if (!($collection instanceof Collection)) continue;
                $html .= $collection->getHtml($hide_accessability_controls, $read_only);
            }
            $html .= "</div>";
        }


        return $html;
    }


    private function getHtmlParameter(Parameter $param, bool $hide_accessability_controls, bool $read_only = FALSE) {
        $html = "";

        // skip invisible items
        if ($param->accessability() < 1) return "";

        // key snake for ID and Name attributes
        $key = $param->key();

        // parameter label
        $html .= "<div class=\"ParameterLabel\" title=\"[$key]\">" . $param->label() . "</div>";

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
            $html .= "<div class=\"ParameterAccessabilityHidden\" id=\"ParameterDerivedAccessabilityHidden_$key\"  title=\"" . _("Derived parameters hidden") . "\" $display>&#x1f6ab;</div>";
            $display = ($param->derivedAccessability() != 1) ? "style=\"display:none;\"" : "";
            $html .= "<div class=\"ParameterAccessabilityVisible\" id=\"ParameterAccessabilityVisible_$key\" title=\"" . _("Derived parameters are visible but fixed") . "\" $display>&#x1f441;</div>";
            $display = ($param->derivedAccessability() != 2) ? "style=\"display:none;\"" : "";
            $html .= "<div class=\"ParameterAccessabilityEditable\" id=\"ParameterAccessabilityEditable_$key\" title=\"" . _("Derived parameters can be changed") . "\" $display>&#x1f4dd;</div>";
            $html .= "<input type=\"hidden\" id=\"ParameterAccessability_$key\" name=\"ParameterAccessability_$key\" value=\"" . $param->derivedAccessability() . "\">";
            $html .= "</div>";
        }

        return $html;
    }
}
