<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */
?>
<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");
require_once(dirname(dirname(dirname(__FILE__))) . "/admin/inc/input_validation.inc.php");

function attributionEurope() {
    $skinObject = new \core\Skinjob("");
    $logoBase = $skinObject->findResourceUrl("IMAGES");

    return "<span id='logos' style='position:fixed; left:50%;'><img src='$logoBase/dante.png' alt='DANTE' style='height:23px;width:47px'/>
              <img src='$logoBase/eu.png' alt='EU' style='height:23px;width:27px;border-width:0px;'/></span>
              <span id='eu_text' style='text-align:right;'><a href='http://ec.europa.eu/dgs/connect/index_en.htm' style='text-decoration:none; vertical-align:top;'>European Commission Communications Networks, Content and Technology</a></span>";
}

function footer() {
    $cat = new \core\CAT();
    echo "</div><!-- trick -->
          </div><!-- pagecontent -->";
    ?>
    <div class='footer'>
        <hr />
        <table style='width:100%'>
            <tr>
                <td style='padding-left:20px; padding-right:20px; text-align:left; vertical-align:top;'>
                    <?php echo $cat->CAT_COPYRIGHT; ?>
                </td>
                <td style='padding-left:80px; padding-right:20px; text-align:right; vertical-align:top;'>
                    <?php
                    if (CONFIG['CONSORTIUM']['name'] == "eduroam" && isset(CONFIG['CONSORTIUM']['deployment-voodoo']) && CONFIG['CONSORTIUM']['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
                        echo attributionEurope();
                    } else {
                        echo "&nbsp;";
                    }
                    ?>
                </td>
            </tr>
        </table>
    </div><!-- footer -->
    <?php echo "</div><!-- maincontent -->"; // was opened in header ?>
    </body>
    </html>
    <?php
}
