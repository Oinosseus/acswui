<?php

namespace html;

/**
 * This is the base class for all html contents
 */
abstract class Content {

    //! TRUE when this or any child item is active in navigation hierarchy
    private $IsNavigated = NULL;

    //! Name that shall be shown in user navigation menu
    private $MenuName = NULL;

    //! List of the parenting menu item names (only used and valid during construction)
    private $ParentClassNames = [];

    //! chache for listRootItems()
    private static $RootItemsList = NULL;

    //! The name of the page that shall be shown in subtitle
    private $PageTitle = NULL;

    //! Automatically detceted
    private $Id = NULL;

    //! storage of child items
    private $ChildItemsList = [];
    private $ChildItemsHash = array();


    /**
     *
     * @param $menu_name The name that shall be shown in user navigation menu
     * @param $page_title The title of the page that shall be shown to users
     * @param $parent_class_names A 1D array of content class names that are parents of this content
     */
    public function __construct(string $menu_name, string $page_title, array $parent_class_names) {
        $this->MenuName = $menu_name;
        $this->PageTitle = $page_title;
        $this->ParentClassNames = $parent_class_names;
        $this->IsNavigated =  array_key_exists("ContentHtml", $_REQUEST) && $_REQUEST["ContentHtml"] == $this->id();
    }


    public function __toString() {
        return get_class($this);
    }


    /**
     * Adding a child Content under this.
     * The child can be a sub-child of a child of this Content,
     * but in this case the hierarchical childs must already present.
     * @parem $child The (sub) child item
     */
    private function assignChild(Content $child) {
        $child_id = $child->id();

        // sanity checks
        if (count($child->ParentClassNames) == 0) {
            \core\Log::error("Insufficient parent amount for content '$child_id'!");
        }
        if ($child->ParentClassNames[0] !== $this->id()) {
            $this_id = $this->id();
            $msg = "Invalid parent for content '$child_id'! ";
            $msg .= "Expecting '$this_id', got '" . $child->ParentClassNames[0] . "'!";
            \core\Log::error($msg);
        }

        // decrement parent names
        $child->ParentClassNames = array_slice($child->ParentClassNames, 1);

        // add as my child
        if (count($child->ParentClassNames) == 0) {
            $this->ChildItemsList[] = $child;
            $this->ChildItemsHash[$child_id] = $child;

        // add as child of child
        } else {
            $this->ChildItemsHash[$child_id]->assignChild($child);
        }

        // check if active by child
        if ($this->ChildItemsHash[$child_id]->isNavigated()) $this->IsNavigated = TRUE;
    }


    //! @return A list of Content objects that are direct children of this
    public function childContents() {
        return $this->ChildItemsList;
    }


    abstract public function getHtml();


    //! @return Unique id of this content item
    public function id() {
        if ($this->Id === NULL) {
            $this->Id = get_class($this);
            if ($pos = strrpos($this->Id, '\\')) {
                $this->Id = substr($this->Id, $pos + 1);
            } else {
                \core\Log::error("Cannot determine classname from '$this->Id'!");
            }
        }
        return $this->Id;
    }


    //! @return TRUE when the current Content is active in navigation (or one of its childs is active)
    public function isNavigated() {
        return $this->IsNavigated;
    }

    public function isActive() {
        return $this->isNavigated();
    }


    //! An array of all available top-level Content objects
    public static function listRootItems() {
        if (Content::$RootItemsList === NULL) {

            Content::$RootItemsList = array();

            // scan all content files in directory
            $rootitems = array();
            $subitems = array();
            foreach (scandir("content/html",SCANDIR_SORT_ASCENDING) as $entry) {

                // skip hidden files, directories and non-php files
                if (substr($entry, 0, 1) === ".") continue;
                if (is_dir("content/html/$entry")) continue;
                if (substr($entry, strlen($entry)-4, 4) != ".php") continue;

                // instantiate class
                $classname = substr($entry, 0, strlen($entry)-4);
                $content_inc = "content/html/$classname.php";
                include_once $content_inc;
                $content_class = "\\content\\html\\$classname";
                $content_object = new $content_class();

                if (count($content_object->ParentClassNames) == 0) {
                    $rootitems[$content_object->id()] = $content_object;
                    Content::$RootItemsList[] = $content_object;
                } else {
                    $subitems[] = $content_object;
                }
            }

            // hierarchical adding of subitems to parent items
            $parent_count = 0;
            while (count($subitems) > 0) {
                $parent_count += 1;
                $subitems_new = array();

                // iterate over sub items and adding them as childs to root items
                foreach ($subitems as $item) {
                    if (count($item->ParentClassNames) == $parent_count) {
                        $root = $rootitems[$item->ParentClassNames[0]];
                        $root->assignChild($item);
                    } else {
                        $subitems[] = $item;
                    }
                }

                // take new array with processed elements removed
                $subitems = $subitems_new;
            }
        }

        return Content::$RootItemsList;
    }


    /**
     * @return The Name that shall be shown in the navigation menu
     */
    public function name() {
        return $this->MenuName;
    }


    /**
     * @return A list of parenting Content class names (to generate a hierarchical navigation menu)
     */
    public function parentClasses() {
        return $this->ParentClassNames;
    }


    /**
     * @return The (sub) title for the current page
     */
    public function pageTtitle() {
        return $this->PageTitle;
    }


    //! @return URL to this Content item
    public function url() {
        return "index2.php?ContentHtml=" . $this->id();
    }
}

