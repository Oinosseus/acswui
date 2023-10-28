<?php

namespace Parameter;

abstract class Parameter extends Deriveable {

    protected $Value = NULL;
    private $ValueForce = FALSE;
    private $Unit = "";
    protected $InheritValue = TRUE;


    public function __construct(?Deriveable $base,
                                ?Collection $parent,
                                string $key = "",
                                string $label = "",
                                string $description = "",
                                $unit="",
                                $default=NULL) {

        parent::__construct($base, $parent, $key, $label, $description);
        $this->Unit = $unit;
        if ($default !== NULL) $this->Value = $this->formatValue($default);
    }


    /**
     * Import data, that previously was exported with dataArrayExport()
     */
    public function dataArrayImport(array $data) {
        $da = parent::dataArrayImport($data);
        if (array_key_exists('VA', $data) && $this->accessability() == 2) $this->setValue($data['VA']);
        if (array_key_exists('IV', $data)) $this->inheritValue($data['IV']);
    }


   //! @return An array with all user data that need to be saved (visibility, values, etc.)
    public function dataArrayExport() {
        $da = parent::dataArrayExport();
        $da['VA'] = $this->value();
        $da['IV'] = $this->inheritValue();
        return $da;
    }


    public abstract function getHtmlInput(string $html_id_prefix = "");


    /**
     * Check if a value is inherited from the base parameter.
     * If the paremter has no base, this return FALSE.
     * Also, this function can set if the value shall be inherited (if parameter $inherit is not NULL)
     * @param $inherit Set to TRUE or FALSE to define if the parameter value shall be inherited from base. Ignore this parameter (or set to NULL) to only retrieve the current state
     */
    public function inheritValue(?bool $inherit = NULL) {
        // save new inheritance setting
        if ($inherit !== NULL) {

            if ($this->base() === NULL)  {
                \Core\Log::warning("Prevent inheriting value without having a base at '" . $this->key() . "'");
                $this->InheritValue = FALSE;
            } else if ($this->accessability() != 2 && !$inherit) {
                // no warning, since this can happen regulary when a child has defined a value and lateron the parent revokes the derived access
                // \Core\Log::warning("Prevent inheriting value without allowing access '" . $this->key() . "'");
                $this->InheritValue = TRUE;
            } else {
                $this->InheritValue = $inherit;
            }
        }

        // output current value
        if ($this->base() === NULL) return FALSE;
        else if ($this->accessability() !== 2) return TRUE;
        else return $this->InheritValue;
    }


    //! @return The represenative unit of the parameter
    public function unit() {
        return ($this->base() !== NULL) ? $this->base()->unit() : $this->Unit;
    }


    public function setValue($new_value, bool $force = FALSE) {
        if ($this->accessability() == 2) {
            $this->Value = $this->formatValue($new_value);
        }

        if ($force) {
            $this->Value = $this->formatValue($new_value);
            $this->ValueForce = TRUE;
        }
    }


    //! This function will check for HTTP POST/GEST form data and store the data into the collection
    public function storeHttpRequest(string $html_id_prefix = "") {
        parent::storeHttpRequest($html_id_prefix);
        $key = $html_id_prefix . $this->key();

        // my inherit value
        $this->InheritValue = (array_key_exists("ParameterInheritValueCheckbox_$key", $_REQUEST)) ? TRUE : FALSE;

        // my value
        if (array_key_exists("ParameterValue_$key", $_REQUEST)) {
            $val = $_REQUEST["ParameterValue_$key"];
            $this->setValue($val);
        }

    }


    public function value() {
        $value = NULL;

        if ($this->ValueForce) {
            $value = $this->Value;
        } else if ($this->inheritValue() || ($this->accessability() !== 2)) {
            $value = $this->base()->value();
        } else {
            $value = $this->Value;
        }

        return $this->formatValue($value);
    }


    public function valueLabel() {
        return $this->value2Label($this->value());
    }


    /**
     * This shall be implemented by the derived class.
     * It shall transform The input value to a valid value.
     * Examples:
     * - "0123" to 123 for integer parameters
     * - -1 to 0 for integer parameters with a minimum of 0
     * - 123 to TRUE for bool parameters
     *
     * If it is not possible to transfoorm the value, NULL shall be returned.
     * @return NULL or the transformed value
     */
    abstract public function formatValue($value);

    //! @return The display representation of a value
    abstract public function value2Label($value);

}
