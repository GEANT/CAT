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
 * This file contains code for testing presenting tests result
 *
 * @author Maja Gorecka-Wolniewicz <mgw@umk.pl>
 *
 * @package Developer
 * 
 */

namespace core\diag;

use \Exception;

class RADIUSTestsUI extends AbstractTest
{

    /**
     * This private variable contains the realm to be checked. Is filled in the
     * class constructor.
     * 
     * @var string
     */
    public $realm = NULL;
    public $outerUser = NULL;

    /**
     * result of the reachability tests
     * 
     * @var array
     */
    public $allReachabilityResults = [];
    
    private $hostMap = [];
    private $globalLevelStatic = \core\common\Entity::L_OK;
    private $globalLevelDynamic = \core\common\Entity::L_OK;
    private $rfc7585suite = NULL;
    private $srv;
    private $naptr;
    private $naptrValid;
    private $hosts;
    private $testSuite;
    private $areFailed = FALSE;
    private $globalInfo = [];
    private $stateIcons = [];
    private $states;
    private $certFields;
    private $timestamp;
    const RADIUS_TEST_OPERATION_MODE_SHALLOW = 1;
    const RADIUS_TEST_OPERATION_MODE_THOROUGH = 2;
    
    

    /**
     * Constructor for the RADIUSTestsUI class. The single mandatory parameter is the
     * token indicating tests that were carried out and saved as JSON files.
     * 
     * @param string $token                  the token which points to a directory
     * @throws Exception
     */
    public function __construct($token)
    {
        parent::__construct();
        $this->globalInfo = [
            \core\common\Entity::L_OK => _("All tests passed."),
            \core\common\Entity::L_WARN => _("There were some warnings."),
            \core\common\Entity::L_ERROR => _("There were some errors."),
            \core\common\Entity::L_REMARK => _("There were some remarks.")
        ]; 
        $this->stateIcons = [
            \core\common\Entity::L_OK => '../resources/images/icons/Quetto/check-icon.png',
            \core\common\Entity::L_WARN => '../resources/images/icons/Quetto/danger-icon.png',
            \core\common\Entity::L_ERROR => '../resources/images/icons/Quetto/no-icon.png',
            \core\common\Entity::L_REMARK => '../resources/images/icons/Quetto/info-icon.png'
        ];
        $this->states = [
            'PASS' => _("PASS"),
            'FAIL' => _("FAIL")
        ];
        $this->certFields = [
            'subject' => _("Subject:"),
            'issuer' => _("Issuer:"),
            'validFrom' =>  _("Valid from:"),
            'validTo' => _("Valid to:"),
            'serialNumber' => _("Serial number:"),
            'sha1' => _("SHA1 fingerprint:"),
            'title' => _("Server certificate"),
            'c_subject' => _("Subject"),
            'c_issuer' => _("Issuer"),
            'policies' => _("Policies"),
            'crldistributionpoints' =>  _("crlDistributionPoint"),
            'authorityinfoaccess' => _("authorityInfoAccess"),
            'subjectaltname' => _("SubjectAltName"),
        ];
        $jsondir = dirname(dirname(dirname(__FILE__)))."/var/json_cache";
        if ($token && is_dir($jsondir.'/'.$token)) {
            foreach (['realm', 'udp', 'clients', 'capath'] as $test_type) {
                foreach (glob("$jsondir/$token/$test_type*") as $filename) {
                    $this->loggerInstance->debug(4, "\nIS_DIR $filename\n");
                    if (!array_key_exists($test_type, $this->allReachabilityResults)) {
                        $this->allReachabilityResults[$test_type] = array();
                    }
                    $this->allReachabilityResults[$test_type][] = json_decode(file_get_contents($filename));
                }   
            }
            if ($this->allReachabilityResults['realm'][0]->realm) {
                $this->realm = $this->allReachabilityResults['realm'][0]->realm;
                $this->outerUser = $this->allReachabilityResults['realm'][0]->outeruser;
                foreach ($this->allReachabilityResults['realm'][0]->totest as $totest) {
                    $this->hostMap[$totest->host] = $totest->bracketaddr;
                }
                $this->rfc7585suite = unserialize($this->allReachabilityResults['realm'][0]->rfc7585suite);
                $this->srv = $this->allReachabilityResults['realm'][0]->srv;
                $this->naptr = $this->allReachabilityResults['realm'][0]->naptr;
                $this->naptrValid = $this->allReachabilityResults['realm'][0]->naptr_valid;
                $this->hosts = $this->allReachabilityResults['realm'][0]->hosts;
                $this->testSuite = unserialize($this->allReachabilityResults['realm'][0]->testsuite);
            }
            $this->timestamp = $this->allReachabilityResults['realm'][0]->datetime;
        }
    }
    
    public function getTimeStamp()
    { 
        return $this->timestamp;
    }
    /**
     * sets the global status for static tests
     */
    public function setGlobalStaticResult()
    { 
        foreach ($this->allReachabilityResults['udp'] as $udp) {
            $this->globalLevelStatic = max($this->globalLevelStatic, $udp->result[0]->level);
        }
    }
    
    public function setGlobalDynamicResult()
    {
        foreach ($this->allReachabilityResults['capath'] as $capath) {
            $this->globalLevelDynamic = max($this->globalLevelDynamic, $capath->level);
        }
        foreach ($this->allReachabilityResults['clients'] as $clients) {
            $srefused = FALSE;
            $level = \core\common\Entity::L_OK;
            foreach ($clients->ca as $ca) {
                foreach ($ca->certificate as $certificate) {
                    if ($certificate->returncode == \core\diag\RADIUSTests::RETVAL_CONNECTION_REFUSED) {
                        $srefused = $this->areFailed = TRUE;
                    }
                }
                if (!$srefused) {
                    foreach ($clients->ca as $cca) {
                        foreach ($cca->certificate as $certificate) {
                            $level = $certificate->returncode;
                            if ($level < 0) {
                                $level = \core\common\Entity::L_ERROR;
                                $this->areFailed = TRUE;
                            }
                            if ($certificate->expected != 'PASS') {
                                if ($certificate->connected == 1) {
                                    $level = \core\common\Entity::L_WARN;
                                } else {
                                    $level = \core\common\Entity::L_OK;
                                }
                            }
                        }
                    }   
                } 
            }
            $this->globalLevelDynamic = max($this->globalLevelDynamic, $level);
        }
    }           

    public function isDynamic()
    {
        if ($this->naptr > 0) {
            return TRUE;
        }
        return FALSE;
    }
    /**
     * prints tabs-1
     * 
     * 
     */
    public function printOverview()
    {
        $out = [];
        $out[] = "<fieldset class='option_container'>
        <legend>
        <strong>"._("Overview").'</strong> 
        </legend>';
        $out[] = "<strong>"._("DNS chekcs")."</strong><div>";
        if ($this->naptr != \core\diag\RADIUSTests::RETVAL_NOTCONFIGURED) {
            $out[] = "<table>";
            $out[] = "<tr><td>"._("Checking NAPTR existence:")."</td><td>";
            switch ($this->naptr) {
                case \core\diag\RFC7585Tests::RETVAL_NONAPTR:
                    $out[] = _("This realm has no NAPTR records.");
                    break;
                case \core\diag\RFC7585Tests::RETVAL_ONLYUNRELATEDNAPTR:
                    $out[] = _("This realm has NAPTR records, but none are related to this roaming consortium.");
                    break;
                default: // if none of the possible negative retvals, then we have matching NAPTRs
                    $out[] = sprintf(_("This realm has %d NAPTR records relating to this roaming consortium."), $this->naptr);
            }
            $out[] = "</td></tr>";
          
            if ($this->naptr > 0) {
                $out[] = "<tr><td>"._("Checking NAPTR compliance (flag = S and regex = {empty}):")."</td><td>";
                switch ($this->naptrValid) {
                    case \core\diag\RADIUSTests::RETVAL_OK:
                        $out[] = "No issues found.";
                        break;
                    case \core\diag\RADIUSTests::RETVAL_INVALID:
                        $out[] = _("At least one NAPTR with invalid content found!");
                        break;
                }
                $out[] = "</td></tr>";
            }
            // SRV resolution
            if ($this->naptr > 0 && $this->naptrValid == \core\diag\RADIUSTests::RETVAL_OK) {
                $out[] = "<tr><td>"._("Checking SRVs:")."</td><td>";
                switch ($this->srv) {
                    case \core\diag\RADIUSTests::RETVAL_SKIPPED:
                        $out[] = _("This check was skipped.");
                        break;
                    case \core\diag\RADIUSTests::RETVAL_INVALID:
                        $out[] = _("At least one NAPTR with invalid content found!");
                        break;
                    default: // print number of successfully retrieved SRV targets
                        $out[] = sprintf(_("%d host names discovered."), $this->srv);
                }
                $out[] = "</td></tr>";
            }
            // IP addresses for the hosts
            if ($this->naptr > 0 && $this->naptrValid == \core\diag\RADIUSTests::RETVAL_OK && $this->srv > 0) {
                $out[] = "<tr><td>"._("Checking IP address resolution:")."</td><td>";
                switch ($this->srv) {
                    case \core\diag\RADIUSTests::RETVAL_SKIPPED:
                        $out[] = _("This check was skipped.");
                        break;
                    case \core\diag\RADIUSTests::RETVAL_INVALID:
                        $out[] = _("At least one hostname could not be resolved!");
                        break;
                    default: // print number of successfully retrieved SRV targets
                        $out[] = sprintf(_("%d IP addresses resolved."), $this->hosts);
                }
                $out[] = "</td></tr>";
            }

            $out[] = "</table><br/>";
            $out[] = sprintf(_("Realm is <strong>%s</strong> "), _(($this->naptr > 0 ? "DYNAMIC" : "STATIC")));
            if (count($this->testSuite->listerrors()) == 0) {
                $out[] = _("with no DNS errors encountered. Congratulations!");
            } else {
                $out[] = _("but there were DNS errors! Check them!")." "._("You should re-run the tests after fixing the errors; more errors might be uncovered at that point. The exact error causes are listed below.");
                $out[] = "<div class='notacceptable'><table>";
                foreach ($this->testSuite->listerrors() as $details) {
                    $out[] = "<tr><td>".$details['TYPE']."</td><td>".$details['TARGET']."</td></tr>";
                }
                $out[] = "</table></div>";
            }
            $out[] = '</div>';
        } else {
            $out[] = "<tr><td>"._("Dynamic discovery test is not configured")."</td><td>";
        }
        $out[] = "<hr><strong>"._("Static connectivity tests")."</strong>
         <table><tr>
         <td class='icon_td'>";
        $out[] = "<img src='".$this->stateIcons[$this->globalLevelStatic]."' id='main_static_ico' class='icon'></td><td id='main_static_result'>".
                            $this->globalInfo[$this->globalLevelStatic].' '. _("See the appropriate tab for details.").'</td>
         </tr></table>';
        if ($this->naptr > 0) {
            $out[] = "<hr><strong>"._("Dynamic connectivity tests")."</strong>
            <table><tr>
            <td class='icon_td'><img src='".$this->stateIcons[$this->globalLevelDynamic]."' id='main_dynamic_ico' class='icon'></td><td id='main_dynamic_result'>".
            $this->globalInfo[$this->globalLevelDynamic].' '._("See the appropriate tab for details.").'</td></tr></table>';
        }
        $out[] = '</fieldset>';
        return join('', $out);
    }
    
    public function printStatic()
    {
        $out = [];
        $out[] = '<fieldset class="option_container" id="static_tests">
                  <legend><strong>';
        $out[] = _("STATIC connectivity tests");
        $out[] = '</strong> </legend>';
        $out[] = _("This check sends a request for the realm through various entry points of the roaming consortium infrastructure. The request will contain the 'Operator-Name' attribute, and will be larger than 1500 Bytes to catch two common configuration problems.<br/>Since we don't have actual credentials for the realm, we can't authenticate successfully - so the expected outcome is to get an Access-Reject after having gone through an EAP conversation.");
        $out[] = '<p>';
        foreach ($this->allReachabilityResults['udp'] as $udp) {
            $hostindex = $udp->hostindex;
            $result = $udp->result[0];
            $out[] = '<hr>';
            $out[] = '<strong>'.sprintf(_("Testing from: %s"), \config\Diagnostics::RADIUSTESTS['UDP-hosts'][$hostindex]['display_name']).'</strong>';
            $out[] = "<table id='results$hostindex'  style='width:100%' class='udp_results'>
<tr>
<td class='icon_td'><img src='".$this->stateIcons[$result->level]."' id='src".$hostindex."_img'></td>
<td id='src$hostindex' colspan=2>
";
            $out[] = '<strong>'.($result->server ? $result->server : _("Connected to undetermined server")).'</strong><br/>'.sprintf (_("elapsed time: %sms."), $result->time_millisec).'<p>'.$result->message.'</p>';
                    
            if ($result->level > \core\common\Entity::L_OK && property_exists($result, 'cert_oddities')) {
                foreach ($result->cert_oddities as $oddities) {
                    $out[] = '<tr class="results_tr"><td>&nbsp;</td><td class="icon_td"><img src="'.$this->stateIcons[$oddities->level].'"></td><td>'.$oddities->message.'</td></tr>';
                }
            }
            $cert_data = '';
            foreach ($result->server_cert as $sckey => $sc) {
                if (array_key_exists($sckey, $this->certFields)) {
                    $cert_data .= '<dt>'.$this->certFields[$sckey].'</dt><dd>'.$sc.'</dd>';
                }
            }
            $out[] = "<tr class='server_cert' style='display: ";
            $out[] = ($result->server_cert ? 'table-row' : 'none').";'><td>&nbsp;</td><td colspan=2><div><dl class='server_cert_list' style='display: none;'>";
            $out[] = $cert_data;
                        
            $ext = '';
            foreach ($result->server_cert->extensions as $extkey => $extval) {
                if ($ext) {
                    $ext .= '<br>';
                }
                $ext .= '<strong>'.$extkey.': </strong>'.'<i>'.$extval.'</i>';   
            }
            if ($ext != '') {
                $out[] = '<dt>'._('Extensions').'</dt></dd><dd>'.$ext.'</dd>';
            }
            $out[] = "</dl><a href='' class='morelink'>"._("show server certificate details")."&raquo;</a></div></tr>";
                        
            $out[] = "</td></tr></table>";
        }
        $out[] = '</fieldset>';
        return join('', $out);            
    }
    
    private function collectCAPath()
    {
        $capathtest = [];
        $capathtest[] = '<p><strong>'._("Checking server handshake...")."</strong><p>";
        foreach ($this->allReachabilityResults['capath'] as $capath) {
            $hostindex = $capath->hostindex;
            $level = $capath->level;
            if ($capath->level == \core\common\Entity::L_OK && $capath->result == \core\diag\RADIUSTests::RETVAL_INVALID) {
                $level = \core\common\Entity::L_WARN;
            }
            $capathtest[] = '<p><strong>'.$this->hostMap[$capath->IP].'</strong>';
            $capathtest[] = '<ul style="list-style-type: none;" class="caresult"><li>';
            $capathtest[] = "<table id='caresults$hostindex'  style='width:100%'>
<tr>
<td class='icon_td'><img src='";
            $capathtest[] = $this->stateIcons[$level]."' id='srcca".$hostindex."_img'></td>
<td id='srcca$hostindex'>";
            $more = '';
            if ($capath->certdata && $capath->certdata->subject != '') {
                $more .= '<div class="more">';
                $certdesc = '<br>'.$this->certFields['title'].'<ul>';
                if ($capath->certdata->subject) {
                    $certdesc .= '<li>'.$this->certFields['c_subject'].': '.$capath->certdata->subject;
                }
                if ($capath->certdata->issuer) {
                    $certdesc .= '<li>'.$this->certFields['c_issuer'].': '.$capath->certdata->issuer;
                }
                if ($capath->certdata->extensions) {
                    if ($capath->certdata->extensions->subjectaltname) {
                        $certdesc .= '<li>'.$this->certFields['subjectaltname'].': '.$capath->certdata->extensions->subjectaltname;
                    }
                }
                if ($capath->certdata->extensions->policies) {
                    $certdesc .= '<li>'.$this->certFields['policies'].': '.$capath->certdata->extensions->policies;
                }
                if ($capath->certdata->extensions->crldistributionpoints) {
                    $certdesc .= '<li>'.$this->certFields['crldistributionpoints'].': '.$capath->certdata->extensions->crldistributionpoints;
                }
                if ($capath->certdata->extensions->authorityinfoaccess) {
                    $certdesc .= '<li>'.$this->certFields['authorityinfoaccess'].': '.$capath->certdata->extensions->authorityinfoaccess;
                }
                            
                $certdesc .= '</ul>';
                $more .= '<span class="morecontent"><span>'.$certdesc.
                        '</span>&nbsp;&nbsp;<a href="" class="morelink">'._("more").'&raquo;</a></span></td></tr>';
            } else {
                $certdesc = '<br>';
            }
            $capathtest[] = '<div>'.($capath->message!='' ? $capath->message : _('Test failed')).'</div>'.$more;
            $capathtest[] = '</td>
</tr>
</table>';
            $capathtest[] = '</li></ul>';
        }
        return $capathtest;
    }

    private function collectClients()
    {
        $clientstest = [];
        foreach ($this->allReachabilityResults['clients'] as $clients) {
            $hostindex = $clients->hostindex; 
            $clientstest[] = '<p><strong>'.$this->hostMap[$clients->IP].'</strong></p>';
            $clientstest[] = "<span id='clientresults$hostindex'>";
            $clientstest[] = '<p></p>';
            if ($this->globalLevelDynamic != \core\common\Entity::L_ERROR) {
                if (property_exists($clients, 'ca')) {
                    $clientstest[] = '<ol>';
                    foreach ($clients->ca as $ca) {
                        $srefused = 0;
                        $cliinfo = '';
                        $cliinfo .= '<li>'._('Client certificate').' <b>'.$ca->clientcertinfo->from.
                                    '</b>'.', '.$ca->clientcertinfo->message .
                                    '<br> (CA: '.$ca->clientcertinfo->issuer.')<ul>';
                        foreach ($ca->certificate as $certificate) {
                            if ($certificate->returncode == \core\diag\RADIUSTests::RETVAL_CONNECTION_REFUSED) {
                                $srefused = 1;
                            }
                        }
                        if ($srefused == 0) {
                            foreach ($ca->certificate as $certificate) { 
                                $cliinfo .= '<li><i>'.$certificate->message. 
                                            ', '._("expected result: ").$this->states[$certificate->expected].'</i>';
                                $cliinfo .= '<ul style="list-style-type: none;">';
                                $level = $certificate->returncode;
                                if ($level < 0) {
                                    $level = \core\common\Entity::L_ERROR;
                                }
                                $add = '';
                                if ($certificate->expected == 'PASS') {
                                    if ($certificate->connected == 1) {
                                        $state = _("Server accepted this client certificate");
                                    } else {
                                        if (property_exists($certificate, 'reason') && $certificate->reason == \core\diag\RADIUSTests::CERTPROB_UNKNOWN_CA) {
                                            $add = '<br>'._('You should update your list of accredited CAs').
                                                            ' <a href=\"'.\config\Diagnostics::RADIUSTESTS['accreditedCAsURL'].'\">'.
                                                            _('Get it from here.').'</a>';
                                        }
                                        $state = _('Server did not accept this client certificate - reason').': '.
                                                    $certificate->resultcomment;
                                    }
                                } else {
                                    if ($certificate->connected == 1) {
                                        $level = \core\common\Entity::L_WARN;
                                        $state = _('Server accepted this client certificate, but should not have');
                                    } else {
                                        $level = \core\common\Entity::L_OK;
                                        $state = _('Server did not accept this client certificate').': '.$certificate->resultcomment;
                                    }
                                }
                                $cliinfo .= '<li><table><tbody><tr><td class="icon_td"><img class="icon" src="'.$this->stateIcons[$level].'" style="width: 24px;"></td><td>'.$state;
                                $cliinfo .= ' ('.sprintf(_('elapsed time: %sms.'), $certificate->time_millisec).'&nbsp;) '.$add.'</td></tr>';
                                $cliinfo .= '</tbody></table></ul></li>';
                                if (property_exists($certificate, 'finalerror') && $certificate->finalerror == 1) {
                                    $cliinfo = '<li>'._('Rest of tests for this CA skipped').'</li>';
                                }
                            }
                            $cliinfo .= '</ul>';
                        }
                                    
                        if ($srefused > 0) {
                            $cliinfo = _('Connection refused');
                            $clientstest[] = "<table><tr><td class='icon_td' id='srcclient".$hostindex."_img'><img src='".$this->stateIcons[\core\common\Entity::L_ERROR]."'></td>".
                                        "<td id='srcclient$hostindex'><p>$cliinfo</p></td></tr></table>";
                        } else {
                            $clientstest[] = "<p>$cliinfo</p>";
                        }
                    }
                    
                } else {
                    $cliinfo = _('Test failed');
                    $clientstest[] = "<table><tr><td class='icon_td' id='srcclient".$hostindex."_img'><img src='".
                                    $this->stateIcons[\core\common\Entity::L_WARN]."'></td>" .
                                    "<td id='srcclient$hostindex'>$cliinfo</td></tr></table>";
                }
            } else {
                $clientstest[] = '<ul style="list-style-type: none;" class="clientsresult"><li>';
                $clientstest[] = "<table id='clientsresults$hostindex'  style='width:100%'>
<tr>
<td class='icon_td'><img src='";
                $clientstest[] = $this->stateIcons[\core\common\Entity::L_ERROR]."' id='srcclients".$hostindex."_img'></td>
<td id='srcclient$hostindex'>";
                $clientstest[] = _("These tests were skipped because of previous errors.").'</td></tr></table></ul>';
            }
            $clientstest[] = '</ol><p></p>';
        }
        return $clientstest;
    }
    
    public function printDynamic()
    {
        $out = [];
        $out[] = "<div id='dynamic_tests'><fieldset class='option_container'>
            <legend><strong>"._("DYNAMIC connectivity tests")."</strong></legend>";
        
        if (count($this->rfc7585suite->NAPTR_hostname_records) > 0) {    
            $capathtest = $this->collectCAPath();
            $clientstest = $this->collectClients();
            $out[] = '<div style="align:right;">';            
            $out[] = '<div style="align:right; display: ';
            if ($this->globalLevelDynamic == \core\common\Entity::L_OK && !$this->areFailed) {
                $out[] = 'none';
            }
            $out[] = ';" id="dynamic_result_fail"><b>'._("Some errors were found during the tests, see below").'</b></div>';
            $out[] = '<div style="align:right; display: ';
            if ($this->globalLevelDynamic != \core\common\Entity::L_OK || $this->areFailed) {
                $out[] = 'none';
            }
            $out[] = '" id="dynamic_result_pass"><b>'.
                                _("All tests passed, congratulations!").'</b></div>'.
                                '<div style="align:left;"><a href="" class="moreall"><i>'._('Show detailed information for all tests').'&raquo;</i></a></div>';
            $out[] = join('', $capathtest);
            $out[] = '<span id="clientstest" style="display: ;"><p><hr><b>'._('Checking if certificates from  CAs are accepted...').'</b><p>'._('A few client certificates will be tested to check if servers are resistant to some certificate problems.').'<p>';
            $out[] = join('', $clientstest);
            $out[] = '</span>';
            $out[] = '</div>';
        }
        $out[] = "</fieldset></div></div>";
        return join('', $out);
    }
    
}
