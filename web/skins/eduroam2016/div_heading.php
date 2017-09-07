<?php
require("Menu.php");
$langsArray = [];
$selectedLang = $Gui->langObject->getLang();
foreach (CONFIG['LANGUAGES'] as $lang => $value) {
     if ($lang == $selectedLang) {
         $langsArray[] = ['text'=>$value['display'], 'link'=>'javascript:changeLang("'.$lang.'")', 'class'=>'selected-lang'];
     } else {
         $langsArray[] = ['text'=>$value['display'], 'link'=>'javascript:changeLang("'.$lang.'")'];
     }
}


$visibility = $visibility ?? 'all';

$menu = new Menu([
    ['id'=>'start',
     'text'=>_("Start page"),
     'visibility' => 'index'],
    ['id'=>'about',
     'text'=>_("About"),'link'=>'','submenu'=>[
            ['text'=>sprintf(_("About %s"), CONFIG['APPEARANCE']['productname']),
             'catInfo'=>['about_cat',sprintf(_("About %s"), CONFIG['APPEARANCE']['productname'])]],
            ['text'=>sprintf(_("About %s"), CONFIG['CONSORTIUM']['name']),
             'link'=>CONFIG['CONSORTIUM']['homepage']],
        ]],
    ['id'=>'lang',
     'text'=>_("Language"), 'submenu'=>$langsArray,],
    ['id'=>'help',
     'text'=>_("Help"), 'submenu'=>[
            ['text'=>_("My institution is not listed"), 'catInfo'=>['idp_not_listed',_("FAQ")], 'visibility'=>'index'],
            ['text'=>_("My device is not listed"), 'catInfo'=>['device_not_listed',_("FAQ")], 'visibility'=>'index'],
            ['text'=>_("SB help item"),'visibility'=>'xxx'],
            ['text'=>_("What is eduroam"), 'catInfo'=>['what_is_eduroam',_("FAQ")]],
            ['text'=>_("FAQ"), 'catInfo'=>['faq',_("FAQ")]],
            ['text'=>_("Contact"), 'catInfo'=>['contact',_("FAQ")]],
        ]],
    ['id'=>'manage',
     'text'=>_("Manage"),'submenu'=>[
            ['text'=>sprintf(_("%s admin access"),CONFIG['CONSORTIUM']['name']),
             'catInfo'=>['admin',sprintf(_("%s admin:<br>manage your IdP"), CONFIG['CONSORTIUM']['name'])]],
            ['text'=>_("Become a CAT developer"),
             'catInfo'=>['develop',_("Become a CAT developer")]],
            ['text'=>_("Documentation")],
        ],
     'visibility' => 'index'],
    ['id'=>'tou',
     'text'=>_("Terms of use"), 'catInfo'=>['tou','TOU']],
    ],
    $visibility
);
?>

<div id="heading">
<?php
print '<div id="cat_logo">';
print '<img id="logo_img" src="'. $Gui->skinObject->findResourceUrl("IMAGES","consortium_logo.png").'" alt="Consortium Logo"/>';
print '<span>Configuration Assistant Tool</span>';
print '</div>';
print '<div id="motd">' . ( isset(CONFIG['APPEARANCE']['MOTD']) ? CONFIG['APPEARANCE']['MOTD'] : '&nbsp' ) . '</div>
<img id="hamburger" src="'. $Gui->skinObject->findResourceUrl("IMAGES","icons/menu.png").'" alt="Menu"/>';
print '<div id="menu_top">';
print $menu->printMenu();

        ?>
</div>
</div> <!-- id="heading" -->

