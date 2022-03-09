<?php
/*
 * *****************************************************************************
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Framework 
 * Programme 7 under Grant Agreements No. 238875 (GN3) and No. 605243 (GN3plus),
 * Horizon 2020 research and innovation programme under Grant Agreements No. 
 * 691567 (GN4-1) and No. 731122 (GN4-2).
 * On behalf of the aforementioned projects, GEANT Association is the sole owner
 * of the copyright in all material which was developed by a member of the GÉANT
 * project. GÉANT Vereniging (Association) is registered with the Chamber of 
 * Commerce in Amsterdam with registration number 40535155 and operates in the 
 * UK as a branch of GÉANT Vereniging.
 * 
 * Registered office: Hoekenrode 3, 1102BR Amsterdam, The Netherlands. 
 * UK branch address: City House, 126-130 Hills Road, Cambridge CB2 1PQ, UK
 *
 * License: see the web/copyright.inc.php file in the file structure or
 *          <base_url>/copyright.php after deploying the software
 */

namespace web\lib\common;

/**
 * This class defines constants for HTML form handling.
 * 
 * When submitting forms, the sending page's form and receiving page's POST/GET 
 * must have a common understanding on what's being transmitted. Rather than
 * using strings or raw integers, named constants are much prettier.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
class FormElements {

    const BUTTON_CLOSE = 0;
    const BUTTON_CONTINUE = 1;
    const BUTTON_DELETE = 2;
    const BUTTON_SAVE = 3;
    const BUTTON_EDIT = 4;
    const BUTTON_TAKECONTROL = 5;
    const BUTTON_PURGECACHE = 6;
    const BUTTON_FLUSH_AND_RESTART = 7;
    const BUTTON_SANITY_TESTS = 8;
    // Silverbullet buttons
    const BUTTON_TERMSOFUSE_ACCEPTED = 9;
    const BUTTON_ADDUSER = 10;
    const BUTTON_CHANGEUSEREXPIRY = 11;
    const BUTTON_REVOKEINVITATION = 12;
    const BUTTON_REVOKECREDENTIAL = 13;
    const BUTTON_DEACTIVATEUSER = 14;
    const BUTTON_NEWINVITATION = 15;
    const BUTTON_ACKUSERELIGIBILITY = 16;
    const BUTTON_SENDINVITATIONMAILBYADMIN = 17;
    const BUTTON_SENDINVITATIONMAILBYCAT = 18;
    const BUTTON_SENDINVITATIONSMS = 19;
    const BUTTON_ACTIVATE = 20;
    const BUTTON_TERMSOFUSE_NEEDACCEPTANCE = 21;
    const BUTTON_REMOVESP = 22;
}
