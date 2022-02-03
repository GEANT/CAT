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
<?php

require_once dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php";

$validator = new \web\lib\common\InputValidation();
$idRaw = $_GET["id"] ?? "";
$id = $validator->databaseReference($idRaw);
if ($id !== FALSE) {
    // check if data is public for this blob call
    $blob = \web\lib\admin\UIElements::getBlobFromDB($id['table'], $id['rowindex'], TRUE);
    if (is_bool($blob)) {
        echo "No valid ID";
    } else {
        $finalBlob = base64_decode($blob);
        // Set data type and caching for 30 days
        $info = new finfo();
        $filetype = $info->buffer($finalBlob, FILEINFO_MIME_TYPE);
        header("Content-type: " . $filetype);

        switch ($filetype) {
            case "text/rtf": // fall-through, same treatment
            case "application/rtf":
                header("Content-Disposition: attachment; filename=download.rtf");
                break;
            case "text/plain":
                header("Content-Disposition: attachment; filename=download.txt");
                break;
            default:
            // do nothing special with the Content-Disposition header
        }

        header("Cache-Control: must-revalidate");
        $offset = 60 * 60 * 24 * 30;
        // gmdate can't possibly fail, because it operates on time() and an integer offset
        $ExpStr = "Expires: " . /** @scrutinizer ignore-type */ gmdate("D, d M Y H:i:s", time() + $offset) . " GMT";
        header($ExpStr);
        //  Print out the image
        echo $finalBlob;
    }
} else {
    echo "No valid ID";
}
