<?php

class cTemplateacswui extends cTemplate {

    public function getHtml () {

        // document head
        $html  = "<!DOCTYPE html>\n";
        $html .= "<html>\n";
        $html .= "  <head>\n";
        $html .= "    <meta charset=\"utf-8\">\n";
        $html .= "    <title>acswui</title>\n";
        $html .= "    <link rel=\"stylesheet\" type=\"text/css\" href=\"templates/acswui/style.css\">\n";
        $html .= "    <link rel=\"stylesheet\" type=\"text/css\" href=\"templates/acswui/style_special.css\">\n";
        $html .= "  </head>\n";
        $html .= "  <body>\n";
        $html .= "    <div class=\"Header\">\n";
        $html .= "      Assetto Corsa Server Web User Interface\n";
        $html .= "    </div>\n";
        if ($this->ContentPage)
            $html .= "    <div class=\"Subtitle\">&nbsp;" . $this->ContentPage->PageTitle . "</div>\n";
        else
            $html .= "    <div class=\"Subtitle\">&nbsp;</div>\n";

        // main/sub navigation
        $html .= "    <ul class=\"MainMenu\">\n";
        foreach ($this->Menus as $mainMenuEntry) {
            $html .= "      <li>\n";

            # output submenu if menu is active
            if ( $mainMenuEntry->Active === True ) {
                $html .= "      <ul>\n";
                foreach ($mainMenuEntry->Menus as $subMenuEntry) {
                    $html .= "        <li>\n";
                    $a_class = ($subMenuEntry->Active === True) ? "class=\"active\"" : "";
                    $html .= "          <a href=\"" . $subMenuEntry->Url . "\" " . $a_class . ">" . $subMenuEntry->Name . "</a>\n";
                    $html .= "        </li>\n";
                }
                $html .= "      </ul>\n";
            }

            # main menu link
            $html .= "        <a href=\"" . $mainMenuEntry->Url . "\" " . (($mainMenuEntry->Active === True) ? "class=\"active\"" : "") . ">" . $mainMenuEntry->Name . "</a>\n";
            $html .= "      </li>\n";
        }
        $html .= "    </ul>\n";

        // content
        $html .= "    <div class=\"MainBody\">\n";
        if ($this->ContentPage) {
            $html .= "      <div id=\"" . str_replace("/", "_", $this->ContentPage->getRelPath()) . "\">\n";
            $html .= $this->ContentPage->getHtml();
        } else  {
            $html .= "      <div>\n";
        }
        $html .= "      </div>\n";
        $html .= "    </div>\n";
        $html .= "  </body>\n";
        $html .= "</html>\n";

        return $html;
    }
}

?>
