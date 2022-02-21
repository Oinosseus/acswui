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
    private $HierarchicalContentPath = NULL;

    //! The name of the page that shall be shown in subtitle
    private $PageTitle = NULL;

    //! Automatically detceted -> use id() method
    private $Id = NULL;

    //! storage of child items
    private $ChildItemsList = NULL;

    //! Defines if the current content can be shown
    private $IsPermitted = TRUE;

    //! Defines that this derived class is the default content
    private $IsHomePage = FALSE;

    // HtmlID for newHtmlContentCheckbox()
    private $HtmlContentCheckbox = 0;

    // A list of scripts that are requested by derived content class
    private $ContentScripts = array();


    /**
     *
     * @param $menu_name The name that shall be shown in user navigation menu
     * @param $page_title The title of the page that shall be shown to users
     */
    public function __construct(string $menu_name, string $page_title) {
        $this->MenuName = $menu_name;
        $this->PageTitle = $page_title;

        if (array_key_exists("HtmlContent", $_REQUEST)) {
            if ($_REQUEST["HtmlContent"] == $this->id()) {
                $this->IsNavigated = TRUE;
                HtmlContent::$NavigatedContent = $this;
            } else {
                $this->IsNavigated = FALSE;
            }
        } else if ($this->IsHomePage) {
            $this->IsNavigated = TRUE;
            HtmlContent::$NavigatedContent = $this;
        } else {
            $this->IsNavigated = FALSE;
        }
    }


    public function __toString() {
        return get_class($this);
    }


    /**
     * This must be only called in the constructor of the derived class
     * @param $filename The name of the script file (no paths)
     */
    protected function addScript($filename) {
        if (!in_array($filename, $this->ContentScripts))
            $this->ContentScripts[] = "js/" . $filename;
    }


    //! @return A list of Content objects that are direct children of this
    public function childContents() {
        if ($this->ChildItemsList === NULL) {
            $this->ChildItemsList = array();
            $path = $this->HierarchicalContentPath . "/" . $this->id();
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
                    if ($content_object->permitted()) {
                        $content_object->HierarchicalContentPath = $path;
                        $content_object->childContents();

                        if ($content_object->IsNavigated) $this->IsNavigated = TRUE;
                        $this->ChildItemsList[] = $content_object;
                    }
                }
            }
        }

        return $this->ChildItemsList;
    }


    abstract protected function getHtml();


    //! @return The HTML string of the content
    public function html() {
        $html = "";

        if ($this->permitted())
            $html .= $this->getHtml();

        if (!$this->permitted()) {
            $html = "403 Not Permitted!";
        }

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
                if ($content_object->permitted()) {
                    $content_object->HierarchicalContentPath = "";
                    $content_object->childContents();

                    HtmlContent::$RootItemsList[] = $content_object;
                }
            }

        }

        return HtmlContent::$RootItemsList;
    }


    //! @return a list of scripts requested to be included (intended to be used by \Core\HtmlTemplate::listScripts()
    public function listScripts() {
        return $this->ContentScripts;
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
     * Generate a new checkbox input with a custom content
     * @param $var_name The nam for the $_REQUEST variable
     * @param $content The HTML content for the checkbox
     * @param $checked Default checkbox state
     * @param $disabled Disable the checkbox
     */
    public function newHtmlContentCheckbox(string $var_name, string $content, bool $checked=FALSE, bool $disabled=FALSE) {
        $html = "";
        $checked = ($checked) ? "checked=\"yes\"" : "";
        $disabled = ($disabled) ? "disabled=\"yes\"" : "";

        ++$this->HtmlContentCheckbox;
        $id = "HtmlContentCheckbox" . $this->HtmlContentCheckbox;

        $html .= "<div class=\"HtmlContentCheckbox\">";
        $html .= "<input type=\"checkbox\" name=\"$var_name\" id=\"$id\" $checked $disabled>";
        $html .= "<label for=\"$id\">$content</label>";
        $html .= "</div>";

        return $html;
    }



    /**
     * Generate a new radio input with a custom content
     * @param $var_name The name for the radio ($_REQUEST variable)
     * @param $var_value The value for this raio input
     * @param $content The HTML content for the radio element
     * @param $checked Default checkbox state
     * @param $disabled Disable the checkbox
     */
    public function newHtmlContentRadio(string $var_name, string $var_value, string $content, bool $checked=FALSE, bool $disabled=FALSE) {
        $html = "";
        $checked = ($checked) ? "checked=\"yes\"" : "";
        $disabled = ($disabled) ? "disabled=\"yes\"" : "";

        ++$this->HtmlContentCheckbox;
        $id = "HtmlContentCheckbox" . $this->HtmlContentCheckbox;

        $html .= "<div class=\"HtmlContentRadio\">";
        $html .= "<input type=\"radio\" name=\"$var_name\" value=\"$var_value\" id=\"$id\" $checked $disabled>";
        $html .= "<label for=\"$id\">$content</label>";
        $html .= "</div>";

        return $html;
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
            $html .= "<form method=\"POST\" action=\"" . $this->url($_GET) . "\" $id>";
        } else {
            \Core\Log::error("Undefined method '$method'!");
        }

        return $html;
    }


    /**
     * Generates a checkbox element to indicate if a table column shall be deleted (or not).
     * This must be placed within a <tr><td> construct.
     * When the column shall be deleted, all <td> elements in the same column gets the CSS class 'ColumnWillBeDeleted' assigned
     * @param $var_name A custom name for the checkbox element (will be available as $_REQUEST variable on form submission)
     */
    public function newHtmlTableColumnDeleteCheckbox(string $var_name) {
        $html = "";

        $html .= "<label class=\"HtmlContentDeleteCheckbox\">";
        $html .= "<div class=\"DeleteRowIcon\" title=\"" . _("Delete Column Entry") . "\">&#x2612;</div>";
        $html .= "<div class=\"UnDeleteRowIcon\" title=\"" . _("Not Delete Column Entry") . "\">&#x267b;</div>";
        $html .= "<input type=\"checkbox\" name=\"$var_name\" onClick=\"toggleTableColumnDelete(this)\">";
        $html .= "</label>";

        return $html;
    }


    /**
     * Generates a checkbox element to indicate if a table row shall be deleted (or not).
     * This must be placed within a <tr><td> construct.
     * When the row shall be deleted, the parenting <tr> element gets the CSS class 'RowWillBeDeleted' assigned
     * @param $var_name A custom name for the checkbox element (will be available as $_REQUEST variable on form submission)
     */
    public function newHtmlTableRowDeleteCheckbox(string $var_name) {
        $html = "";

        $html .= "<label class=\"HtmlContentDeleteCheckbox\">";
        $html .= "<div class=\"DeleteRowIcon\" title=\"" . _("Delete Row Entry") . "\">&#x2612;</div>";
        $html .= "<div class=\"UnDeleteRowIcon\" title=\"" . _("Not Delete Row Entry") . "\">&#x267b;</div>";
        $html .= "<input type=\"checkbox\" name=\"$var_name\" onClick=\"toggleTableRowDelete(this)\">";
        $html .= "</label>";

        return $html;
    }


    /**
     * @return A list of parenting Content class names (to generate a hierarchical navigation menu)
     */
    public function parentClasses() {
        return $this->ParentClassNames;
    }


    /**
     * Parses a markdown coded string into HTML
     * @param $markdown A string with markdown markup
     * @return a html string
     */
    public function parseMarkdown(string $markdown) {

        $search = array();
        $replace = array();

        // link [Beschriftung des Hyperlinks](https://de.wikipedia.org/ "Titel, der beim Überfahren mit der Maus angezeigt wird")
        $search[] = '/\[([^\]]+)\]\(([^)"]+)\"([^"]+)\"\)/';
        $replace[] = '<a href="${2}" title="${3}">${1}</a>';

        $html = preg_replace($search, $replace, $markdown);
        return $html;
    }


    /**
     * @return The (sub) title for the current page
     */
    public function pageTitle() {
        return $this->PageTitle;
    }


    //! @return TRUE if current user is permitted to view this content
    public function permitted() {
        return $this->IsPermitted;
    }



    /**
     * Require for the content that the current user has a certain permission.
     * If the user does not have the permission, a 403 page is shown
     * @param $permission The required permission
     */
    protected function requirePermission(string $permission) {
        $this->IsPermitted &= \Core\UserManager::permitted($permission);
    }


    /**
     * Set this content as defualt homepage.
     * Should only be called in only one derived class.
     * Must be called before parent constructor.
     *
     * Example:
     * class MyHomePage extends \core\HtmlContent {
     *     public function __construct() {
     *         $this->setThisAsHomePage();
     *         parent::__construct(_("Home Page"),  _("This is the default lanmding page"));
     *     }
     * }
     *
     */
    protected function setThisAsHomePage() {
        $this->IsHomePage = TRUE;
    }


    //! @return URL to this Content item
    public function url(array $get_vars = array(), string $content = NULL) {
        $url = HtmlContent::BASESCRIPT;

        if ($content === NULL) {
            $url .="?HtmlContent=" . $this->id();
        } else {
            $url .="?HtmlContent=$content";
        }

        foreach ($get_vars as $key => $val) {
            if ($key == "HtmlContent") continue;
            $url .= "&$key=$val";
        }

        return $url;
    }
}
