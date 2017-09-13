<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");

/**
 * Menu class helps to define the menu on the main page
 */

class Menu {

    /**
     * the constructor takes an array argument defining menu items.
     * the array must be indexed by strings which will be passed to user/cat_info.php a the page argument
     * the values of the array can be either a simple string which is passed to user/cat_info.php
     * as the title argument or an two element array - the first element of this array will be
     * the title and the second is a style specification applied to the given menu item
     */
    public function __construct($menuArray,$visibility = 'all') {
        $this->menu = $menuArray;
        $this->visibility = $visibility;
    }
    public function printMenu($menu = NULL,$id=NULL) {
        $menu = $menu ?? $this->menu;
     if(count($menu) == 0) {
          return;
     }
        $out = "\n<ul>\n";
        foreach ($menu as $menuItem) {
            $itemVisibility = $menuItem['visibility'] ?? 'all';
            if ($this->visibility === 'all' || $itemVisibility === 'all' || $itemVisibility === $this->visibility) {
                $iD = $menuItem['id'] ?? $id;
                $catInfo = NULL;
                if (!empty($menuItem['catInfo'])) {
                    $catInfo = 'javascript:infoCAT("'.$iD.'", "'.$menuItem['catInfo'][0].'","'.$menuItem['catInfo'][1].'")';
                }
                $link = $catInfo ?? $menuItem['link'] ?? '';
                $class = empty($menuItem['class']) ? '' : ' class="'.$menuItem['class'].'"';
                $submenu  = $menuItem['submenu'] ?? [];
                $out .= $this->printMenuItem($menuItem['text'], $link, $class);
                $out .= $this->printMenu($submenu,$iD);
                $out .= "</li>\n";
             }
        }
       
        $out .= '</ul>';
        return($out);
    }

    private function printMenuItem($itemText,$itemLink = '',$itemClass = '') {
        return "<li><a href='" . $itemLink . "'".$itemClass.'>' . $itemText . "</a>";
    }

    private $menu;
    private $visibility;
}