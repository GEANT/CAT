<?php
include "./lib.inc";
define("ZIPDIR", '/opt/FR/var/log/forCAT/');
$remove = 0;
$opn = $vlans = '';
$guest_vlan = 0;
error_log(serialize($_REQUEST));
# when a request contains logid and backlog ";s:11:"DEBUG-11-52";s:7:"backlog";s:1:"7";}
if ( isset($_REQUEST['logid']) && isset($_REQUEST['backlog']) ) {
	if (substr($_REQUEST['logid'], 0, 5) == 'DEBUG') {
          $logid = substr($_REQUEST['logid'], 6);
        }
  	$res = cat_socket(implode(':', array($logid, $_REQUEST['backlog'])));
	error_log('GOT '.$res);
	if (substr($res, 0, strlen(ZIPDIR)) == ZIPDIR) {
            error_log('GOT filename '.$res);
            $za = new ZipArchive();
            $za->open($res);
	    header('Content-Type: application/zip');
            header("Content-Disposition: attachment; filename=\"detail_".$logid.".zip\"");
            header("Content-Transfer-Encoding: binary");
	    echo 'ZIPDATA:'.file_get_contents($res);
	    error_log('Sent data in response');
	}
        error_log('with '.$za->numFiles . ' files');
	exit;
}
# MUST provide: deployment_id, inst_id
#               and port, secret, pskkey, country or torevoke
# MAY provide: operatorname, vlanno, vlanrealms[], guest_vlan, remove
if (
    isset($_REQUEST['instid']) && isset($_REQUEST['deploymentid']) &&
    (isset($_REQUEST['port']) && isset($_REQUEST['secret']) && isset($_REQUEST['pskkey']) && isset($_REQUEST['country']) ||
     isset($_REQUEST['torevoke']))) {
  if (isset($_REQUEST['remove'])) {
    $remove = 1;
  } else {
    if (isset($_REQUEST['operatorname'])) {
      $opn = trim($_REQUEST['operatorname']);
    }
    if (isset($_REQUEST['vlan']) && isset($_REQUEST['realmforvlan']) &&
        is_array($_REQUEST['realmforvlan'])) {
      $vlans = $_REQUEST['vlan'] . '#' . implode('#', $_REQUEST['realmforvlan']);
    }
    if (isset($_REQUEST['guest_vlan'])) {
      $guest_vlan = $_REQUEST['guest_vlan'];
    }
  }
  if (isset($_REQUEST['torevoke'])) {
	  $el = explode('#', $_REQUEST['torevoke']);
	  $res = cat_socket(implode(':', array($_REQUEST['instid'], $_REQUEST['deploymentid'], $el[0], $el[1])));
  } else {
  	# arguments 5-7 are Base64 encoded
  	$res = cat_socket(implode(':', array($_REQUEST['country'],
                                 $_REQUEST['instid'], $_REQUEST['deploymentid'],
                                 $_REQUEST['port'],
                                 base64_encode($_REQUEST['secret']),
                                 base64_encode($opn), 
                                 base64_encode($vlans), base64_encode($_REQUEST['pskkey']), $guest_vlan, $remove)));
  }
  echo $res;
} else {
  echo "FAILURE";
}
