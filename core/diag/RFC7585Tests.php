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

namespace core\diag;

require_once dirname(dirname(__DIR__)) . "/config/_config.php";

/**
 * Test suite to verify that a given NAI realm has NAPTR records according to
 * consortium-agreed criteria
 * Can only be used if CONFIG_DIAGNOSTICS['RADIUSTESTS'] is configured.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class RFC7585Tests extends AbstractTest {

    /**
     * maintains state for the question: has the NAPTR existence check already been executed? Holds the number of NAPTR records found if so.
     * 
     * @var integer
     */
    private $NAPTR_executed;
    
    /**
     * maintains state for the question: has the NAPTR compliance check already been executed?
     * 
     * @var integer
     */
    private $NAPTR_compliance_executed;
    
    /**
     * maintains state for the question: has the NAPTR SRV check already been executed? Holds the number of SRV records if so.
     * 
     * @var integer
     */
    private $NAPTR_SRV_executed;
    
    /**
     * maintains state for the question: has the existrence of hostnames been checked already? Holds the number of IP:port pairs if so.
     * 
     * @var integer
     */
    private $NAPTR_hostname_executed;
    
    /**
     * holds the list of NAPTR records found
     * 
     * @var array
     */
    private $NAPTR_records;
    
    /**
     * holds the list of SRV records found
     * 
     * @var array
     */
    private $NAPTR_SRV_records;
    
    /**
     * stores the various errors encountered during the checks
     * 
     * @var array
     */
    private $errorlist;
    
    /**
     * stores the IP address / port pairs (strings) which were ultimately found
     * as candidate RADIUS/TLS servers
     * 
     * @var array
     */
    public $NAPTR_hostname_records;

    // return codes specific to NAPTR existence checks
    
    /**
     * test hasn't been run yet
     */
    const RETVAL_NOTRUNYET = -1;
    
    /**
     * no NAPTRs for domain; this is not an error, simply means that realm is not doing dynamic discovery for any service
     */
    const RETVAL_NONAPTR = -104;

    /**
     * no eduroam NAPTR for domain; this is not an error, simply means that realm is not doing dynamic discovery for eduroam
     */
    const RETVAL_ONLYUNRELATEDNAPTR = -105;

    /**
     * This private variable contains the realm to be checked. Is filled in the
     * class constructor.
     * 
     * @var string
     */
    private $realm;

    /**
     * Initialises the dynamic discovery test instance for a specific realm that is to be tested
     * 
     * @param string $realm the realm to be tested
     */
    public function __construct(string $realm) {
        parent::__construct();
        \core\common\Entity::intoThePotatoes();
        // return codes specific to NAPTR existence checks
        /**
         * no NAPTRs for domain; this is not an error, simply means that realm is not doing dynamic discovery for any service
         */
        $this->returnCodes[RFC7585Tests::RETVAL_NONAPTR]["message"] = _("This realm has no NAPTR records.");
        $this->returnCodes[RFC7585Tests::RETVAL_NONAPTR]["severity"] = \core\common\Entity::L_OK;

        /**
         * no eduroam NAPTR for domain; this is not an error, simply means that realm is not doing dynamic discovery for eduroam
         */
        $this->returnCodes[RFC7585Tests::RETVAL_ONLYUNRELATEDNAPTR]["message"] = _("NAPTR records were found, but all of them refer to unrelated services.");
        $this->returnCodes[RFC7585Tests::RETVAL_ONLYUNRELATEDNAPTR]["severity"] = \core\common\Entity::L_OK;


        $this->realm = $realm;
        $this->NAPTR_executed = RFC7585Tests::RETVAL_NOTRUNYET;
        $this->NAPTR_compliance_executed = RFC7585Tests::RETVAL_NOTRUNYET;
        $this->NAPTR_SRV_executed = RFC7585Tests::RETVAL_NOTRUNYET;
        $this->NAPTR_hostname_executed = RFC7585Tests::RETVAL_NOTRUNYET;
        $this->NAPTR_records = [];
        $this->NAPTR_SRV_records = [];
        $this->NAPTR_hostname_records = [];
        $this->errorlist = [];
        \core\common\Entity::outOfThePotatoes();
    }

    /**
     * Tests if this realm exists in DNS and has NAPTR records matching the
     * configured consortium NAPTR target.
     * 
     * possible RETVALs:
     * - RETVAL_NOTCONFIGURED; needs CONFIG_DIAGNOSTICS['RADIUSTESTS']['TLS-discoverytag']
     * - RETVAL_ONLYUNRELATEDNAPTR
     * - RETVAL_NONAPTR
     * 
     * @return int Either a RETVAL constant or a positive number (count of relevant NAPTR records)
     */
    public function relevantNAPTR() {
        if (CONFIG_DIAGNOSTICS['RADIUSTESTS']['TLS-discoverytag'] == "") {
            $this->NAPTR_executed = RADIUSTests::RETVAL_NOTCONFIGURED;
            return RADIUSTests::RETVAL_NOTCONFIGURED;
        }
        $NAPTRs = dns_get_record($this->realm . ".", DNS_NAPTR);
        if ($NAPTRs === FALSE || count($NAPTRs) == 0) {
            $this->NAPTR_executed = RFC7585Tests::RETVAL_NONAPTR;
            return RFC7585Tests::RETVAL_NONAPTR;
        }
        $NAPTRs_consortium = [];
        foreach ($NAPTRs as $naptr) {
            if ($naptr["services"] == CONFIG_DIAGNOSTICS['RADIUSTESTS']['TLS-discoverytag']) {
                $NAPTRs_consortium[] = $naptr;
            }
        }
        if (count($NAPTRs_consortium) == 0) {
            $this->NAPTR_executed = RFC7585Tests::RETVAL_ONLYUNRELATEDNAPTR;
            return RFC7585Tests::RETVAL_ONLYUNRELATEDNAPTR;
        }
        $this->NAPTR_records = $NAPTRs_consortium;
        $this->NAPTR_executed = count($NAPTRs_consortium);
        return count($NAPTRs_consortium);
    }

    /**
     * Tests if all the dicovered NAPTR entries conform to the consortium's requirements
     * 
     * possible RETVALs:
     * - RETVAL_NOTCONFIGURED; needs CONFIG_DIAGNOSTICS['RADIUSTESTS']['TLS-discoverytag']
     * - RETVAL_INVALID (at least one format error)
     * - RETVAL_OK (all fine)

     * @return int one of two RETVALs above
     */
    public function relevantNAPTRcompliance() {
// did we query DNS for the NAPTRs yet? If not, do so now.
        if ($this->NAPTR_executed == RFC7585Tests::RETVAL_NOTRUNYET) {
            $this->relevantNAPTR();
        }
// if the NAPTR checks aren't configured, tell the caller
        if ($this->NAPTR_executed === RADIUSTests::RETVAL_NOTCONFIGURED) {
            $this->NAPTR_compliance_executed = RADIUSTests::RETVAL_NOTCONFIGURED;
            return RADIUSTests::RETVAL_NOTCONFIGURED;
        }
// if there were no relevant NAPTR records, we are compliant :-)
        if (count($this->NAPTR_records) == 0) {
            $this->NAPTR_compliance_executed = RADIUSTests::RETVAL_OK;
            return RADIUSTests::RETVAL_OK;
        }
        $formatErrors = [];
// format of NAPTRs is consortium specific. eduroam below; others need
// their own code
        if (CONFIG_DIAGNOSTICS['RADIUSTESTS']['TLS-discoverytag'] == "x-eduroam:radius.tls") {
            foreach ($this->NAPTR_records as $edupointer) {
// must be "s" type for SRV
                if ($edupointer["flags"] != "s" && $edupointer["flags"] != "S") {
                    $formatErrors[] = ["TYPE" => "NAPTR-FLAG", "TARGET" => $edupointer['flag']];
                }
// no regex
                if ($edupointer["regex"] != "") {
                    $formatErrors[] = ["TYPE" => "NAPTR-REGEX", "TARGET" => $edupointer['regex']];
                }
            }
        }
        if (count($formatErrors) == 0) {
            $this->NAPTR_compliance_executed = RADIUSTests::RETVAL_OK;
            return RADIUSTests::RETVAL_OK;
        }
        $this->errorlist = array_merge($this->errorlist, $formatErrors);
        $this->NAPTR_compliance_executed = RADIUSTests::RETVAL_INVALID;
        return RADIUSTests::RETVAL_INVALID;
    }

    /**
     * Tests if NAPTR records can be resolved to SRVs. Will only run if NAPTR
     * checks completed without error.
     *
     * possible RETVALs:
     * - RETVAL_INVALID
     * - RETVAL_SKIPPED
     * 
     * @return int one of the RETVALs above or the number of SRV records which were resolved
     */
    public function relevantNAPTRsrvResolution() {
// see if preceding checks have been run, and run them if not
// compliance check will cascade NAPTR check on its own
        if ($this->NAPTR_compliance_executed == RFC7585Tests::RETVAL_NOTRUNYET) {
            $this->relevantNAPTRcompliance();
        }
// we only run the SRV checks if all records are compliant and more than one relevant NAPTR exists
        if ($this->NAPTR_executed <= 0 || $this->NAPTR_compliance_executed == RADIUSTests::RETVAL_INVALID) {
            $this->NAPTR_SRV_executed = RADIUSTests::RETVAL_SKIPPED;
            return RADIUSTests::RETVAL_SKIPPED;
        }

        $sRVerrors = [];
        $sRVtargets = [];

        foreach ($this->NAPTR_records as $edupointer) {
            $tempResult = dns_get_record($edupointer["replacement"], DNS_SRV);
            if ($tempResult === FALSE || count($tempResult) == 0) {
                $sRVerrors[] = ["TYPE" => "SRV_NOT_RESOLVING", "TARGET" => $edupointer['replacement']];
            } else {
                foreach ($tempResult as $res) {
                    $sRVtargets[] = ["hostname" => $res["target"], "port" => $res["port"]];
                }
            }
        }
        $this->NAPTR_SRV_records = $sRVtargets;
        if (count($sRVerrors) > 0) {
            $this->NAPTR_SRV_executed = RADIUSTests::RETVAL_INVALID;
            $this->errorlist = array_merge($this->errorlist, $sRVerrors);
            return RADIUSTests::RETVAL_INVALID;
        }
        $this->NAPTR_SRV_executed = count($sRVtargets);
        return count($sRVtargets);
    }

    /**
     * Checks whether the previously discovered hostnames have actual IP addresses in DNS.
     * 
     * The actual list is stored in the class property NAPTR_hostname_records.
     * 
     * @return int count of IP / port pairs for all the hostnames
     */
    public function relevantNAPTRhostnameResolution() {
// make sure the previous tests have been run before we go on
// preceeding tests will cascade automatically if needed
        if ($this->NAPTR_SRV_executed == RFC7585Tests::RETVAL_NOTRUNYET) {
            $this->relevantNAPTRsrvResolution();
        }
// if previous are SKIPPED, skip this one, too
        if ($this->NAPTR_SRV_executed == RADIUSTests::RETVAL_SKIPPED) {
            $this->NAPTR_hostname_executed = RADIUSTests::RETVAL_SKIPPED;
            return RADIUSTests::RETVAL_SKIPPED;
        }
// the SRV check may have returned INVALID, but could have found a
// a working subset of hosts anyway. We should continue checking all 
// dicovered names.

        $ipAddrs = [];
        $resolutionErrors = [];

        foreach ($this->NAPTR_SRV_records as $server) {
            $hostResolutionIPv6 = dns_get_record($server["hostname"], DNS_AAAA);
            $hostResolutionIPv4 = dns_get_record($server["hostname"], DNS_A);
            $hostResolution = array_merge($hostResolutionIPv6, $hostResolutionIPv4);
            if ($hostResolution === FALSE || count($hostResolution) == 0) {
                $resolutionErrors[] = ["TYPE" => "HOST_NO_ADDRESS", "TARGET" => $server['hostname']];
            } else {
                foreach ($hostResolution as $address) {
                    if (isset($address["ip"])) {
                        $ipAddrs[] = ["family" => "IPv4", "IP" => $address["ip"], "port" => $server["port"], "status" => ""];
                    } else {
                        $ipAddrs[] = ["family" => "IPv6", "IP" => $address["ipv6"], "port" => $server["port"], "status" => ""];
                    }
                }
            }
        }

        $this->NAPTR_hostname_records = $ipAddrs;

        if (count($resolutionErrors) > 0) {
            $this->errorlist = array_merge($this->errorlist, $resolutionErrors);
            $this->NAPTR_hostname_executed = RADIUSTests::RETVAL_INVALID;
            return RADIUSTests::RETVAL_INVALID;
        }
        $this->NAPTR_hostname_executed = count($this->NAPTR_hostname_records);
        return count($this->NAPTR_hostname_records);
    }

}
