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
$auth = new \web\lib\admin\Authentication();
$auth->authenticate();
$validator = new \web\lib\common\InputValidation();
[$my_inst, $editMode] = $validator->existingIdPInt($_GET['inst_id'], $_SESSION['user']);
if ($editMode == 'fullaccess') {
    $hotspotProfiles = $my_inst->listDeployments();
    if (count($hotspotProfiles) > 0) { // no profiles yet.
       foreach ($hotspotProfiles as $counter => $deploymentObject) {
           if ($deploymentObject->institution == $_GET['inst_id'] && $deploymentObject->identifier == $_GET['dep_id']) {
               $cacert = file_get_contents(ROOT .  "/config/ManagedSPCerts/eduroamSP-CA.pem");
               $zip = new ZipArchive;
               $zip->open(ROOT . '/var/tmp/' . $deploymentObject->identifier.'.zip', ZipArchive::CREATE);
               if ($deploymentObject->radsec_priv != '') {
                    $zip->addFromString('priv.key', $deploymentObject->radsec_priv);
               }
               $zip->addFromString('cert.pem', $deploymentObject->radsec_cert);
               $zip->addFromString('ca.pem', $cacert);
               $zip->close();
               $data = file_get_contents(ROOT . '/var/tmp/' . $deploymentObject->identifier.'.zip');
               unlink(ROOT . '/var/tmp/' . $deploymentObject->identifier.'.zip');
               if ($data !== FALSE) {
                    header('Content-Type: application/zip');
                    header("Content-Disposition: attachment; filename=\"full_".$deploymentObject->identifier.".zip\"");
                    header("Content-Transfer-Encoding: binary");
                    echo $data;
               }
           }
       }
    }
}