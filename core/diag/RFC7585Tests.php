<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

namespace core\diag;

use \Exception;

require_once(dirname(dirname(__DIR__)) . "/config/_config.php");

/**
 * Test suite to verify that a given NAI realm has NAPTR records according to
 * consortium-agreed criteria
 * Can only be used if CONFIG['RADIUSTESTS'] is configured.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class RFC7585Tests extends AbstractTest {

    private $NAPTR_executed;
    private $NAPTR_compliance_executed;
    private $NAPTR_SRV_executed;
    private $NAPTR_hostname_executed;
    private $NAPTR_records;
    private $NAPTR_SRV_records;
    private $errorlist;
    public $NAPTR_hostname_records;

    // return codes specific to NAPTR existence checks
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

    public function __construct($realm) {
        parent::__construct();

        // return codes specific to NAPTR existence checks
        /**
         * no NAPTRs for domain; this is not an error, simply means that realm is not doing dynamic discovery for any service
         */
        $this->return_codes[RFC7585Tests::RETVAL_NONAPTR]["message"] = _("This realm has no NAPTR records.");
        $this->return_codes[RFC7585Tests::RETVAL_NONAPTR]["severity"] = \core\common\Entity::L_OK;

        /**
         * no eduroam NAPTR for domain; this is not an error, simply means that realm is not doing dynamic discovery for eduroam
         */
        $this->return_codes[RFC7585Tests::RETVAL_ONLYUNRELATEDNAPTR]["message"] = _("NAPTR records were found, but all of them refer to unrelated services.");
        $this->return_codes[RFC7585Tests::RETVAL_ONLYUNRELATEDNAPTR]["severity"] = \core\common\Entity::L_OK;


        $this->realm = $realm;
        $this->NAPTR_executed = FALSE;
        $this->NAPTR_compliance_executed = FALSE;
        $this->NAPTR_SRV_executed = FALSE;
        $this->NAPTR_hostname_executed = FALSE;
        $this->NAPTR_records = [];
        $this->NAPTR_SRV_records = [];
        $this->NAPTR_hostname_records = [];
        $this->errorlist = [];
    }

    /**
     * Tests if this realm exists in DNS and has NAPTR records matching the
     * configured consortium NAPTR target.
     * 
     * possible RETVALs:
     * - RETVAL_NOTCONFIGURED; needs CONFIG['RADIUSTESTS']['TLS-discoverytag']
     * - RETVAL_ONLYUNRELATEDNAPTR
     * - RETVAL_NONAPTR
     * 
     * @return int Either a RETVAL constant or a positive number (count of relevant NAPTR records)
     */
    public function NAPTR() {
        if (CONFIG['RADIUSTESTS']['TLS-discoverytag'] == "") {
            $this->NAPTR_executed = RADIUSTests::RETVAL_NOTCONFIGURED;
            return RADIUSTests::RETVAL_NOTCONFIGURED;
        }
        $NAPTRs = dns_get_record($this->realm . ".", DNS_NAPTR);
        if ($NAPTRs === FALSE || count($NAPTRs) == 0) {
            $this->NAPTR_executed = RADIUSTests::RETVAL_NONAPTR;
            return RADIUSTests::RETVAL_NONAPTR;
        }
        $NAPTRs_consortium = [];
        foreach ($NAPTRs as $naptr) {
            if ($naptr["services"] == CONFIG['RADIUSTESTS']['TLS-discoverytag']) {
                $NAPTRs_consortium[] = $naptr;
            }
        }
        if (count($NAPTRs_consortium) == 0) {
            $this->NAPTR_executed = RADIUSTests::RETVAL_ONLYUNRELATEDNAPTR;
            return RADIUSTests::RETVAL_ONLYUNRELATEDNAPTR;
        }
        $this->NAPTR_records = $NAPTRs_consortium;
        $this->NAPTR_executed = count($NAPTRs_consortium);
        return count($NAPTRs_consortium);
    }

    /**
     * Tests if all the dicovered NAPTR entries conform to the consortium's requirements
     * 
     * possible RETVALs:
     * - RETVAL_NOTCONFIGURED; needs CONFIG['RADIUSTESTS']['TLS-discoverytag']
     * - RETVAL_INVALID (at least one format error)
     * - RETVAL_OK (all fine)

     * @return int one of two RETVALs above
     */
    public function NAPTR_compliance() {
// did we query DNS for the NAPTRs yet? If not, do so now.
        if ($this->NAPTR_executed === FALSE) {
            $this->NAPTR();
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
        if (CONFIG['CONSORTIUM']['name'] == "eduroam") { // SW: APPROVED
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
    public function NAPTR_SRV() {
// see if preceding checks have been run, and run them if not
// compliance check will cascade NAPTR check on its own
        if ($this->NAPTR_compliance_executed === FALSE) {
            $this->NAPTR_compliance();
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

    public function NAPTR_hostnames() {
// make sure the previous tests have been run before we go on
// preceeding tests will cascade automatically if needed
        if ($this->NAPTR_SRV_executed === FALSE) {
            $this->NAPTR_SRV();
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
