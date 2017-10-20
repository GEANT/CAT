<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */
namespace web\lib\user;

require_once(ROOT."/config/_config.php");
/**
 * these constants live in the global space just to ease their use - with class
 * prefix, the names simply get too long for comfort
 */

const WELCOME_ABOARD_PAGEHEADING = 1000;
const WELCOME_ABOARD_DOWNLOAD = 1001;
const WELCOME_ABOARD_HEADING = 1002;  
const WELCOME_ABOARD_USAGE = 1003;
const WELCOME_ABOARD_PROBLEMS = 1004;
const WELCOME_ABOARD_BACKTODOWNLOADS = 1005;
const HEADING_TOPLEVEL_GREET = 1010;
const HEADING_TOPLEVEL_PURPOSE = 1011;
const FRONTPAGE_ROLLER_EASY = 1020;
const FRONTPAGE_ROLLER_CUSTOMBUILT = 1021;
const FRONTPAGE_ROLLER_SIGNEDBY = 1022;
const FRONTPAGE_BIGDOWNLOADBUTTON = 1023;
const INSTITUTION_SELECTION = 1030;
const PROFILE_SELECTION = 1040;
const DOWNLOAD_CHOOSE = 1050;
const DOWNLOAD_ALLPLATFORMS = 1051;
const DOWNLOAD_MESSAGE = 1052;
const DOWNLOAD_REDIRECT = 1053;
const DOWNLOAD_REDIRECT_CONTINUE = 1054;

/**
 * some of the texts we write are consortium-specific.
 */
const EDUROAM_WELCOME_ADVERTISING = 2000;

/**
 * provides various translated texts which are hopefully of common interest for
 * a number of skins.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
class TextTemplates {
    
    /**
     * An array with lots of template texts. 
     * 
     * HTML markup is used sparingly. Expect <br> <a> <b> <span> but nothing else. 
     * Remember that you can get plain text by using strip_tags()
     * 
     * @var array
     */
    public $templates;
    
    /**
     * Initialises the texts.
     */
    public function __construct(Gui $parent) {
        
        $this->templates[WELCOME_ABOARD_PAGEHEADING] = sprintf(_("Welcome aboard the %s user community!"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        $this->templates[WELCOME_ABOARD_DOWNLOAD] = _("Your download will start shortly. In case of problems with the automatic download please use this direct <a href=''>link</a>.");
        $this->templates[WELCOME_ABOARD_HEADING] = sprintf(_("Dear user from %s,"), "<span class='inst_name'></span>");
        $this->templates[WELCOME_ABOARD_USAGE] = sprintf(_("Now that you have downloaded and installed a client configurator, all you need to do is find an %s hotspot in your vicinity and enter your user credentials (this is our fancy name for 'username and password' or 'personal certificate') - and be online!"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        $this->templates[WELCOME_ABOARD_PROBLEMS] = sprintf(_("Should you have any problems using this service, please always contact the helpdesk of %s. They will diagnose the problem and help you out. You can reach them via the means shown above."), "<span class='inst_name'></span>");
        $this->templates[WELCOME_ABOARD_BACKTODOWNLOADS] = _("Back to downloads");
        $this->templates[EDUROAM_WELCOME_ADVERTISING] = sprintf(_("we would like to warmly welcome you among the several million users of %s! From now on, you will be able to use internet access resources on thousands of universities, research centres and other places all over the globe. All of this completely free of charge!"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        $this->templates[HEADING_TOPLEVEL_GREET] = sprintf(_("Welcome to %s"), CONFIG['APPEARANCE']['productname']);
        $this->templates[HEADING_TOPLEVEL_PURPOSE] = sprintf(_("Connect your device to %s"),CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        $this->templates[FRONTPAGE_ROLLER_EASY] = sprintf(_("%s installation made easy:"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        $this->templates[FRONTPAGE_ROLLER_CUSTOMBUILT] = sprintf(_("Custom built for your %s"),$parent->nomenclature_inst);
        $this->templates[FRONTPAGE_ROLLER_SIGNEDBY] = sprintf(_("Digitally signed by the organisation that coordinates %s: %s"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], CONFIG_CONFASSISTANT['CONSORTIUM']['signer_name']);
        $this->templates[FRONTPAGE_BIGDOWNLOADBUTTON] = sprintf(_("Click here to download your %s installer"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        $this->templates[PROFILE_SELECTION] = _("Select the user group");
        $this->templates[INSTITUTION_SELECTION] = _("select another");
        $this->templates[DOWNLOAD_CHOOSE] = _("Choose an installer to download");
        $this->templates[DOWNLOAD_ALLPLATFORMS] = _("All platforms");
        $this->templates[DOWNLOAD_MESSAGE] = sprintf(_("Download your %s installer"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']);
        $this->templates[DOWNLOAD_REDIRECT] = _("Your local administrator has specified a redirect to a local support page.<br>When you click <b>Continue</b> this support page will be opened in a new window/tab.");
        $this->templates[DOWNLOAD_REDIRECT_CONTINUE] = _("Continue");
    }
}