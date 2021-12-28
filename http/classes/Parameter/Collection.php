<?php

namespace Parameter;

final class Collection extends Deriveable {

    public function getHtml() {
        $html = "";


        if ($this->maxChildLevels() == 0) {
            $html .= "<div class=\"ParameterCollection\">";
            $html .= "<div class=\"ParameterCollectionContainerLabel\">" . $this->label() . "</div>";
            $html .= "</div>";

        } else if ($this->maxChildLevels() == 1) {

            $html .= "<div class=\"ParameterCollection\">";
            $html .= "<div class=\"ParameterCollectionContainerLabel\">" . $this->label() . "</div>";
            $html .= "<div class=\"ParameterCollectionContainer\">";
            $html .= "<small>" . $this->description() . "</small>";

            // list direct parameter children
            $html .= "<div class=\"ParameterContainer\">";
            foreach ($this->children() as $parameter) {
                if (!($parameter instanceof Parameter)) continue;
                if ($parameter->accessability() < 1) continue;
                $html .= $this->getHtmlParameter($parameter);
            }
            $html .= "</div>";

            $html .= "</div>";
            $html .= "</div>";


        } else if ($this->maxChildLevels() == 2) {

            $html .= "<div class=\"ParameterCollection\">";
            $html .= "<div class=\"ParameterCollectionContainerLabel\">" . $this->label() . "</div>";
            $html .= "<div class=\"ParameterCollectionContainer\">";
            $html .= "<small>" . $this->description() . "</small>";

            // list direct parameter children
            $html .= "<div class=\"ParameterContainer\">";
            foreach ($this->children() as $parameter) {
                if (!($parameter instanceof Parameter)) continue;
                $html .= $this->getHtmlParameter($parameter);
            }
            $html .= "</div>";

            // list collection children
            foreach ($this->children() as $collection) {
                if (!($collection instanceof Collection)) continue;
                $html .= "<div class=\"ParameterCollectionSubLabel\">" . $collection->label() . "</div>";
                $html .= "<div class=\"ParameterContainer\">";
                foreach ($collection->children() as $parameter) {
                    if (!($parameter instanceof Parameter)) continue;
                    if ($parameter->accessability() < 1) continue;
                    $html .= $this->getHtmlParameter($parameter);
                }
                $html .= "</div>";
            }

            $html .= "</div>";
            $html .= "</div>";


        } else {

            $html .= "<div class=\"ParameterCollection\">";

            $h = 6 - $this->maxChildLevels();
            if ($h < 1) $h = 1;
            $html .= "<h$h>" . $this->label() . " [" . $this->keySnake() . "]</h$h>";
            $html .= "<small>" . $this->description() . "</small>";

            //! @todo list direct parameters
            $html .= "<div class=\"ParameterCollectionContainerLabel\">" . $this->label() . "</div>";
            $html .= "<div class=\"ParameterCollectionContainer\">";
            $html .= "<div class=\"ParameterContainer\">";
            foreach ($this->children() as $parameter) {
                if (!($parameter instanceof Parameter)) continue;
                if ($parameter->accessability() < 1) continue;
                $html .= $this->getHtmlParameter($parameter);
            }
            $html .= "</div>";
            $html .= "</div>";
            $html .= "<br>";


            foreach ($this->children() as $collection) {
                if (!($collection instanceof Collection)) continue;
                $html .= $collection->getHtml();
            }
            $html .= "</div>";
        }


        return $html;
    }


    private function getHtmlParameter(Parameter $param) {
        $html = "";

        // skip invisible items
        if ($param->accessability() < 1) return "";

        // key snake for ID and Name attributes
        $key_snake = $param->keySnake();

        // parameter label
        $html .= "<div class=\"ParameterLabel\">" . $param->label() . "</div>";

        // value
        $param_value_span = 1;
        if ($param->unit() == "") ++$param_value_span;
        if ($param->base() == NULL || $param->accessability() < 2) ++$param_value_span;
        $html .= "<div class=\"ParameterValueSpan$param_value_span\">";
        if ($param->accessability() == 2) {  // editable input
            $visible = ($param->inheritValue()) ? "style=\"display: none;\"" : "";
            $html .= "<div id=\"ParameterValueInput_$key_snake\" $visible>" . $param->getHtmlInput() . "</div>";
        }
        if ($param->base() !== NULL) {  // inherited value
            $visible = ($param->inheritValue()) ? "" : "style=\"display: none;\"";
            $html .= "<div class=\"ParameterInheritedValue\" id=\"ParameterValueInherited_$key_snake\" $visible>" . $param->base()->valueLabel() . "</div>";
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
            $html .= "<input type=\"checkbox\" id=\"ParameterInheritValueCheckbox_$key_snake\" name=\"ParameterInheritValueCheckbox_$key_snake\" $checked onclick=\"toggleParameterInheritance('$key_snake')\">";
            $html .= "<label for=\"ParameterInheritValueCheckbox_$key_snake\">";
            $html .= "<div class=\"Checked\" title=\"" . _("Derive value from base parameter") . "\">&#x26af;</div>";
            $html .= "<div class=\"UnChecked\" title=\"" . _("Define value locally") . "\">&#x26ae;</div>";
            $html .= "</label>";
            $html .= "</div>";
        }

        // accessability
        $derived_accessability = ($param->base() !== NULL) ? $param->base()->derivedAccessability() : 2;
        $html .= "<div class=\"ParameterDerivedCheckbox\" onclick=\"toggleParameterAccessability('$key_snake', $derived_accessability)\">";
        $display = ($param->derivedAccessability() != 0) ? "style=\"display:none;\"" : "";
        $html .= "<div class=\"ParameterAccessabilityHidden\" id=\"ParameterDerivedAccessabilityHidden_$key_snake\"  title=\"" . _("Derived parameters hidden") . "\" $display>&#x1f6ab;</div>";
        $display = ($param->derivedAccessability() != 1) ? "style=\"display:none;\"" : "";
        $html .= "<div class=\"ParameterAccessabilityVisible\" id=\"ParameterAccessabilityVisible_$key_snake\" title=\"" . _("Derived parameters are visible but fixed") . "\" $display>&#x1f441;</div>";
        $display = ($param->derivedAccessability() != 2) ? "style=\"display:none;\"" : "";
        $html .= "<div class=\"ParameterAccessabilityEditable\" id=\"ParameterAccessabilityEditable_$key_snake\" title=\"" . _("Derived parameters can be changed") . "\" $display>&#x1f4dd;</div>";
        $html .= "<input type=\"hidden\" id=\"ParameterAccessability_$key_snake\" name=\"ParameterAccessability_$key_snake\" value=\"" . $param->derivedAccessability() . "\">";
        $html .= "</div>";

        return $html;
    }
}
