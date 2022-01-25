<?php

namespace Core;

/**
 * This is the base class for all json contents
 */
abstract class JsonContent {

    //! Defines if the current content can be shown
    private $IsPermitted = TRUE;


    //! @return a list of all available JSON content class names
    public static function availableContents() {
        $json_contents = array();
        foreach (scandir("content/json/", SCANDIR_SORT_ASCENDING) as $entry) {
            if (substr($entry, 0, 1) === ".") continue;
            if (is_dir("content/json/$entry")) continue;
            if (substr($entry, strlen($entry)-4, 4) != ".php") continue;
            $classname = substr($entry, 0, strlen($entry)-4);
            $json_contents[] = $classname;
        }
        return $json_contents;
    }


    //! Must be implemented by dervied classes and shall return an data array
    abstract protected function getDataArray();


    //! @return The JSON data as string
    public static function getContent() {

        // check fundamental permissions
        if (!\Core\UserManager::permitted("Json")) return "";

        // get requested content object
        $classname = $_GET["JsonContent"];
        if (!in_array($classname, JsonContent::availableContents())) {
            \Core\Log::warning("Invalid JSON content requested: '" . $classname . "!");
            return "";
        }

        // setup content object
        $content_inc = "content/json/$classname.php";
        include_once $content_inc;
        $content_class = "\\Content\\json\\$classname";
        $content_object = new $content_class();

        // check content permission
        if (!$content_object->IsPermitted) {
//             \Core\Log::debug("");
            return "";
        }

        $data = $content_object->getDataArray();
        return json_encode($data);
    }


    /**
     * Require for the content that the current user has a certain permission.
     * If the user does not have the permission, a 403 page is shown
     * @param $permission The required permission
     */
    protected function requirePermission(string $permission) {
        $this->IsPermitted &= \Core\UserManager::permitted($permission);
    }
}
