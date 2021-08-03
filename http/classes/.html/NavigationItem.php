<?php

namespace html;


class NavigationItem {


    // The referred \html\Content object
    private $HtmlContentClass = NULL;

    // chache for listRootItems()
    private static $RootItemsList = NULL;

    // TRUE when this or any child item is active
    private $IsActive = NULL;

    // List of parents (only valid during construction of hierarchy)
    private $ParentNames = [];

    // storage of child items
    private $ChildItemsList = [];
    private $ChildItemsHash = array();


    public function __toString() {
        return get_class($this) . "(" . $this->HtmlContentClass->id() . ")";
    }


    /**
     * Adding a child NavigationItem under this items.
     * The child can be a sub-child of a child of this NavitationItem,
     * but in this case the hierarchical childs must already present.
     * @parem $child The (sub) child item
     */
    private function addChildItem(NavigationItem $child) {
        $child_id = $child->HtmlContentClass->id();

        // sanity checks
        if (count($child->ParentNames) == 0) {
            \core\Log::error("Insufficient parent amount for content '$childid'!");
        }
        if ($child->ParentNames[0] !== $this->HtmlContentClass->id()) {
            $this_id = $this->HtmlContentClass->id();
            $msg = "Invalid parent for content '$child_id'! ";
            $msg .= "Expecting '$this_id', got '" . $child->ParentNames[0] . "'!";
            \core\Log::error($msg);
        }

        // decrement parent names
        $child->ParentNames = array_slice($child->ParentNames, 1);

        // add as my child
        if (count($child->ParentNames) == 0) {
            $this->ChildItemsList[] = $child;
            $this->ChildItemsHash[$child_id] = $child;

        // add as child of child
        } else {
            $this->ChildItemsHash[$child_id]->addChildItem($child);
        }

        // check if active by child
        if ($this->ChildItemsHash[$child_id]->isActive()) $this->IsActive = TRUE;
    }


    //! @return A list of NavitationItem objects that are direct children of this
    public function childItems() {
        return $this->ChildItemsList;
    }


    //! @return A NavigationItem object that is constructed from a \content\html\* class name
    public static function constructFromClass(string $content_html_classname) {
        $navitem = NULL;

        // include source
        $include_file_path = "content/html/$content_html_classname.php";
        include_once $include_file_path;

        // instantiate
        $content_class = "\\content\\html\\$content_html_classname";
        $content_object = new $content_class();
        $navitem = new NavigationItem();
        $navitem->HtmlContentClass = $content_object;
        $navitem->ParentNames = $content_object->parentClasses();

        // check if active
        if (array_key_exists("ContentHtml", $_REQUEST) && $_REQUEST["ContentHtml"] == $navitem->HtmlContentClass->id()) {
            $navitem->IsActive = TRUE;
        } else {
            $navitem->IsActive = FALSE;
        }

        return $navitem;
    }


    //! @return TRUE when the current NavigationItem (or one of its childs is active)
    public function isActive() {
        return $this->IsActive;
    }


    //! An array of all available top-level NavigationItem objects
    public static function listRootItems() {
        if (NavigationItem::$RootItemsList === NULL) {

            NavigationItem::$RootItemsList = array();

            // scan all content files in directory
            $navrootitems = array();
            $navsubitems = array();
            foreach (scandir("content/html",SCANDIR_SORT_ASCENDING) as $entry) {

                // skip hidden files, directories and non-php files
                if (substr($entry, 0, 1) === ".") continue;
                if (is_dir("content/html/$entry")) continue;
                if (substr($entry, strlen($entry)-4, 4) != ".php") continue;

                // instantiate class
                $classname = substr($entry, 0, strlen($entry)-4);
                $navitem = NavigationItem::constructFromClass($classname);

                $navitems[] = $navitem;

                if (count($navitem->ParentNames) == 0) {
                    $navrootitems[$navitem->HtmlContentClass->id()] = $navitem;
                    NavigationItem::$RootItemsList[] = $navitem;
                } else {
                    $navsubitems[] = $navitem;
                }
            }

            // hierarchical adding of subitems to parent items
            $parent_count = 0;
            while (count($navsubitems) > 0) {
                $parent_count += 1;
                $navsubitems_new = array();

                // iterate over sub items and adding them as childs to root items
                foreach ($navsubitems as $navitem) {
                    if (count($navitem->ParentNames) == $parent_count) {
                        $navroot = $navrootitems[$navitem->ParentNames[0]];
                        $navroot->addChildItem($navitem);
                    } else {
                        $navsubitems[] = $navitem;
                    }
                }

                // take new array with processed elements removed
                $navsubitems = $navsubitems_new;
            }
        }

        return NavigationItem::$RootItemsList;
    }


    //! @return The name that shall be shown in user navigation menu
    public function name() {
        return $this->HtmlContentClass->menuName();
    }


    //! @return URL to this navigation item
    public function url() {
        return "index2.php?ContentHtml=" . $this->HtmlContentClass->id();
    }


}
