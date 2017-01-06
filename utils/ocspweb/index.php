<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

/**
 * this file is meant to be deployed on the web server that serves OCSP 
 * statements
 * (a cron-style script fetches the pre-computed OCSP statements from the CAT
 * database and stores them in the subdir /statements/)
 * 
 * The job of the PHP script here is to receive OCSP requests via HTTP (both GET
 * and POST are to be supported), decode them, verify that they are pertinent to
 * the CA (compare issuer hash), extract the serial number, and return the OCSP
 * statement for that serial number by fetching it from /statements/
 */

/**
 * The following constants define for which issuer and key hash we respond. You
 * can find out those values by executing:
 * 
 * openssl ocsp -issuer cacert.pem -serial 1234 -req_text
 * 
 * (where serial is arbitrary, and cacert.pem is the CA file of the issuing CA)
 */

const OUR_NAME_HASH = "EBB151A467CD64D0E6F8F5E8D8CE9F6FADA54332";
const OUR_KEY_HASH = "156B722D8BFD915157148BBE30E46C2C8B9810CC";

$ocspRequestDer = "";

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // the GET URL *is* the request.
        $ocspRequestDer = base64_decode(urldecode(substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'], '/') + 1)));
        break;
    case 'POST':
        if ($_SERVER['CONTENT-TYPE'] != 'application/ocsp-request') {
            throw new Exception("For request method POST, the Content-Type must be application/ocsp-request.");
        }
        $ocspRequestDer = file_get_contents("php://input");
        break;
    default:
        throw new Exception("Request method is not suitable for OCSP, see RFC6960 Appendix A.");
}

/* here it is. Now we need to get issuer hash, key hash and requested serial out of it.
 * PHP's openssl extension does not seem to help with that. Good old cmdline to
 * the rescue.
 */
$output = [];
$retval = 999;
$derFilePath = tempnam(realpath(sys_get_temp_dir()), "ocsp_");
$derFile = fopen($derFilePath);
fwrite($derFile, $ocspRequestDer);
exec("openssl ocsp -reqin $derFilePath -req_text", $output, $retval);
fclose($derFile);
if ($retval !== 0) {
    throw new Exception("openssl ocsp returned a non-zero return code. The DER data is probably bogus.");
}

$nameHash = FALSE;
$keyHash = FALSE;
$serialHex = FALSE;
foreach ($output as $oneLine) {
    $matchBuffer = [];
    if (preg_match('/Issuer Name Hash: (.*)$/', $oneLine, $matchBuffer)) {
        $nameHash = $matchBuffer[1];
    }
    if (preg_match('/Issuer Key Hash: (.*)$/', $oneLine, $matchBuffer)) {
        $keyHash = $matchBuffer[1];
    }
    if (preg_match('/Serial Number: (.*)$/', $oneLine, $matchBuffer)) {
        $serialHex = $matchBuffer[1];
    }
}
if (!$nameHash || !$keyHash || !$serialHex) {
    throw new Exception("Unable to extract all of issuer hash, key hash, serial number from the request.");
}
/*
 * We respond only if this is about our own CA of course. Once that is checked,
 * get the canned response for the requested serial from filesystem and send it
 * back (if we have it).
 */
if ($nameHash != OUR_NAME_HASH || $keyHash != OUR_KEY_HASH) {
    throw new Exception("The request is about a different Issuer name / public key.");
}
$response = fopen(__DIR__."/statements/".$serialHex.".der");
if (!$response) {
    throw new Exception("Unable to find the OCSP statement for that serial number.");
}
/*
 * Finally! Send stuff back.
 */
$responseContent = fread($response, 1000000);
fclose($response);
header('Content-Type: application/ocsp-response');
header('Content-Length: '.strlen($responseContent));
echo $responseContent;