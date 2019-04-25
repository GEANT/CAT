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

/**
 * We also need to do some string magic for GET requests and need to know how
 * far down in the URL the OCSP statement starts.
 * 
 * The following constant tells us the number of slashes before the base64 of
 * the actual request starts
 * 
 * [http://hostname]/whatever/index.php/OCSP_REQ_DATA -> three slashes
 * [http://hostname]/something/else/entirely/ocsp/REQ_DATA -> five slashes
 */
const SLASHES_IN_URL_INCL_LEADING = 2;

$ocspRequestDer = "";

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // the GET URL *is* the request.
        // don't just cut off at last slash; base64 data may have embedded slashes
        // so remove the leading slash first:
        $rawStream = filter_input(INPUT_SERVER, $_SERVER['PHP_SELF'], FILTER_SANITIZE_STRING);
        // and now find and cut at every slash until SLASHES_IN_URL is reached
        for ($iterator = 0; $iterator < SLASHES_IN_URL_INCL_LEADING; $iterator++) {
            $nextSlash = strpos($rawStream, '/');
            if ($nextSlash === FALSE) {
                throw new Exception("We were supposed to find and strip a slash in the base URL, but it doesn't exist!");
            }
            $rawStream = substr($rawStream, $nextSlash + 1);
            if (strlen($rawStream) >= 255) {
                throw new Exception("As per RFC6960, GET is only allowed for requests up to 254 bytes");
            }
        }
        $ocspRequestDer = base64_decode(urldecode($rawStream), TRUE);
        if ($ocspRequestDer === FALSE) {
            throw new Exception("The input data was not cleanly base64-encoded data!");
        }
        break;
    case 'POST':
        if ($_SERVER['CONTENT_TYPE'] != 'application/ocsp-request') {
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
file_put_contents($derFilePath, $ocspRequestDer);
exec("openssl ocsp -reqin $derFilePath -req_text", $output, $retval);

if ($retval !== 0) {
    throw new Exception("openssl ocsp returned a non-zero return code. The DER data is probably bogus. B64 representation of DER data is: " . base64_encode($ocspRequestDer));
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
    throw new Exception("Unable to extract all of issuer hash, key hash, serial number from the request.");
}
/*
 * We respond only if this is about our own CA of course. Once that is checked,
 * get the canned response for the requested serial from filesystem and send it
 * back (if we have it).
 */
if (strcasecmp($nameHash, OUR_NAME_HASH) != 0 || strcasecmp($keyHash, OUR_KEY_HASH) != 0) {
    throw new Exception("The request is about a different Issuer name / public key. Expected vs. actual name hash: " . OUR_NAME_HASH . " / $nameHash, " . OUR_KEY_HASH . " / $keyHash");
}
error_log("base64-encoded request: " . base64_encode($ocspRequestDer));

$response = fopen(__DIR__ . "/statements/" . $serialHex . ".der", "r");
if ($response === FALSE) { // not found
    // first lets load the unauthorised response, which is the default reply
    $unauthResponse = fopen(__DIR__ . "/statements/UNAUTHORIZED.der", "r");
    if ($unauthResponse === FALSE) {
        throw new Exception("Unable to open our canned UNAUTHORIZED response!");
    }
    // this might be a very young certificate, just issued, OCSP statement is 
    // not on the server yet. Apply some amount of grace for a while...
    $graceRaw = file_get_contents("gracelist.serialised");
    if ($graceRaw !== FALSE) {
        $grace = unserialize($graceRaw);
        if (array_key_exists($serialHex, $grace)) {
            // we applied grace earlier. Check if we are still in the window.
            $now = new DateTime();
            $first = $grace[$serialHex]; // this is a DateTime object
            $diff = $now->diff($first);
            if ($diff->y == 0 && $diff->m == 0 && $diff->d == 0 && $diff->h == 0) {
                // this certificate gets a small dose of amazing grace. 
                error_log("Not sending any reply for serial $serialHex because we've applied grace (subsequently).");
                exit(1);
            } else {
                $response = $unauthResponse;
            }
        } else {
            // this certificate gets a small dose of amazing grace. Do not reply
            // but remember when this happened.
            $grace[$serialHex] = new DateTime();
            file_put_contents("gracelist.serialised", serialize($grace));
            error_log("Not sending any reply for serial $serialHex because we've applied grace (first time).");
            exit(1);
        }
    }
    // if we are outside the grace window, send back a negative reply
    $response = $unauthResponse;
    error_log("Serving OCSP response for serial number $serialHex! (we ran out of grace)");
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
