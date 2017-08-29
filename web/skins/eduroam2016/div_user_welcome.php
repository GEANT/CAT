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

