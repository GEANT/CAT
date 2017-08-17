<div class='footer' id='footer'>
    <table>
        <tr>
            <td>
            <?php
                echo $Gui->CAT_COPYRIGHT;
            ?>
            </td>
            <td>
            <?php

                if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo']) && CONFIG_CONFASSISTANT['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { 
echo "<span id='logos'><img src='".$Gui->skinObject->findResourceUrl("IMAGES","dante.png")."' alt='DANTE' style='height:23px;width:47px'/>
              <img src='".$Gui->skinObject->findResourceUrl("IMAGES","eu.png")."' alt='EU' style='height:23px;width:27px;border-width:0px;'/></span>
              <span id='eu_text' style='text-align:right; padding-left: 60px; display: block; '><a href='http://ec.europa.eu/dgs/connect/index_en.htm' style='text-decoration:none; vertical-align:top; text-align:right'>European Commission Communications Networks, Content and Technology</a></span>";

                } else {
                    echo "&nbsp;";
                }
            ?>
            </td>
        </tr>
    </table>
</div>
