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

}
