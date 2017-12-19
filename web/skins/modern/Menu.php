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
     * @param string $visibility
     * @param string $selectedLang
     */
    public function __construct($visibility = 'all', $selectedLang = '') {
        $langsArray = [];
        foreach (CONFIG['LANGUAGES'] as $lang => $value) {
            if ($lang == $selectedLang) {
                $langsArray[] = ['text'=>$value['display'], 'link'=>'javascript:changeLang("' . $lang . '")', 'class'=>'selected-lang'];
            } else {
                $langsArray[] = ['text'=>$value['display'], 'link'=>'javascript:changeLang("' . $lang . '")'];
            }
        }
        $this->menu = [['id' => 'start',
        'text' => _("Start page"),
        'visibility' => 'index'],
            ['id' => 'about',
                'text' => _("About"), 'link' => '', 'submenu' => [
                    ['text' => sprintf(_("About %s"), CONFIG['APPEARANCE']['productname']),
                        'catInfo' => ['about_cat', sprintf(_("About %s"), CONFIG['APPEARANCE']['productname'])]],
                    ['text' => sprintf(_("About %s"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']),
                        'link' => CONFIG_CONFASSISTANT['CONSORTIUM']['homepage']],
                ]],
            ['id' => 'lang',
                'text' => _("Language"), 'submenu' => $langsArray, ],
            ['id' => 'help',
                'text' => _("Help"), 'submenu' => [
                    ['text' => _("My institution is not listed"), 'catInfo' => ['idp_not_listed', _("FAQ")], 'visibility' => 'index'],
                    ['text' => _("My device is not listed"), 'catInfo' => ['device_not_listed', _("FAQ")], 'visibility' => 'index'],
                    ['text' => _("SB help item"), 'visibility' => 'sb', 'link'=>'xxx.php'],
                    ['text' => _("What is eduroam"), 'catInfo' => ['what_is_eduroam', _("FAQ")]],
                    ['text' => _("FAQ"), 'catInfo' => ['faq', _("FAQ")]],
                    ['text' => _("Contact"), 'catInfo' => ['contact', _("FAQ")]],
                    ['text' => _("Diagnostics"), 'link' => '/diag/diag.php'], 
                ]],
            ['id' => 'manage',
                'text' => _("Manage"), 'submenu' => [
                    ['text' => sprintf(_("%s admin access"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']),
                        'catInfo' => ['admin', sprintf(_("%s admin:<br>manage your IdP"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'])]],
                    ['text' => _("Become a CAT developer"),
                        'catInfo' => ['develop', _("Become a CAT developer")]],
                    ['text' => _("Documentation")],
                ],
                'visibility' => 'index'],
            ['id' => 'tou',
                'text' => _("Terms of use"), 'catInfo' => ['tou', 'TOU']],
        ];
        $this->visibility = $visibility;
    }
    public function printMenu($menu = NULL, $id = NULL) {
        $menu = $menu ?? $this->menu;
        if (count($menu) == 0) {
            return;
        }
        $out = "\n<ul>\n";
        foreach ($menu as $menuItem) {
            $itemVisibility = $menuItem['visibility'] ?? 'all';
            if ($this->visibility === 'all' || $itemVisibility === 'all' || $itemVisibility === $this->visibility) {
                $iD = $menuItem['id'] ?? $id;
                $catInfo = NULL;
                if (!empty($menuItem['catInfo'])) {
                    $catInfo = 'javascript:infoCAT("' . $iD . '", "' . $menuItem['catInfo'][0] . '","' . $menuItem['catInfo'][1] . '")';
                }
                if (!empty($menuItem['link']) && substr($menuItem['link'],0,1) === '/') {
                    $rootUrl = substr(CONFIG['PATHS']['cat_base_url'], -1) === '/' ? substr(CONFIG['PATHS']['cat_base_url'], 0, -1) : CONFIG['PATHS']['cat_base_url'];
                    $menuItem['link'] = $rootUrl . $menuItem['link'];
                }
                $link = $catInfo ?? $menuItem['link'] ?? CONFIG['PATHS']['cat_base_url'];
                $class = empty($menuItem['class']) ? '' : ' class="' . $menuItem['class'] . '"';
                $submenu = $menuItem['submenu'] ?? [];
                $out .= $this->printMenuItem($menuItem['text'], $link, $class);
                $out .= $this->printMenu($submenu, $iD);
                $out .= "</li>\n";
            }
        }
        $out .= '</ul>';
        return($out);
    }

    private function printMenuItem($itemText, $itemLink = '', $itemClass = '') {
        
        return "<li><a href='" . $itemLink . "'" . $itemClass . '>' . $itemText . "</a>";
    }
    

    private $menu;
    private $visibility;
}
