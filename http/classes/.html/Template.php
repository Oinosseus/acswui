<?php

namespace html;

// Baseclass for templates
abstract class Template {

    // the following variables shall be set in the constructor of derived classes
    protected $TemplateName = NULL;
    protected $TemplateAuthor = NULL;

    // the name of the template directory
    // automatically determined
    private $Id = NULL;

    // cache variables
    private static $CacheTemplates = NULL;


    //! @return The author of the template
    public function author() {
        if ($this->TemplateAuthor === NULL) return get_class($this);
        else return $this->TemplateAuthor;
    }


    public function id() {
        return $this->Id;
    }


    // this must be implemented by a template class
    abstract public function getHtml();


    /**
     * Get a certain template identified by it's ID.
     * When the reuqested template could not be found, NULL is returned.
     * @param $id The ID of the requested template
     * @return A Template object or NULL
     */
    public static function getTemplate(string $id) {
        $requested_template = NULL;

        // scan for requested template
        foreach (Template::listTemplates() as $template) {
            if ($template->id() == $id) {
                $requested_template = $template;
                break;
            }
        }

        return $requested_template;
    }


    //! @return A list of all available Template objects
    public static function listTemplates() {

        // create cache
        if (Template::$CacheTemplates === NULL) {

            // initialize array
            Template::$CacheTemplates = array();

            // scan for templates
            foreach (scandir("templates/", SCANDIR_SORT_ASCENDING) as $template) {
                if (substr($template, 0, 1) === ".") continue;
                if (!is_dir("templates/$template")) continue;

                include("templates/$template/Template.php");
                $template_class = "\\templates\\$template\\Template";
                $template_object = new $template_class;
                $template_object->Id = $template;
                Template::$CacheTemplates[] = $template_object;
            }
        }

        return Template::$CacheTemplates;
    }


    //! @return The name of the template
    public function name() {
        if ($this->TemplateName === NULL) return get_class($this);
        else return $this->TemplateName;
    }


    public function pageTitle() {
        return "";
    }
}

