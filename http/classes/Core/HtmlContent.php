<?php

namespace Core;

/**
 * This is the base class for all html contents
 */
abstract class HtmlContent {

    private const BASESCRIPT = "index.php";

    //! TRUE when this or any child item is active in navigation hierarchy
    private $IsNavigated = NULL;

    //! A pointer to the Content object that is currently requested
    private static $NavigatedContent = NULL;

    //! Name that shall be shown in user navigation menu
    private $MenuName = NULL;

    //! chache for listRootItems()
    private static $RootItemsList = NULL;

    //! The path relative from content/html/ directory
    private static $HierarchicalContentPath = NULL;

    //! The name of the page that shall be shown in subtitle
    private $PageTitle = NULL;

    //! Automatically detceted -> use id() method
    private $Id = NULL;

    //! storage of child items
    private $ChildItemsList = NULL;


    /**
     *
     * @param $menu_name The name that shall be shown in user navigation menu
     * @param $page_title The title of the page that shall be shown to users
     */
    public function __construct(string $menu_name, string $page_title) {
        $this->MenuName = $menu_name;
        $this->PageTitle = $page_title;

        if (array_key_exists("HtmlContent", $_REQUEST) && $_REQUEST["HtmlContent"] == $this->id()) {
            $this->IsNavigated = TRUE;
            HtmlContent::$NavigatedContent = $this;
        } else {
            $this->IsNavigated = FALSE;
        }
    }


    public function __toString() {
        return get_class($this);
    }


    //! @return A list of Content objects that are direct children of this
    public function childContents() {
        if ($this->ChildItemsList === NULL) {
            $this->ChildItemsList = array();
            $path = $this->HierarchicalContentPath . "/" . $this->id();
//             echo "HERE: $path<br>";
            if (is_dir("content/html/$path")) {
                foreach (scandir("content/html/$path", SCANDIR_SORT_ASCENDING) as $entry) {
                    // skip hidden files, directories and non-php files
                    if (substr($entry, 0, 1) === ".") continue;
                    if (is_dir("content/html/$path/$entry")) continue;
                    if (substr($entry, strlen($entry)-4, 4) != ".php") continue;

                    // instantiate class
                    $classname = substr($entry, 0, strlen($entry)-4);
                    $content_inc = "content/html/$path/$classname.php";
                    include_once $content_inc;
                    $content_class = "\\Content\\Html\\$classname";
                    $content_object = new $content_class();
                    $content_object->HierarchicalContentPath = $path;
                    $content_object->childContents();

                    if ($content_object->IsNavigated) $this->IsNavigated = TRUE;
                    $this->ChildItemsList[] = $content_object;
                }
            }
        }

        return $this->ChildItemsList;
    }


    abstract protected function getHtml();


    //! @return The HTML string of the content
    public function html() {
        $html = $this->getHtml();
        return $html;
    }


    //! @return Unique id of this content item
    public function id() {
        if ($this->Id === NULL) {
            $this->Id = get_class($this);
            if ($pos = strrpos($this->Id, '\\')) {
                $this->Id = substr($this->Id, $pos + 1);
            } else {
                \Core\Log::error("Cannot determine classname from '$this->Id'!");
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
        if (HtmlContent::$RootItemsList === NULL) {

            HtmlContent::$RootItemsList = array();

            // scan all content files in directory
            $subitems = array();
            foreach (scandir("content/html", SCANDIR_SORT_ASCENDING) as $entry) {

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
                $content_object->HierarchicalContentPath = "";
                $content_object->childContents();

                HtmlContent::$RootItemsList[] = $content_object;
            }

        }

        return HtmlContent::$RootItemsList;
    }


    /**
     * @return The Name that shall be shown in the navigation menu
     */
    public function name() {
        return $this->MenuName;
    }


    //! @return The Content object that is navigated by the user (can be NULL)
    public static function navigatedContent() {

        // update cache
        if (HtmlContent::$RootItemsList === NULL) {
            HtmlContent::listRootItems();
        }

        // sanity check
        if (HtmlContent::$NavigatedContent === NULL) {
            if (array_key_exists("HtmlContent", $_REQUEST)) {
                $c = $_REQUEST["HtmlContent"];
                \Core\Log::warning("Content '$c' not found!");
            }
        }

        return HtmlContent::$NavigatedContent;
    }


    /**
     * Generates the HTML string for a new form element
     * The generated element will contain all necessary variables to allow navigation to current content
     * @param $method GET or POST
     * @param $id Used as id atribute (if not empty string)
     * @param HTML code with a new <form> element
     */
    public function newHtmlForm($method="GET", string $id = "") {
        $method = strtoupper($method);

        $html = "";
        $id = (strlen($id) > 0) ? "id=\"$id\"" : "";

        if ($method == "GET") {
            $html .= "<form method=\"GET\" action=\"" . HtmlContent::BASESCRIPT . "\" $id>";
            $html .= "<input type=\"hidden\" name=\"HtmlContent\" value=\"" . $this->id() . "\">";
        } else if ($method == "POST") {
            $html .= "<form method=\"GET\" action=\"" . $this->url() . "\" $id>";
        } else {
            \Core\Log::error("Undefined method '$method'!");
        }

        return $html;
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
    public function url(string $content = NULL, array $get_vars = array()) {
        $url = HtmlContent::BASESCRIPT;

        if ($content === NULL) {
            $url .="?HtmlContent=" . $this->id();
        } else {
            $url .="?HtmlContent=$content";
        }

        foreach ($get_vars as $key => $val) {
            $url .= "&$key=$val";
        }

        return $url;
    }
}
