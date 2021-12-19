<?php

namespace Parameter;

abstract class Deriveable {

    // basic properties
    private $Base = NULL;
    private $Description = "";
    private $Key = "";
    private $Label = "";
    private $Parent = NULL;

    // access to derived parameters
    private $DerivedAccessability = 0;

    // children
    private $Children = array();

    // cache objects
    private $MaxChildLevels = 0;
    private $KeySnake = "";


    /**
     * Create a new Deriveable object.
     * When $base is given $key, $label and $description are ignored.
     *
     * When $base is set to NULL, a new underived object is created.
     * When $base is given, a derived object is created.
     *
     * When $parent is NULL, a top level object is created.
     */
    public function __construct(?Deriveable $base, ?Collection $parent, string $key = "", string $label = "", string $description = "") {

        $this->Base = $base;
        $this->Parent = $parent;

        // not deriving
        if ($base === NULL) {
            $this->Description = trim($description);
            $this->Key = trim($key);
            $this->Label = trim($label);

        // unallowed deriving
        } else if (get_class($base) !== get_class($this)) {
            $type_base = get_class($base);
            $type_this = get_class($this);
            \Core\Log::error("Deriving '$type_this' from '$type_base'!");

        // correct deriving
        } else {

            $this->MaxChildLevels = $base->MaxChildLevels;
            $this->KeySnake = $base->KeySnake;

            // create derived children
            foreach ($base->children() as $base_child) {
                $chld_class = get_class($base_child);
                new $chld_class($base_child, $this);
            }

            // clone aatributes
            $this->cloneXtraAttributes($base);
            $this->DerivedAccessability = $base->DerivedAccessability;
        }


        if ($parent === NULL) {
            $this->KeySnake = $this->key();

        // announce to parent
        } else {
            // append child
            if ($parent->child($this->key()) !== NULL) {
                \Core\Log::error("Collection '" . $parent->key() . "' already contains a child with key='" . $this->key() . "'!");
            } else {
                $parent->Children[$this->key()] = $this;
            }

            // inform parent that at least one child is present
            $parent->informChildLevels(1);

            // determine parent keys
            $this->KeySnake = $parent->keySnake() . "_" . $this->key();
        }
    }


    public function __toString() {
        return $this->key();
    }


    /**
     * Read the accessability of the item.
     * 0 - Not visible or editable
     * 1 - Visible (read-only)
     * 2 - Editable (and visible)
     * @return The current accessability level
     */
    public function accessability() {
        // return current level
        if ($this->Base === NULL) return 2;
        else return $this->Base->derivedAccessability();
    }


    //! @return The base object of this derived parameter (can be NULL)
    public function base() {
        return $this->Base;
    }


    //! @return A child object with a specific key (NULL if not found)
    public function child(string $key) {
        return (array_key_exists($key, $this->Children)) ? $this->Children[$key] : NULL;
    }


    //! @return A list of all child objects
    public function children() {
        return array_values($this->Children);
    }


    /**
     * This function can be implemented by derived classes to implement extra effort at deriving.
     * For example this can be used to copy additional attributes/properties.
     */
    protected function cloneXtraAttributes(Deriveable $base) {
    }


    /**
     * Import data, that previously was exported with dataArrayExport()
     */
    public function dataArrayImport(array $data) {
        if (array_key_exists('CHLD', $data)) {
            foreach ($this->children() as $child) {
                if (array_key_exists($child->key(), $data['CHLD'])) {
                    $child->dataArrayImport($data['CHLD'][$child->key()]);
                }
            }
        }

        if (array_key_exists('DA', $data)) $this->derivedAccessability($data['DA']);
    }


   //! @return An array with all user data that need to be saved (visibility, values, etc.)
    public function dataArrayExport() {
        $da = array();

        $da['CHLD'] = array();
        foreach ($this->children() as $child) {
            $da['CHLD'][$child->key()] = $child->dataArrayExport();
        }

        $da['DA'] = $this->derivedAccessability();

        return $da;
    }


    /**
     * Set and read the accessability for derived items.
     * 0 - Not visible or editable
     * 1 - Visible (read-only)
     * 2 - Editable (and visible)
     * @param $access Set new accessability (ignore or set to NULL to read back only)
     * @return The current accessability level
     */
    public function derivedAccessability(int $access = NULL) {

        // set new accessability
        if ($access !== NULL) {
            if ($access < 0) {
                \Core\Log::warning("Limiting requested access '$access' to 0");
                $access = 0;
            }
            if ($access > 2) {
                \Core\Log::warning("Limiting requested access '$access' to 2");
                $access = 2;
            }
            if (($this->Base !== NULL) && ($access > $this->Base->derivedAccessability())) {
                \Core\Log::warning("Limiting requested access '$access' to " . $this->Base->derivedAccessability());
                $access = $this->Base->derivedAccessability();
            }
            $this->DerivedAccessability = $access;
        }

        // return current level
        if ($this->Base === NULL) return 2;
        else return $this->DerivedAccessability;
    }


    //! @return The description of this parameter
    public function description() {
        return ($this->Base === NULL) ? $this->Description : $this->Base->description();
    }


    //! Used internally to inform when a new child was added
    private function informChildLevels(int $new_child_levels) {

        // take over new amount of levels if more then currently known
        if ($new_child_levels > $this->MaxChildLevels) {
            $this->MaxChildLevels = $new_child_levels;

            // inform parent about new child level amount
            if ($this->parent() !== NULL) {
                $this->parent()->informChildLevels($new_child_levels + 1);
            }
        }
    }


    //! @return The key of this parameter (which is unique whithin a ParameterCollection
    public function key() {
        return ($this->Base === NULL) ? $this->Key : $this->Base->key();
    }


    //! @return A string, that concatenates all keys of the upward hierarchy wih '_' (eg RootKey_CollectionOne_CollectionTwo_CurrentItem)
    public function keySnake() {
        return $this->KeySnake;
    }


    //! @return The user visible name of this parameter
    public function label() {
        return ($this->Base == NULL) ? $this->Label : $this->Base->label();
    }


    //! @return The amount of chierharical children levels below
    public function maxChildLevels() {
        return $this->MaxChildLevels;
    }


    //! @return The parenting object
    public function parent() {
        return $this->Parent;
    }


    //! This function will check for HTTP POST/GEST form data and store the data into the collection
    public function storeHttpRequest() {

        // accessability
        $key_snake = $this->keySnake();
        if (array_key_exists("ParameterAccessability_$key_snake", $_REQUEST)) {
            $this->derivedAccessability($_REQUEST["ParameterAccessability_$key_snake"]);
        }

        // store http request for children
        foreach ($this->children() as $child) {
            $child->storeHttpRequest();
        }
    }
}
