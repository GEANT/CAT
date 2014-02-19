<?php
/***********************************************************************************
 * (c) 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");

require_once("Helper.php");
require_once("CAT.php");

require_once("common.inc.php");

function getImageFromDB($id) {

    $blob = FALSE;

    $blob = getBlobFromDB($id);

    // suppress E_NOTICE on the following... we are testing *if*
    // we have a serialized value - so not having one is fine and
    // shouldn't throw E_NOTICE
    if (@unserialize($blob) !== FALSE) { // an array? must be lang-tagged content
        $blob = unserialize($blob);
        if (!isset($blob['content']))
            return;
        $blob = base64_decode($blob['content']);
    } else {
        $blob = base64_decode($blob);
    }

    if ($blob === FALSE)
        return;

    // Set data type and caching for 30 days
    $info = new finfo();
    $filetype = $info->buffer($blob, FILEINFO_MIME_TYPE);
    header("Content-type: " . $filetype);
    if ($filetype == "text/rtf" || $filetype == "application/rtf")
        header("Content-Disposition: attachment; filename='download.rtf'");
    if ($filetype == "text/plain")
        header("Content-Disposition: attachment; filename='download.txt'");
    header("Cache-Control: must-revalidate");
    $offset = 60 * 60 * 24 * 30;
    $ExpStr = "Expires: " . gmdate("D, d M Y H:i:s", time() + $offset) . " GMT";
    header($ExpStr);

    //  Print out the image
    echo $blob;
}

// FIXME: if this is called directly, anyone can iterate over all images, even those that
// don't belong to him... fix that!

if (isset($_GET["id"])) {
    getImageFromDB($_GET["id"]);
} else {
    echo "No valid ID";
}
?>
