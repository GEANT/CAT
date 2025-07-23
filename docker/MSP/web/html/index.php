<?php
include "./lib.inc";
define("ZIPDIR", '/opt/FR/var/log/forCAT/');
$remove = 0;
$opn = $vlans = '';
$guest_vlan = 0;
error_log(serialize($_POST));
error_log(SERVER_SECRET);
if ( isset($_POST['enc']) ) {
	error_log("enc: ".$_POST['enc']);
	$decrypted = openssl_decrypt(base64_decode($_POST['enc']), "CHACHA20", SERVER_SECRET, 0, SERVER_IV);
	if ($decrypted === false) {
          echo "FAILURE";
          exit;	  
	} else {
          error_log("data=$decrypted");
	  parse_str($decrypted, $darr);
	  error_log(serialize($darr));
	  if (!isset($darr['token']) || $darr['token'] != SERVER_TOKEN) {
		  echo "FAILURE";
		  exit;
          } 
	  error_log("GREAT!");
	} 
}
# when a request contains logid and backlog ";s:11:"DEBUG-11-52";s:7:"backlog";s:1:"7";}
if ( isset($darr['logid']) && isset($darr['backlog']) && isset($darr['iv']) ) {
	if (substr($darr['logid'], 0, 5) == 'DEBUG') {
          $logid = substr($darr['logid'], 6);
        }
	$iv = $darr['iv'];
  	$res = cat_socket(implode(':', array($logid, $darr['backlog'])));
	$cnt = 0;
	error_log('GOT '.$res);
	if (substr($res, 0, strlen(ZIPDIR)) == ZIPDIR) {
            error_log('GOT filename '.$res);
            $za = new ZipArchive();
            $za->open($res);
	    $cnt = $za->numFiles;
	    $content = file_get_contents($res);
            $encrypted = openssl_encrypt(SERVER_TOKEN . $content, "CHACHA20", SERVER_SECRET, 0, $iv);
	    header('Content-Type: application/octet-stream');
            header("Content-Disposition: attachment; filename=\"detail_".$logid.".enc\"");
            header("Content-Transfer-Encoding: binary");
	    echo "ZIPDATA:$encrypted";
	    error_log('Sent data in response');
	}
        error_log('with '. $cnt . ' files');
	if (file_exists($res)) {
	  unlink($res);
	}
	exit;
}
# MUST provide: deployment_id, inst_id
#               and port, secret, pskkey, country or torevoke
# MAY provide: operatorname, vlanno, vlanrealms[], guest_vlan, remove
if (
    isset($darr['instid']) && isset($darr['deploymentid']) &&
    (isset($darr['port']) && isset($darr['secret']) && isset($darr['pskkey']) && isset($darr['country']) ||
     isset($darr['torevoke']))) {
  if (isset($darr['remove'])) {
    $remove = 1;
  } else {
    if (isset($darr['operatorname'])) {
      $opn = trim($darr['operatorname']);
    }
    if (isset($darr['vlan']) && isset($darr['realmforvlan']) &&
        is_array($darr['realmforvlan'])) {
      $vlans = $darr['vlan'] . '#' . implode('#', $darr['realmforvlan']);
    }
    if (isset($darr['guest_vlan'])) {
      $guest_vlan = $darr['guest_vlan'];
    }
  }
  if (isset($darr['torevoke'])) {
	  $el = explode('#', $darr['torevoke']);
	  $res = cat_socket(implode(':', array($darr['instid'], $darr['deploymentid'], $el[0], $el[1])));
  } else {
  	# arguments 5-7 are Base64 encoded
  	$res = cat_socket(implode(':', array($darr['country'],
                                 $darr['instid'], $darr['deploymentid'],
                                 $darr['port'],
                                 base64_encode($darr['secret']),
                                 base64_encode($opn), 
                                 base64_encode($vlans), base64_encode($darr['pskkey']), $guest_vlan, $remove)));
  }
  echo $res;
} else {
  echo "FAILURE";
}
