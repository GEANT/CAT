<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

/** This file contains the X509 class.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 */

namespace core\common;

use Exception;

/**
 * This class contains handling functions for X.509 certificates
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class X509 {

    const KNOWN_PUBLIC_KEY_ALGORITHMS = [0 => "rsaEncryption", 1 => "id-ecPublicKey"];

    /**
     * transform PEM formatted certificate to DER format
     *
     *  @param string $pemData blob of data, which is hopefully a PEM certificate
     *  @return string the DER representation of the certificate
     *
     *  @author http://php.net/manual/en/ref.openssl.php (comment from 29-Mar-2007)
     */
    public function pem2der(string $pemData) {
        $begin = "CERTIFICATE-----";
        $end = "-----END";
        $pemDataTemp = substr($pemData, strpos($pemData, $begin) + strlen($begin));
        if ($pemDataTemp === FALSE) { // this is not allowed to happen, we always have clean input here
            throw new Exception("No BEGIN marker found in guaranteed PEM data!");
        }
        $markerPosition = strpos($pemDataTemp, $end);
        if ($markerPosition === FALSE) {
            throw new Exception("No END marker found in guaranteed PEM data!");
        }
        $pemDataTemp2 = substr($pemDataTemp, 0, $markerPosition);
        if ($pemDataTemp2 === FALSE) { // this is not allowed to happen, we always have clean input here
            throw new Exception("Impossible: END marker cutting resulted in an empty string or error?!");
        }
        $der = base64_decode($pemDataTemp2);
        if ($der === FALSE) {
            throw new Exception("Invalid DER data after extracting guaranteed PEM data!");
        }
        return $der;
    }

    /**
     * transform DER formatted certificate to PEM format
     * 
     * @param string $derData blob of DER data
     * @return string the PEM representation of the certificate
     */
    public function der2pem($derData) {
        $pem = chunk_split(base64_encode($derData), 64, "\n");
        $pem = "-----BEGIN CERTIFICATE-----\n" . $pem . "-----END CERTIFICATE-----\n";
        return $pem;
    }

    private function opensslTextParse($myca, &$out) {
        $algoMatch = [];
        $keyLengthMatch = [];
        $output = "";
        // we are also interested in the type and length of the public key,
        // which ..._parse doesn't tell us :-(
        openssl_x509_export($myca, $output, FALSE);
        if (preg_match('/^\s+Public Key Algorithm:\s*(.*)\s*$/m', $output, $algoMatch) && in_array($algoMatch[1], X509::KNOWN_PUBLIC_KEY_ALGORITHMS)) {
            $out['full_details']['public_key_algorithm'] = $algoMatch[1];
        } else {
            $out['full_details']['public_key_algorithm'] = "UNKNOWN";
        }

        if ((preg_match('/^\s+Public-Key:\s*\((.*) bit\)\s*$/m', $output, $keyLengthMatch)) && is_numeric($keyLengthMatch[1])) {
            $out['full_details']['public_key_length'] = $keyLengthMatch[1];
        } else {
            $out['full_details']['public_key_length'] = 0; // if we don't know, assume an unsafe key length -> will trigger warning
        }
    }

    private function typeOfCertificate($myca, &$out) {
        $mydetails = openssl_x509_parse($myca);
        $out['root'] = 0; // default not a root, unless concinved otherwise below
        if ($mydetails['issuer'] === $mydetails['subject']) {
            $out['root'] = 1;
            $mydetails['type'] = 'root';
        }

        // default: not a CA unless convinced otherwise
        $out['ca'] = 0; // we need to resolve this ambiguity
        $out['basicconstraints_set'] = 0;
        // if no basicContraints are set at all, this is a problem in itself
        // is this a CA? or not? Treat as server, but add a warning...
        if (isset($mydetails['extensions']['basicConstraints'])) {
            $out['ca'] = preg_match('/^CA:TRUE/', $mydetails['extensions']['basicConstraints']);
            $out['basicconstraints_set'] = 1;
        }

        if ($out['ca'] > 0 && $out['root'] == 0) {
            $mydetails['type'] = 'interm_ca';
        }
        if ($out['ca'] == 0 && $out['root'] == 0) {
            $mydetails['type'] = 'server';
        }
        return $mydetails;
    }

    /**
     * prepare PEM and DER formats, MD5 and SHA1 fingerprints and subject of the certificate
     *
     * returns an array with the following fields:
     * <pre> uuid
     * pem	certificate in PEM format
     * der	certificate in DER format
     * md5	MD5 fingerprint
     * sha1	SHA1 fingerprint
     * sha256   SHA256 fingerprint
     * name	certificate subject
     * root value 1 if root certificate 0 otherwise
     * ca   value 1 if CA certificate 0 otherwise
     *
     * </pre>
     * @param string $cadata certificate in either PEM or DER format
     * @return array|boolean
     */
    public function processCertificate($cadata) {
        if ($cadata === FALSE) { // we are expecting a string anyway
            return FALSE;
        }
        $pemBegin = strpos($cadata, "-----BEGIN CERTIFICATE-----");
        if ($pemBegin !== FALSE) {
            $pemEnd = strpos($cadata, "-----END CERTIFICATE-----") + 25;
            if ($pemEnd !== FALSE) {
                $cadata = substr($cadata, $pemBegin, $pemEnd - $pemBegin);
                if ($cadata === FALSE) {
                    throw new Exception("Impossible: despite having found BEGIN and END markers, unable to cut out substring!");
                }
            }
            $authorityDer = $this->pem2der($cadata);
            $authorityPem = $this->der2pem($authorityDer);
        } else {
            $authorityDer = $cadata;
            $authorityPem = $this->der2pem($cadata);
        }

        // check that the certificate is OK
        $myca = openssl_x509_read($authorityPem);
        if ($myca == FALSE) {
            return FALSE;
        }
        
        $out = [];
        $mydetails = $this->typeOfCertificate($myca, $out);
        if (!isset($mydetails['subject'])) {
            return FALSE;
        }
        $out["pem"] = $authorityPem;
        $out["der"] = $authorityDer;
        $out["md5"] = openssl_digest($authorityDer, 'MD5');
        $out["sha1"] = openssl_digest($authorityDer, 'SHA1');
        $out["sha256"] = openssl_digest($authorityDer, 'SHA256');
        $out["name"] = $mydetails['name'];
        $mydetails['sha1'] = $out['sha1'];
        $out['full_details'] = $mydetails;
        $this->opensslTextParse($myca, $out);
        return $out;
    }

    /**
     * split a certificate file into components 
     *
     * returns an array containing the PEM format of the certificate (s)
     * if the file contains multiple certificates it gets split into components
     *
     * @param string $cadata certificate in ether PEM or DER format
     * @return array
     */
    public function splitCertificate($cadata) {
        $returnarray = [];
        // maybe we got no real cert data at all? The code is hardened, but will
        // produce ugly WARNING level output in the logfiles, so let's avoid at least
        // the trivial case: if the file is empty, there's no cert in it
        if ($cadata == "") {
            return $returnarray;
        }
        $startPem = strpos($cadata, "-----BEGIN CERTIFICATE-----");
        if ($startPem !== FALSE) {
            $cadata = substr($cadata, $startPem);
            if ($cadata === FALSE) {
                throw new Exception("Impossible: despite having found BEGIN marker, unable to cut out substring!");
            }
            $endPem = strpos($cadata, "-----END CERTIFICATE-----") + 25;
            $nextPem = strpos($cadata, "-----BEGIN CERTIFICATE-----", 30);
            while ($nextPem !== FALSE) {
                $returnarray[] = substr($cadata, 0, $endPem);
                $cadata = substr($cadata, $nextPem);
                $endPem = strpos($cadata, "-----END CERTIFICATE-----") + 25;
                $nextPem = strpos($cadata, "-----BEGIN CERTIFICATE-----", 30);
            }
            $returnarray[] = substr($cadata, 0, $endPem);
        } else {
            // we hand it over to der2pem (no user content coming in from any caller
            // so we know we work with valid cert data in the first place
            $returnarray[] = $this->der2pem($cadata);
        }
        return array_unique($returnarray);
    }

}
