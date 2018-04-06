<?php

// const BLACKALGOS = ["DSA-SHA1", "RSA-MD2", "RSA-MD5"];
const BLACKALGOS = ["RSA-MD2"];
include("../config/_config.php");
const BLACKONLY = TRUE;
$blackinsts = [];
$db = core\DBConnection::handle("INST");
$query = $db->exec("select institution_id as id, option_value from institution_option where option_name = 'eap:ca_file'");
$x509 = new \core\common\X509();
while ($a = mysqli_fetch_object($query)) {
    $cert = $x509->processCertificate(base64_decode($a->option_value));
    if (!BLACKONLY || in_array($cert['full_details']['signatureTypeSN'], BLACKALGOS)) {
        echo "Signature algorithm: " . $cert['full_details']['signatureTypeSN'] . " , key size " . $cert['full_details']['public_key_length'];
        if (BLACKONLY) {
            echo ", inst ID = ".$a->id;
            $blackinsts[] = $a->id;
        }
        echo "\n";
    }
}
$query2 = $db->exec("select pro.inst_id as id, opt.option_value as option_value from profile pro, profile_option opt where option_name = 'eap:ca_file' and opt.profile_id = pro.profile_id");
while ($a = mysqli_fetch_object($query2)) {
    $cert = $x509->processCertificate(base64_decode($a->option_value));
    if (!BLACKONLY || in_array($cert['full_details']['signatureTypeSN'], BLACKALGOS)) {
        echo "Signature algorithm: " . $cert['full_details']['signatureTypeSN'] . " , key size " . $cert['full_details']['public_key_length'];
        if (BLACKONLY) {
            echo ", inst ID = ".$a->id;
            $blackinsts[] = $a->id;
        }
        echo "\n";
    }
}

echo "Names and countries of affected institutions:\n\n";

foreach (array_unique($blackinsts) as $blackinst) {
    $query3 = $db->exec("select country from institution where inst_id = $blackinst");
    $query4 = $db->exec("select option_value from institution_option where option_name = 'general:instname' and institution_id = $blackinst limit 1");
    echo strtoupper(mysqli_fetch_row($query3)[0]).", ".unserialize(mysqli_fetch_row($query4)[0])['content']."\n";
}