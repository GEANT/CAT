<?php

/* * *********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

function footer() {
    echo "</div><!-- trick -->
          </div><!-- pagecontent -->
          <div class='footer'>
          <hr />
          <table style='width:100%'>
            <tr>
                <td style='padding-left:20px; padding-right:20px; text-align:left; vertical-align:top;'>";
// this variable gets set during "make distribution" only
    $RELEASE = "THERELEASE";
    echo Config::$APPEARANCE['productname'] . " - ";
    if ($RELEASE != "THERELEASE")
        echo sprintf(_("Release %s"), $RELEASE);
    else {
        echo _("Unreleased SVN Revision");
    }

    echo "&copy; 2011-14 DANTE Ltd. on behalf of the GN3 and GN3plus consortia";
    echo "</td>
          <td style='padding-left:80px; padding-right:20px; text-align:right; vertical-align:top;'>";
    if (Config::$CONSORTIUM['name'] == "eduroam" && isset(Config::$CONSORTIUM['deployment-voodoo']) && Config::$CONSORTIUM['deployment-voodoo'] == "Operations Team") { // SW: APPROVED
        $logo_base = $_SERVER['HTTP_HOST'];
        if (strpos($_SERVER['PHP_SELF'], "admin/") === FALSE)
            $logo_base .= substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/")) . "/resources/images";
        else
            $logo_base .= substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/admin/")) . "/resources/images";
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on")
            $logo_base = "https://" . $logo_base;
        else
            $logo_base = "http://" . $logo_base;

        echo "<span id='logos' style='position:fixed; left:50%;'><img src='$logo_base/dante.png' alt='DANTE' style='height:23px;width:47px'/>
              <img src='$logo_base/eu.png' alt='EU' style='height:23px;width:27px;border-width:0px;'/></span>
              <span id='eu_text' style='text-align:right;'><a href='http://ec.europa.eu/dgs/connect/index_en.htm' style='text-decoration:none; vertical-align:top;'>European Commission Communications Networks, Content and Technology</a></span>";
    }
    else {
        echo "&nbsp;";
    }
    echo "</td>
         </tr>
        </table>";
    echo "</div><!-- footer -->
          </div><!-- maincontent -->
    </body>
</html>";
}
?>
