<div id="roller">
    <div id="slides">
        <span id="line1"><?php printf(_("%s installation made easy:"), CONFIG['CONSORTIUM']['name']) ?></span>
        <span id="line2"></span>
        <span id="line3"></span>
        <span id="line4"><?php echo _("Custom built for your home institution") ?></span>
        <span id="line5">
            <?php
            if (!empty(CONFIG['CONSORTIUM']['signer_name'])) {
                printf(_("Digitally signed by the organisation that coordinates %s: %s"), CONFIG['CONSORTIUM']['name'], CONFIG['CONSORTIUM']['signer_name']);
            }
            ?>
        </span>
    </div>
    <div id = "img_roll">
        <img id="img_roll_0" src="<?php echo $Gui->skinObject->findResourceUrl("IMAGES","empty.png")?>" alt="Rollover 0"/> <img id="img_roll_1" src="<?php echo $Gui->skinObject->findResourceUrl("IMAGES","empty.png")?>" alt="Rollover 1"/>
    </div>
</div>
