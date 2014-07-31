<?php
/* *********************************************************************************
 * (c) 2011-13 DANTE Ltd. on behalf of the GN3 and GN3plus consortia
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php
/** This file contains the X509 class.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 */

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

/** transform PEM formed certificate to DER format
 *
 *  @param mixed $pem_data blob of data, which is hopefully a PEM certificate
 *  @return the DER representation of the certificate
 *
 *  @author http://php.net/manual/en/ref.openssl.php (comment from 29-Mar-2007)
 */
public function pem2der($pem_data) {
   $begin = "CERTIFICATE-----";
   $end   = "-----END";
   $pem_data = substr($pem_data, strpos($pem_data, $begin)+strlen($begin));
   $pem_data = substr($pem_data, 0, strpos($pem_data, $end));
   $der = base64_decode($pem_data);
   return $der;
}

public function der2pem($der_data) {
   $pem = chunk_split(base64_encode($der_data), 64, "\n");
   $pem = "-----BEGIN CERTIFICATE-----\n".$pem."-----END CERTIFICATE-----\n";
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
  * @param blob $cadata certificate in ether PEM or DER format
  * @return array
  */
public function processCertificate ($cadata) {
    $begin_pem = strpos($cadata,"-----BEGIN CERTIFICATE-----");
   if($begin_pem !== FALSE) {
        $end_c = strpos($cadata,"-----END CERTIFICATE-----") + 25;
        if($end_c !== FALSE) {
           $cadata = substr($cadata,$begin_pem,$end_c - $begin_pem);
        }
        $ca_pem = $cadata;
        $ca_der = X509::pem2der($ca_pem);
        // echo "XXXXXXXXXXXXX".$cadata."XXXXXXXXXXXXX"; exit;
    } else {
        $ca_der = $cadata;
        $ca_pem =  X509::der2pem($cadata);
    }

    # check that the certificate is OK
//print "<pre>CA:\n$ca_pem</pre>\n";
    $myca = openssl_x509_read($ca_pem);
    if ($myca == FALSE) 
        return FALSE;
    $mydetails = openssl_x509_parse($myca);
    if (!isset($mydetails['subject']))
        return FALSE;
    $md5 = openssl_digest($ca_der,'MD5');
    $sha1 = openssl_digest($ca_der,'SHA1');
    $out = array ("uuid" => uuid(), "pem" => $ca_pem, "der" => $ca_der, "md5"=>$md5, "sha1"=>$sha1, "name"=>$mydetails['name']);
    $diff_a = array_diff($mydetails['issuer'], $mydetails['subject']);
    if(count($diff_a) == 0 ) {
     $out['root'] = 1;
     $mydetails['type'] = 'root';
    } else {
     $out['root'] = 0;
    }
    // if no basicContraints are set at all, this is a problem in itself
    // is this a CA? or not? Treat as server, but add a warning...
    if (isset($mydetails['extensions']['basicConstraints'])) {
       $out['ca'] = preg_match('/^CA:TRUE/',$mydetails['extensions']['basicConstraints']);
       $out['basicconstraints_set'] = 1;
    } else {
       $out['ca'] = 0; // we need to resolve this ambiguity
       $out['basicconstraints_set'] = 0;
    }
    
    if( $out['ca'] > 0 && $out['root'] == 0 )
     $mydetails['type'] = 'interm_ca';
    if( $out['ca'] == 0 && $out['root'] == 0 )
     $mydetails['type'] = 'server';
    $out['full_details'] = $mydetails;
    
    // we are also interested in the signature algorithm and length of public key,
    // whith ..._parse doesn't tell us :-(
    
    
    openssl_x509_export($myca, $output, FALSE);
    if(preg_match('/^\s+Signature Algorithm:\s*(.*)\s*$/m', $output, $match)) 
      $out['full_details']['signature_algorithm'] = $match[1];
    else
      $out['full_details']['signature_algorithm'] = $output;
 
    if((preg_match('/^\s+Public-Key:\s*\((.*) bit\)\s*$/m', $output, $match)) && is_numeric($match[1])) 
      $out['full_details']['public_key_length'] = $match[1];
    else
      $out['full_details']['public_key_length'] = $output;
    
    return $out;
}

/**
  * split a certificate file into components 
  *
  * returns an array containing the PEM format of the certificate (s)
  * if the file contains multiple certificates it gets split into components
  *
  * @param blob $cadata certificate in ether PEM or DER format
  * @return array
  */

public function splitCertificate($cadata) {
  $returnarray = array();
  // maybe we got no real cert data at all? The code is hardened, but will
  // produce ugly WARNING level output in the logfiles, so let's avoid at least
  // the trivial case: if the file is empty, there's no cert in it
  if ($cadata == "")
      return $returnarray;
  $start_c = strpos($cadata,"-----BEGIN CERTIFICATE-----" );
  if( $start_c !== FALSE) {
        $cadata = substr($cadata,$start_c);
        $end_c = strpos($cadata,"-----END CERTIFICATE-----") + 25;
        $next_c = strpos($cadata,"-----BEGIN CERTIFICATE-----",30);
        while ( $next_c !== FALSE) {
          $returnarray[] = substr($cadata,0,$end_c);
          $cadata = substr($cadata,$next_c);
          $end_c = strpos($cadata,"-----END CERTIFICATE-----") + 25;
          $next_c = strpos($cadata,"-----BEGIN CERTIFICATE-----",30);
        }
        $returnarray[] = substr($cadata,0,$end_c);
    } else {
        // TODO: before we blindly hand it over to der2pem - is this valid DER
        // data at all?
      $returnarray[] = X509::der2pem($cadata);
    }
    // print_r($returnarray);
    
    return $returnarray;
}

}

?>
