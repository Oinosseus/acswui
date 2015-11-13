<?php

class cTemplate {

  // []['text' / 'page' / 'submenu']
  // submenu contains an array []['text' / 'page']
  private $mainMenuArray = array();
  
  // title of actual page
  private $pageTitle;
  
  // content storage
  private $content;
  
  // if set to >0 an auto-reload statement is inserted in the header
  private $autoRefresh;
  
  function __construct($PAGE) {
    $this->mainMenuArray = array();
    $this->PAGE = $PAGE;
    $this->content = "";
    $this->pageTitle = "";
    $this->autoRefresh = 0;
  }
  
  // add html content
  function addContent($content) {
    $this->content .= $content . "\n";
  }
  
  function setPageTitle($pageTitle) {
    $this->pageTitle = $pageTitle;
  }
  
  function autoRefresh($seconds) {
    $seconds = intval($seconds);
    if ($this->autoRefresh < 1 || $seconds < $this->autoRefresh) $this->autoRefresh = $seconds;
  }

  function addMainMenu($text, $page) {
    $newIndex = count($this->mainMenuArray);
    $this->mainMenuArray[$newIndex]['text'] = $text;
    $this->mainMenuArray[$newIndex]['page'] = $page;
    $this->mainMenuArray[$newIndex]['submenu'] = array();
    return $newIndex;
  }
  
  function addSubMenu($MainMenuIndex, $text, $page) {
    $newIndex = count($this->mainMenuArray[$MainMenuIndex]['submenu']);
    $this->mainMenuArray[$MainMenuIndex]['submenu'][$newIndex]['text'] = $text;
    $this->mainMenuArray[$MainMenuIndex]['submenu'][$newIndex]['page'] = $page;
  }
  
  function getTemplate() {
    $template  = "<!DOCTYPE html>\n";
    $template .= "<html>\n";
    $template .= "  <head>\n";
    $template .= "    <title>acswui</title>\n";
    $template .= "    <link rel=\"stylesheet\" type=\"text/css\" href=\"templates/acswui/style.css\">\n";
    
    if ($this->autoRefresh > 0)
      $template .= "    <meta http-equiv=\"refresh\" content=\"" . $this->autoRefresh . "\";>\n";
    
    $template .= "  </head>\n";
    $template .= "  <body>\n";
    $template .= "    <div class=\"Header\">\n";
    $template .= "      Assetto Corsa Server Web User Interface\n";
    $template .= "    </div>\n";
    $template .= "    <div class=\"Subtitle\">" . $this->pageTitle . "</div>\n";
 
    
    // insert navigation
    $template .= "    <ul class=\"MainMenu\">\n";
    
    foreach ($this->mainMenuArray as $mainMenuEntry) {
      $template .= "      <li>\n";
      $menuIsActive = False;
      
      # check if submenu was clicked
      if ($this->PAGE == $mainMenuEntry['page']) $menuIsActive = True;
      foreach ($mainMenuEntry['submenu'] as $subMenuEntry) {
        if ($this->PAGE == $subMenuEntry['page']) $menuIsActive = True;
      }

      # output submenu if menu is active
      if ( $menuIsActive === True ) {
        $template .= "      <ul>\n";
        foreach ($mainMenuEntry['submenu'] as $subMenuEntry) {
          $template .= "        <li>\n";
          $a_class = ($this->PAGE == $subMenuEntry['page']) ? "class=\"active\"" : "";
          $template .= "          <a href=\"?PAGE=" . $subMenuEntry['page'] . "\" " . $a_class . ">" . $subMenuEntry['text'] . "</a>\n";
          $template .= "        </li>\n";
          if ($this->PAGE == $subMenuEntry['page']) $main_a_class = "class=\"active\"";
        }
        $template .= "      </ul>\n";
      }
      
      # main menu link
      $template .= "        <a href=\"?PAGE=" . $mainMenuEntry['page'] . "\" " . (($menuIsActive===True) ? "class=\"active\"" : "") . ">" . $mainMenuEntry['text'] . "</a>\n";
      $template .= "      </li>\n";
    }

    $template .= "    </ul>\n";
    
    
    
    $template .= "    <div class=\"MainBody\">\n";
    $template .= $this->content;
    $template .= "    </div>\n";
    $template .= "  </body>\n";
    $template .= "</html>\n";
    return $template;
  }
  
}


?>
