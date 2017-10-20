<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */

/** This file contains the X509 class.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 */
namespace core\common;
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
        $pemDataTemp2 = substr($pemDataTemp, 0, strpos($pemDataTemp, $end));
        $der = base64_decode($pemDataTemp2);
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

    /**
     * prepare PEM and DER formats, MD5 and SHA1 fingerprints and subject of the certificate
     *
     * returns an array with the following fields:
     * <pre> uuid
     * pem	certificate in PEM format
     * der	certificate in DER format
     * md5	MD5 fingerprint
     * sha1	SHA1 fingerprint
     * name	certificate subject
     * root value 1 if root certificate 0 otherwise
     * ca   value 1 if CA certificate 0 otherwise
     *
     * </pre>
     * @param string $cadata certificate in ether PEM or DER format
     * @return array
     */
    public function processCertificate($cadata) {
        $pemBegin = strpos($cadata, "-----BEGIN CERTIFICATE-----");
        if ($pemBegin !== FALSE) {
            $pemEnd = strpos($cadata, "-----END CERTIFICATE-----") + 25;
            if ($pemEnd !== FALSE) {
                $cadata = substr($cadata, $pemBegin, $pemEnd - $pemBegin);
            }
            $authorityDer = X509::pem2der($cadata);
            $authorityPem = X509::der2pem($authorityDer);
        } else {
            $authorityDer = $cadata;
            $authorityPem = X509::der2pem($cadata);
        }
        
        // check that the certificate is OK
        $myca = openssl_x509_read($authorityPem);
        if ($myca == FALSE) {
            return FALSE;
        }
        $mydetails = openssl_x509_parse($myca);
        if (!isset($mydetails['subject'])) {
            return FALSE;
        }
        $md5 = openssl_digest($authorityDer, 'MD5');
        $sha1 = openssl_digest($authorityDer, 'SHA1');
        $out = ["pem" => $authorityPem, "der" => $authorityDer, "md5" => $md5, "sha1" => $sha1, "name" => $mydetails['name']];
        
        $out['root'] = 0; // default, unless concinved otherwise below
        if ($mydetails['issuer'] === $mydetails['subject']) {
            $out['root'] = 1;
            $mydetails['type'] = 'root';
        }
        // again default: not a CA unless convinced otherwise
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
        $mydetails['sha1'] = $sha1;
        // the signature algorithm is available in PHP7 with the property "signatureTypeSN", example "RSA-SHA512"
        $out['full_details'] = $mydetails;

        $match = [];
        
        // we are also interested in the length of public key,
        // whith ..._parse doesn't tell us :-(
        openssl_x509_export($myca, $output, FALSE);
        if ((preg_match('/^\s+Public-Key:\s*\((.*) bit\)\s*$/m', $output, $match)) && is_numeric($match[1])) {
            $out['full_details']['public_key_length'] = $match[1];
        } else {
            $out['full_details']['public_key_length'] = $output;
        }
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
