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
        $html .= "    <title>acswui</title>\n";
        $html .= "    <link rel=\"stylesheet\" type=\"text/css\" href=\"templates/acswui/style.css\">\n";
        $html .= "    <link rel=\"stylesheet\" type=\"text/css\" href=\"templates/acswui/style_dbentry.css\">\n";
        $html .= "    <link rel=\"stylesheet\" type=\"text/css\" href=\"templates/acswui/style_content.css\">\n";
        $html .= "  </head>\n";
        $html .= "  <body>\n";

        $html .= "<div id=\"UserBox\">";
//         if ($current_user === NULL) {
            $html .= \Core\UserManager::htmlLogInOut();
//         } else {
//         }
        $html .= "</div>";

        $html .= "    <header>\n";
        $html .= "      Assetto Corsa Server Web User Interface\n";
        $html .= "    </header>\n";
        if ($content) $html .= "    <div class=\"Subtitle\">&nbsp;" . $content->pageTitle() . "</div>\n";

        // main/sub navigation
        $html .= "    <nav><ul class=\"MainMenu\">\n";
        foreach (\Core\HtmlContent::listRootItems() as $navitem) {
            $html .= "      <li>\n";

            # output submenu if menu is active
            if ($navitem->isActive()) {
                $html .= "      <ul>\n";
                foreach ($navitem->childContents() as $subnavitem) {
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
