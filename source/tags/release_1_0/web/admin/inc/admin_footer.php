<?php
/***********************************************************************************
 * (c) 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
</div><!-- trick -->
</div><!-- pagecontent -->
<div class='footer'>
    <hr />
    <?php
// this variable gets set during "make distribution" only
    $RELEASE = "THERELEASE";
    echo Config::$APPEARANCE['productname'] . " - ";
    if ($RELEASE != "THERELEASE")
        echo sprintf(_("Release %s"), $RELEASE);
    else {
        echo _("Unreleased SVN Revision");
    }
    ?>
    &copy; 2011-12 DANTE Ltd. on behalf of the GN3 consortium</div><!-- footer -->
</div><!-- maincontent -->
</body>
</html>
