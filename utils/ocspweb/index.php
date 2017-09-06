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
 * statement for that serial number by fetching it from statements/
 * this script works only if it is exactly one subdir down from hostname base
 * i.e. http://hostname/whatever/index.php
 */
/**
 * The following constants define for which issuer and key hash we respond. You
 * can find out those values by executing:
 * 
 * openssl ocsp -issuer cacert.pem -serial 1234 -req_text
 * 
 * (where serial is arbitrary, and cacert.pem is the CA file of the issuing CA)
 */
error_reporting(E_ALL);

const OUR_NAME_HASH = "DCEB2C72264239201A4A5DF547C78268A1CB33A2";
const OUR_KEY_HASH = "BC8DDD42F7B3B458E8ECEE403D21D404CEB9F2D0";

function instantDeath($message) {
    error_log($message);
    throw new Exception($message);
}

$ocspRequestDer = "";

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // the GET URL *is* the request.
        // don't just cut off at last slash; base64 data may have embedded slashes
        $rawStream = substr(filter_input(INPUT_SERVER, 'PHP_SELF', FILTER_SANITIZE_STRING), strpos($_SERVER['PHP_SELF'], '/', 1) + 1);
        $ocspRequestDer = base64_decode(urldecode($rawStream), TRUE);
        if ($ocspRequestDer === FALSE) {
            instantDeath("The input data was not cleanly base64-encoded data!");
        }
        break;
    case 'POST':
        if ($_SERVER['CONTENT_TYPE'] != 'application/ocsp-request') {
            instantDeath("For request method POST, the Content-Type must be application/ocsp-request.");
        }
        $ocspRequestDer = file_get_contents("php://input");
        break;
    default:
        instantDeath("Request method is not suitable for OCSP, see RFC6960 Appendix A.");
}

/* here it is. Now we need to get issuer hash, key hash and requested serial out of it.
 * PHP's openssl extension does not seem to help with that. Good old cmdline to
 * the rescue.
 */
$output = [];
$retval = 999;
$derFilePath = tempnam(realpath(sys_get_temp_dir()), "ocsp_");
$derFile = fopen($derFilePath, "w");
fwrite($derFile, $ocspRequestDer);
exec("openssl ocsp -reqin $derFilePath -req_text", $output, $retval);
fclose($derFile);
if ($retval !== 0) {
    instantDeath("openssl ocsp returned a non-zero return code. The DER data is probably bogus. B64 representation of DER data is: " . base64_encode($ocspRequestDer));
}
if ($output === NULL) { // this can't really happen, but makes Scrutinizer happier
    $output = [];
}

$nameHash = "";
$keyHash = "";
$serialHex = "";
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
if (strlen($serialHex) == 0 || strlen($keyHash) == 0 || strlen($serialHex) == 0) {
    instantDeath("Unable to extract all of issuer hash, key hash, serial number from the request.");
}
/*
 * We respond only if this is about our own CA of course. Once that is checked,
 * get the canned response for the requested serial from filesystem and send it
 * back (if we have it).
 */
if ($nameHash != OUR_NAME_HASH || $keyHash != OUR_KEY_HASH) {
    instantDeath("The request is about a different Issuer name / public key. Expected vs. actual name hash: " . OUR_NAME_HASH . " / $nameHash, " . OUR_KEY_HASH . " / $keyHash");
}
error_log("base64-encoded request: " . base64_encode($ocspRequestDer));
$response = fopen(__DIR__ . "/statements/" . $serialHex . ".der", "r");
if (!$response) {

    $response = fopen(__DIR__ . "/statements/UNAUTHORIZED.der", "r");
    error_log("Serving OCSP UNAUTHORIZED response (no statement for serial number found)!");
    if (!$response) {
        instantDeath("Unable to open our canned UNAUTHORIZED response!");
    }
} else {
    error_log("Serving OCSP response for serial number $serialHex!");
}
/*
 * Finally! Send stuff back.
 */

$responseContent = fread($response, 1000000);
fclose($response);
error_log("base64-encoded response: " . base64_encode($responseContent));
header('Content-Type: application/ocsp-response');
header('Content-Length: ' . strlen($responseContent));
echo $responseContent;
