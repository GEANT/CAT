<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

use web\lib\user;

/**
 * This class delivers various <div> elements for the front page.
 * 
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 */
class Divs {

    /**
     * The Gui object we are working with.
     * 
     * @var user\Gui
     */
    private $Gui;

    public function __construct(user\Gui $Gui) {
        $this->Gui = $Gui;
    }

    public function div_user_welcome() {
        $retval = "
<div id='user_welcome'> <!-- this information is shown just pefore the download -->
    <strong>" . $this->Gui->textTemplates->templates[user\WELCOME_ABOARD_PAGEHEADING] . "</strong>
    <p>
    <span id='download_info'>
    <!-- the empty href is dynamically exchanged with the actual path by jQuery at runtime -->
        " . $this->Gui->textTemplates->templates[user\WELCOME_ABOARD_DOWNLOAD] . "
    </span>
    <p>" . $this->Gui->textTemplates->templates[user\WELCOME_ABOARD_HEADING] . "
    <br/>
    <br/>";
        switch (CONFIG_CONFASSISTANT['CONSORTIUM']['name']) {
            case "eduroam": $retval .= $this->Gui->textTemplates->templates[user\EDUROAM_WELCOME_ADVERTISING];
                break;
            default:
        }
        $retval .= "
    </p>
    <p>" . $this->Gui->textTemplates->templates[user\WELCOME_ABOARD_USAGE] . "
    <p>" . $this->Gui->textTemplates->templates[user\WELCOME_ABOARD_PROBLEMS] . "
    </p>
    <p>
    <a href='javascript:back_to_downloads()'><strong>" . $this->Gui->textTemplates->templates[user\WELCOME_ABOARD_BACKTODOWNLOADS] . "</strong></a>
    </p>
</div> <!-- id='user_welcomer_page' -->
";
        return $retval;
    }

    public function div_top_welcome() {
        return "
<div id='welcome_top1'>
    " . $this->Gui->textTemplates->templates[user\HEADING_TOPLEVEL_GREET] . "
</div>
<div id='top_invite' class='signin'>
    " . $this->Gui->textTemplates->templates[user\HEADING_TOPLEVEL_PURPOSE] . "
</div>";
    }

    public function div_roller() {
        $retval = "
<div id='roller'>
    <div id='slides'>
        <span id='line1'>" . $this->Gui->textTemplates->templates[user\FRONTPAGE_ROLLER_EASY] . "</span>
        <span id='line2'></span>
        <span id='line3'></span>
        <span id='line4'>" . $this->Gui->textTemplates->templates[user\FRONTPAGE_ROLLER_CUSTOMBUILT] . "</span>
        <span id='line5'>";
        if (!empty(CONFIG_CONFASSISTANT['CONSORTIUM']['signer_name'])) {
            $retval .= $this->Gui->textTemplates->templates[user\FRONTPAGE_ROLLER_CUSTOMBUILT];
        }
        $retval .= "
        </span>
    </div>
    <div id = 'img_roll'>
        <img id='img_roll_0' src='" . $this->Gui->skinObject->findResourceUrl("IMAGES", "empty.png") . "' alt='Rollover 0'/> <img id='img_roll_1' src='" . $this->Gui->skinObject->findResourceUrl("IMAGES", "empty.png") . "' alt='Rollover 1'/>
    </div>
</div>";
        return $retval;
    }

    public function div_main_button() {
        return "
<div id='user_button_td'>
  <span id='signin'>
     <button class='large_button signin signin_large' id='user_button1'>
        <span id='user_button'>" . $this->Gui->textTemplates->templates[user\FRONTPAGE_BIGDOWNLOADBUTTON] . "
        </span>
     </button>
  </span>
  <span style='padding-left:50px'>&nbsp;</span>
</div>";
    }

}
