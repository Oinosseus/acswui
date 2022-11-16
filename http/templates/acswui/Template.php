<?php

namespace Templates\acswui;

class Template extends \Core\HtmlTemplate {

    public function __construct() {
        $this->TemplateName = "ACswui";
        $this->TemplateAuthor = "Thomas Weinhold";
    }

    public function getHtml () {
        $content = \Core\HtmlContent::navigatedContent();
        $current_user = \Core\UserManager::loggedUser();

        // document head
        $html  = "<!DOCTYPE html>\n";
        $html .= "<html>\n";
        $html .= "  <head>\n";
        $html .= "    <meta charset=\"utf-8\">\n";
        $html .= "    <title>ACswui</title>\n";
        $html .= "    <link rel=\"stylesheet\" type=\"text/css\" href=\"templates/acswui/style.css\">\n";
        $html .= "    <link rel=\"stylesheet\" type=\"text/css\" href=\"templates/acswui/style_dbentry.css\">\n";
        $html .= "    <link rel=\"stylesheet\" type=\"text/css\" href=\"templates/acswui/style_content.css\">\n";
        $html .= "    <link rel=\"stylesheet\" type=\"text/css\" href=\"templates/acswui/style_functional.css\">\n";
        $html .= "    <link rel=\"stylesheet\" type=\"text/css\" href=\"templates/acswui/style_parameter.css\">\n";
        $html .= "    <script src=\"templates/acswui/style_chart.js\"></script>";
        foreach ($this->listScripts() as $s) $html .= "<script src=\"$s\"></script>";
        $html .= "  </head>\n";
        $html .= "  <body>\n";

        $html .= "<div id=\"UserBox\">";
        if ($current_user !== NULL) {
            $html .= $current_user->html();
        }
        $html .= "</div>";

        $html .= "    <header>\n";
        $html .= "      Assetto Corsa Server Web User Interface\n";
        $html .= "    </header>\n";
        if ($content) $html .= "    <div class=\"Subtitle\">&nbsp;" . $content->pageTitle() . "</div>\n";

        // main/sub navigation
        $html .= "    <nav><ul class=\"MainMenu\">\n";
        foreach (\Core\HtmlContent::listRootItems() as $navitem) {
            if (!$navitem->permitted()) continue;

            $html .= "      <li>\n";

            # output submenu if menu is active
            if ($navitem->isActive()) {
                $html .= "      <ul>\n";
                foreach ($navitem->childContents() as $subnavitem) {
                    if (!$subnavitem->permitted()) continue;
                    $html .= "        <li>\n";
                    $a_class = ($subnavitem->isActive()) ? "class=\"active\"" : "";
                    $html .= "          <a href=\"" . $subnavitem->url() . "\" " . $a_class . ">" . $subnavitem->name() . "</a>\n";
                    $html .= "        </li>\n";
                }
                $html .= "      </ul>\n";
            }

            # main menu link
            $html .= "        <a href=\"" . $navitem->url() . "\" " . (($navitem->isActive() === True) ? "class=\"active\"" : "") . ">" . $navitem->name() . "</a>\n";
//             $html .= "NAV: " . $navitem->name();
            $html .= "      </li>\n";
        }
        $html .= "    </ul></nav>\n";

        // content
        if ($content !== NULL) {
            $main_class = $content->id();
            $html .= "    <main class=\"$main_class\">\n";
            $html .= $content->html();
            $html .= "    </main>\n";
        }

        // done
        $html .= "  </body>\n";
        $html .= "</html>\n";

        return $html;
    }
}
