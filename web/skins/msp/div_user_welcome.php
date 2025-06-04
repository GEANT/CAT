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
?>
<div id="user_welcome"> <!-- this information is shown just pefore the download -->
    <strong><?php echo $Gui->textTemplates->templates[web\lib\user\WELCOME_ABOARD_HEADING] ?></strong>
    <p>
        <span id="download_info"><?php
/// the empty href is dynamically exchanged with the actual path by jQuery at runtime
            echo $Gui->textTemplates->templates[web\lib\user\WELCOME_ABOARD_DOWNLOAD];
            ?></span>
    <p>
        <?php printf(_("Dear user from %s,"), "<span class='inst_name'></span>") ?>
        <br/>
        <br/>
        <?php echo _("we would like to warmly welcome you among the several million users of eduroam®! From now on, you will be able to use internet access resources on thousands of universities, research centres and other places all over the globe. All of this completely free of charge!") ?>
    </p>
    <p>
        <?php echo _("Now that you have downloaded and installed a client configurator, all you need to do is find an eduroam® hotspot in your vicinity and enter your user credentials (this is our fancy name for 'username and password' or 'personal certificate') - and be online!") ?>
    <p>
        <?php printf(_("Should you have any problems using this service, please always contact the helpdesk of %s. They will diagnose the problem and help you out. You can reach them via the means shown above."), "<span class='inst_name'></span>") ?>
    </p>
    <p>
        <a href="javascript:back_to_downloads()"><strong><?php echo _("Back to downloads") ?></strong></a>
    </p>
</div> <!-- id="user_welcomer_page" -->

