<?php

namespace Core;

// Baseclass for templates
abstract class HtmlTemplate {

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
        foreach (HtmlTemplate::listTemplates() as $template) {
            if ($template->id() == $id) {
                $requested_template = $template;
                break;
            }
        }

        return $requested_template;
    }


    //! @return an array of scripts that need to be loaded with a template
    public function listScripts() {
        $l = array();
        $l[] = "js/general.js";
        $l[] = "js/parameter.js";
        $l[] = "https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js";

        // scripts requested by content
        $content = \Core\HtmlContent::navigatedContent();
        if ($content !== NULL) {
            foreach ($content->listScripts() as $s)
                $l[] = $s;
        }

        return $l;
    }


    //! @return A list of all available Template objects
    public static function listTemplates() {

        // create cache
        if (HtmlTemplate::$CacheTemplates === NULL) {

            // initialize array
            HtmlTemplate::$CacheTemplates = array();

            // scan for templates
            foreach (scandir("templates/", SCANDIR_SORT_ASCENDING) as $template) {
                if (substr($template, 0, 1) === ".") continue;
                if (!is_dir("templates/$template")) continue;

                include("templates/$template/Template.php");
                $template_class = "\\templates\\$template\\Template";
                $template_object = new $template_class;
                $template_object->Id = $template;
                HtmlTemplate::$CacheTemplates[] = $template_object;
            }
        }

        return HtmlTemplate::$CacheTemplates;
    }


    //! @return The name of the template
    public function name() {
        if ($this->TemplateName === NULL) return get_class($this);
        else return $this->TemplateName;
    }
}
