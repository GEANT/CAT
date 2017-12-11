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
 * The overall coordination class that runs all kinds of tests to find out where
 * and what is wrong. Operates on the realm of a user. Can do more magic if it
 * also knows which federation the user is currently positioned in, or even 
 * which exact hotspot to analyse.
 */
class Telepath extends AbstractTest {

    private $additionalFindings;
    private $realm;
    private $visitedFlr;
    private $visitedHotspot;
    private $catIdP;
    private $dbIdP;
    private $idPFederation;
    private $testsuite;

    public function __construct(string $realm, $visitedFlr = NULL, $visitedHotspot = NULL) {
        parent::__construct();

        $this->additionalFindings = [];
        $this->realm = $realm;
        $this->visitedFlr = $visitedFlr;
        $this->visitedHotspot = $visitedHotspot;
        $links = \core\Federation::determineIdPIdByRealm($realm);
        $this->catIdP = $links["CAT"];
        $this->dbIdP = $links["EXTERNAL"];
        $this->idPFederation = $links["FEDERATION"];
        // this is NULL if the realm is not known in either DB
        // if so, let's try a regex to extract the ccTLD if any
        $matches = [];
        if ($this->idPFederation === NULL && preg_match("/\.(..)$/", $realm, $matches)) {
            $this->idPFederation = strtoupper($matches[1]);
        }
        $this->loggerInstance->debug(4, "XYZ: IdP-side NRO is " . $this->idPFederation . "\n");
    }

    /* The eduroam OT monitoring has the following return codes:
     * 

      Status codes

      0 - O.K.
      -1 - Accept O.K. Reject No
      -2 - Reject O.K. Accept No
      -3 - Accept No Reject No
      -9 - system error
      -10 - Accept O.K. Reject timeou
      -11 - Accept O.K. Reject no EAP
      -20 - Reject O.K. Accept timeou
      -21 - Reject O.K. Accept no EAP
      -31 - Accept No  Reject timeou
      -32 - Accept Timeout Reject no
      -33 - Accept Timeout Reject timeou
      -35 - Accept No Reject no EAP
      -36 - Reject No Accept no EAP
      -37 - Reject No EAP Accept no EAP
      -40 - UDP test error

     */

    private function genericAPIStatus($type, $param1 = NULL, $param2 = NULL) {
        $endpoints = [
            'tlr_test' => "https://monitor.eduroam.org/mapi/index.php?type=tlr_test&tlr=$param1",
            'federation_via_tlr' => "https://monitor.eduroam.org/mapi/index.php?type=federation_via_tlr&federation=$param1",
            'flrs_test' => "https://monitor.eduroam.org/mapi/index.php?type=flrs_test&federation=$param1",
            'flr_by_federation' => "https://monitor.eduroam.org/mapi/index.php?type=flr_by_federation&federation=$param2&with=$param1",
        ];
        $ignore = [
            'tlr_test' => 'tlr',
            'federation_via_tlr' => 'fed',
            'flrs_test' => 'fed',
            'flr_by_federation' => 'fed',
        ];
        $this->loggerInstance->debug(4, "Doing Monitoring API check with $endpoints[$type]\n");
        $jsonResult = \core\common\OutsideComm::downloadFile($endpoints[$type]);
        $this->loggerInstance->debug(4, "Monitoring API Result: $jsonResult\n");
        $decoded = json_decode($jsonResult, TRUE);
        $retval = [];
        $retval["RAW"] = $decoded;
        $atLeastOneFunctional = FALSE;
        $allFunctional = TRUE;
        if (!isset($decoded[$type]) || isset($decoded['ERROR'])) {
            $retval["STATUS"] = AbstractTest::STATUS_MONITORINGFAIL;
            return $retval;
        }
        foreach ($decoded[$type] as $instance => $resultset) {
            if ($instance == $ignore[$type]) {
                // don't care
                continue;
            }
            // TLR test has statuscode on this level, otherwise need to recurse
            // one more level
            switch ($type) {
                case "tlr_test":
                    switch ($resultset['status_code']) {
                        case 0:
                            $atLeastOneFunctional = TRUE;
                            break;
                        case 9: // monitoring itself has an error, no effect on our verdict
                        case -1: // Reject test fails, but we diagnose supposed-working connection, so no effect on our verdict
                        case -10: // same as previous
                        case -11: // same as previous
                            break;
                        default:
                            $allFunctional = FALSE;
                    }
                    break;
                default:
                    foreach ($resultset as $particularInstance => $particularResultset) {
                        switch ($particularResultset['status_code']) {
                            case 0:
                                $atLeastOneFunctional = TRUE;
                                break;
                            case 9: // monitoring itself has an error, no effect on our verdict
                            case -1: // Reject test fails, but we diagnose supposed-working connection, so no effect on our verdict
                            case -10: // same as previous
                            case -11: // same as previous
                                break;
                            default:
                                $allFunctional = FALSE;
                        }
                    }
            }
        }

        if ($allFunctional) {
            $retval["STATUS"] = AbstractTest::STATUS_GOOD;
            return $retval;
        }
        if ($atLeastOneFunctional) {
            $retval["STATUS"] = AbstractTest::STATUS_PARTIAL;
            return $retval;
        }
        $retval["STATUS"] = AbstractTest::STATUS_DOWN;
        return $retval;
    }

    private function checkEtlrStatus() {
        // TODO: we always check the European TLRs even though the connection in question might go via others and/or this one
        // needs a table to determine what goes where :-(
        return $this->genericAPIStatus("tlr_test", "TLR_EU");
    }

    private function checkFedEtlrUplink($fed) {
        // TODO: we always check the European TLRs even though the connection in question might go via others and/or this one
        // needs a table to determine what goes where :-(
        return $this->genericAPIStatus("federation_via_tlr", $fed);
    }

    private function checkFlrServerStatus($fed) {
        // TODO: we always check the European TLRs even though the connection in question might go via others and/or this one
        // needs a table to determine what goes where :-(
        return $this->genericAPIStatus("flrs_test", $fed);
    }

    private function checkNROFlow() {
        return $this->genericAPIStatus("flr_by_federation", $this->idPFederation, $this->visitedFlr);
    }

    /**
     * Runs the CAT-internal diagnostics tests. Determines the state of the 
     * realm (and indirectly that of the links and statuses of involved proxies
     * and returns a judgment whether external Monitoring API tests are warranted
     * or not
     * @return boolean TRUE if external tests have to be run
     */
    private function CATInternalTests() {
        // we are expecting to get a REJECT from all runs, because that means the packet got through to the IdP.
        // (the ETLR sometimes does a "Reject instead of Ignore" but that is filtered out and changed into a timeout
        // by the test suite automatically, so it does not disturb the measurement)
        // If that's true, we can exclude two sources of problems (both proxy levels). Hooray!
        $allAreConversationReject = TRUE;
        $atLeastOneConversationReject = FALSE;

        foreach (CONFIG_DIAGNOSTICS['RADIUSTESTS']['UDP-hosts'] as $probeindex => $probe) {
            $reachCheck = $this->testsuite->udpReachability($probeindex);
            if ($reachCheck != RADIUSTests::RETVAL_CONVERSATION_REJECT) {
                $allAreConversationReject = FALSE;
            } else {
                $atLeastOneConversationReject = TRUE;
            }

            $this->additionalFindings[AbstractTest::INFRA_ETLR][] = ["DETAIL" => $this->testsuite->consolidateUdpResult($probeindex)];
            $this->additionalFindings[AbstractTest::INFRA_NRO_IDP][] = ["DETAIL" => $this->testsuite->consolidateUdpResult($probeindex)];
            $this->additionalFindings[AbstractTest::INFRA_IDP_RADIUS][] = ["DETAIL" => $this->testsuite->consolidateUdpResult($probeindex)];
        }

        if ($allAreConversationReject) {
            $this->additionalFindings[AbstractTest::INFRA_ETLR][] = ["CONNCHECK" => RADIUSTests::RETVAL_CONVERSATION_REJECT];
            $this->additionalFindings[AbstractTest::INFRA_NRO_IDP][] = ["CONNCHECK" => RADIUSTests::RETVAL_CONVERSATION_REJECT];
            $this->additionalFindings[AbstractTest::INFRA_IDP_RADIUS][] = ["CONNCHECK" => RADIUSTests::RETVAL_CONVERSATION_REJECT];
            $this->additionalFindings[AbstractTest::INFRA_LINK_ETLR_NRO_IDP][] = ["LINKCHECK" => RADIUSTests::L_OK];
            // we have actually reached an IdP, so all links are good, and the
            // realm is routable in eduroam. So even if it exists in neither DB
            // we can exclude the NONEXISTENTREALM case
            unset($this->possibleFailureReasons[AbstractTest::INFRA_ETLR]);
            unset($this->possibleFailureReasons[AbstractTest::INFRA_NRO_IDP]);
            unset($this->possibleFailureReasons[AbstractTest::INFRA_LINK_ETLR_NRO_IDP]);
            unset($this->possibleFailureReasons[AbstractTest::INFRA_NONEXISTENTREALM]);
        }

        if ($atLeastOneConversationReject) {
            // at least we can be sure it exists
            unset($this->possibleFailureReasons[AbstractTest::INFRA_NONEXISTENTREALM]);
            // It could still be an IdP RADIUS problem in that some cert oddities 
            // in combination with the device lead to a broken auth
            // if there is nothing beyond the "REMARK" level, then it's not an IdP problem
            // otherwise, add the corresponding warnings and errors to $additionalFindings
            switch ($this->additionalFindings[AbstractTest::INFRA_IDP_RADIUS][0]['DETAIL']['level']) {
                case RADIUSTests::L_OK:
                case RADIUSTests::L_REMARK:
                    // both are fine - the IdP is working and the user problem
                    // is not on the IdP RADIUS level
                    $this->additionalFindings[AbstractTest::INFRA_IDP_RADIUS][] = ["ODDITYLEVEL" => $this->additionalFindings[AbstractTest::INFRA_IDP_RADIUS][0]['DETAIL']['level']];
                    unset($this->possibleFailureReasons[AbstractTest::INFRA_IDP_RADIUS]);
                    break;
                case RADIUSTests::L_WARN:
                    $this->additionalFindings[AbstractTest::INFRA_IDP_RADIUS][] = ["ODDITYLEVEL" => RADIUSTests::L_WARN];
                    $this->possibleFailureReasons[AbstractTest::INFRA_IDP_RADIUS] = 0.3; // possibly we found the culprit - if RADIUS server is misconfigured AND user is on a device which reacts picky about exactly this oddity.
                    break;
                case RADIUSTests::L_ERROR:
                    $this->additionalFindings[AbstractTest::INFRA_IDP_RADIUS][] = ["ODDITYLEVEL" => RADIUSTests::L_ERROR];
                    $this->possibleFailureReasons[AbstractTest::INFRA_IDP_RADIUS] = 0.8; // errors are never good, so we can be reasonably sure we've hit the spot!
            }
        }
    }

    public function magic() {

        // simple things first: do we know anything about the realm, either
        // because it's a CAT participant or because it's in the eduroam DB?
        // if so, we can exclude the INFRA_NONEXISTENTREALM cause

        $this->additionalFindings[AbstractTest::INFRA_NONEXISTENTREALM][] = ["ID1" => $this->catIdP, "ID2" => $this->dbIdP];

        if ($this->catIdP != \core\Federation::UNKNOWN_IDP || $this->dbIdP != \core\Federation::UNKNOWN_IDP) {
            unset($this->possibleFailureReasons[AbstractTest::INFRA_NONEXISTENTREALM]);
        }

        // let's do the least amount of testing needed:
        // - The CAT reachability test already covers ELTRs, IdP NRO level and the IdP itself.
        //   if the realm maps to a CAT IdP, we can run the more thorough tests; otherwise just
        //   the normal shallow ones

        if ($this->catIdP > 0) {
            $idpObject = new \core\IdP($this->catIdP);
            $profileObjects = $idpObject->listProfiles();

            $bestProfile = FALSE;


            foreach ($profileObjects as $profileObject) {
                $mangledRealm = substr($profileObject->realm, strpos($profileObject->realm, "@") + 1);
                $readinessLevel = $profileObject->readinessLevel();
                if ($readinessLevel == \core\AbstractProfile::READINESS_LEVEL_SHOWTIME && $mangledRealm == $this->realm) {
                    $bestProfile = $profileObject;
                    break;
                }
                if ($readinessLevel == \core\AbstractProfile::READINESS_LEVEL_SUFFICIENTCONFIG && $profileObject->realm == $this->realm) {
                    $bestProfile = $profileObject;
                }
            }
            if ($bestProfile == FALSE) { // huh? no match on the realm. Then let's take the next-best with SUFFICIENTCONFIG
                foreach ($profileObjects as $profileObject) {
                    $readinessLevel = $profileObject->readinessLevel();
                    if ($readinessLevel == \core\AbstractProfile::READINESS_LEVEL_SUFFICIENTCONFIG) {
                        $bestProfile = $profileObject;
                        break;
                    }
                }
            }
            if ($bestProfile != FALSE) { // still nothing? then there's only a very incomplete profile definition, and we can't work with that. Fall back to shallow
                $this->additionalFindings[AbstractTest::INFRA_IDP_RADIUS][] = ["Profile" => $bestProfile->identifier];
                $this->testsuite = new RADIUSTests($this->realm, $bestProfile->getRealmCheckOuterUsername(), $bestProfile->getEapMethodsinOrderOfPreference(1), $bestProfile->getCollapsedAttributes()['eap:server_name'], $bestProfile->getCollapsedAttributes()["eap:ca_file"]);
            } else {
                $this->additionalFindings[AbstractTest::INFRA_IDP_RADIUS][] = ["Profile" => "UNCONCLUSIVE"];
                $this->testsuite = new RADIUSTests($this->realm, "anonymous@" . $this->realm);
            }
        } else {
            $this->testsuite = new RADIUSTests($this->realm, "anonymous@" . $this->realm);
        }

        // these are the normal "realm check" tests covering ETLR, LINK_NRO_IDP, NRO, IDP_RADIUS
        $this->CATInternalTests();
        // - if the test does NOT go through, we need to find out which of the three is guilty
        // - then, the international "via ETLR" check can be used to find out if the IdP alone
        //   is guilty. If that one fails, the direct monitoring of servers and ETLRs themselves
        //   closes the loop.
        // let's see if the ETLRs are up
        if (array_key_exists(AbstractTest::INFRA_ETLR, $this->possibleFailureReasons)) {

            $etlrStatus = $this->checkEtlrStatus();
            $this->additionalFindings[AbstractTest::INFRA_ETLR][] = $etlrStatus;
            switch ($etlrStatus["STATUS"]) {
                case AbstractTest::STATUS_GOOD:
                    unset($this->possibleFailureReasons[AbstractTest::INFRA_ETLR]);
                    break;
                case AbstractTest::STATUS_PARTIAL:
                case AbstractTest::STATUS_MONITORINGFAIL:
                    // one of the ETLRs is down, or there is a failure in the monitoring system? 
                    // This probably doesn't impact the user unless he's unlucky and has his session fall into failover.
                    // keep ETLR as a possible problem with original probability
                    break;
                case AbstractTest::STATUS_DOWN:
                    // Oh! Well if it is not international roaming, that still doesn't have an effect /in this case/. 
                    if ($this->idPFederation == $this->visitedFlr) {
                        unset($this->possibleFailureReasons[AbstractTest::INFRA_ETLR]);
                        break;
                    }
                    // But it is about int'l roaming, and we are spot on here.
                    // Raise probability by much (even monitoring is sometimes wrong, or a few minutes behind reality)
                    $this->possibleFailureReasons[AbstractTest::INFRA_ETLR] = 0.95;
            }
        }

        // then let's check the IdP's FLR, if we know the IdP federation at all
        if ($this->idPFederation != NULL) {
            if (array_key_exists(AbstractTest::INFRA_NRO_IDP, $this->possibleFailureReasons)) {
                // first the direct connectivity to the server
                $flrServerStatus = $this->checkFlrServerStatus($this->idPFederation);
                $this->additionalFindings[AbstractTest::INFRA_NRO_IDP][] = $flrServerStatus;
                switch ($flrServerStatus["STATUS"]) {
                    case AbstractTest::STATUS_GOOD:
                        unset($this->possibleFailureReasons[AbstractTest::INFRA_NRO_IDP]);
                        break;
                    case AbstractTest::STATUS_PARTIAL:
                        // a subset of the FLRs is down? This probably doesn't impact the user unless he's unlucky and has his session fall into failover.
                        // keep FLR as a possible problem with original probability
                        break;
                    case AbstractTest::STATUS_DOWN:
                        // Raise probability by much (even monitoring is sometimes wrong, or a few minutes behind reality)
                        $this->possibleFailureReasons[AbstractTest::INFRA_NRO_IDP] = 0.95;
                }
            }

            // now let's theck the link
            if (array_key_exists(AbstractTest::INFRA_LINK_ETLR_NRO_IDP, $this->possibleFailureReasons)) {
                $flrUplinkStatus = $this->checkFedEtlrUplink($this->idPFederation);
                $this->additionalFindings[AbstractTest::INFRA_NRO_IDP][] = $flrUplinkStatus;
                switch ($flrUplinkStatus["STATUS"]) {
                    case AbstractTest::STATUS_GOOD:
                        unset($this->possibleFailureReasons[AbstractTest::INFRA_NRO_IDP]);
                        unset($this->possibleFailureReasons[AbstractTest::INFRA_LINK_ETLR_NRO_IDP]);
                        break;
                    case AbstractTest::STATUS_PARTIAL:
                        // a subset of the FLRs is down? This probably doesn't impact the user unless he's unlucky and has his session fall into failover.
                        // keep FLR as a possible problem with original probability
                        break;
                    case AbstractTest::STATUS_DOWN:
                        // Raise probability by much (even monitoring is sometimes wrong, or a few minutes behind reality)
                        // if earlier test found the server itself to be the problem, keep it, otherwise put the blame on the link
                        if ($this->possibleFailureReasons[AbstractTest::INFRA_NRO_IDP] != 0.95) {
                            $this->possibleFailureReasons[AbstractTest::INFRA_LINK_ETLR_NRO_IDP] = 0.95;
                        }
                }
            }
        }
        // now, if we know the country the user is currently in, let's see 
        // if the NRO SP-side is up
        if ($this->visitedFlr !== NULL) {
            $visitedFlrStatus = $this->checkFlrServerStatus($this->visitedFlr);
            $this->additionalFindings[AbstractTest::INFRA_NRO_SP][] = $visitedFlrStatus;
            // direct query to server
            switch ($visitedFlrStatus["STATUS"]) {
                case AbstractTest::STATUS_GOOD:
                    unset($this->possibleFailureReasons[AbstractTest::INFRA_NRO_SP]);
                    break;
                case AbstractTest::STATUS_PARTIAL:
                    // a subset of the FLRs is down? This probably doesn't impact the user unless he's unlucky and has his session fall into failover.
                    // keep FLR as a possible problem with original probability
                    break;
                case AbstractTest::STATUS_DOWN:
                    // Raise probability by much (even monitoring is sometimes wrong, or a few minutes behind reality)
                    $this->possibleFailureReasons[AbstractTest::INFRA_NRO_SP] = 0.95;
            }
            // and again its uplink to the ETLR
            $visitedFlrUplinkStatus = $this->checkFedEtlrUplink($this->visitedFlr);
            $this->additionalFindings[AbstractTest::INFRA_NRO_SP][] = $visitedFlrUplinkStatus;
            switch ($visitedFlrUplinkStatus["STATUS"]) {
                case AbstractTest::STATUS_GOOD:
                    unset($this->possibleFailureReasons[AbstractTest::INFRA_NRO_SP]);
                    unset($this->possibleFailureReasons[AbstractTest::INFRA_LINK_ETLR_NRO_SP]);
                    break;
                case AbstractTest::STATUS_PARTIAL:
                    // a subset of the FLRs is down? This probably doesn't impact the user unless he's unlucky and has his session fall into failover.
                    // keep FLR as a possible problem with original probability
                    break;
                case AbstractTest::STATUS_DOWN:
                    // Raise probability by much (even monitoring is sometimes wrong, or a few minutes behind reality)
                    // if earlier test found the server itself to be the problem, keep it, otherwise put the blame on the link
                    if ($this->possibleFailureReasons[AbstractTest::INFRA_NRO_SP] != 0.95) {
                        $this->possibleFailureReasons[AbstractTest::INFRA_LINK_ETLR_NRO_SP] = 0.95;
                    }
            }
        }
        // the last thing we can do (but it's a bit redundant): check the country-to-country link
        // it's only needed if all three and their links are up, but we want to exclude funny routing blacklists 
        // which occur only in the *combination* of source and dest
        // if there is an issue at that point, blame the SP: once a request
        // would have reached the ETLRs, things would be all good (assuming
        // perfection on the ETLRs here!). So the SP has a wrong config.
        if ($this->idPFederation !== NULL &&
                $this->visitedFlr !== NULL &&
                !array_key_exists(AbstractTest::INFRA_ETLR, $this->possibleFailureReasons) &&
                !array_key_exists(AbstractTest::INFRA_LINK_ETLR_NRO_IDP, $this->possibleFailureReasons) &&
                !array_key_exists(AbstractTest::INFRA_NRO_IDP, $this->possibleFailureReasons) &&
                !array_key_exists(AbstractTest::INFRA_LINK_ETLR_NRO_SP, $this->possibleFailureReasons) &&
                !array_key_exists(AbstractTest::INFRA_NRO_SP, $this->possibleFailureReasons)
        ) {
            $countryToCountryStatus = $this->checkNROFlow();
            $this->additionalFindings[AbstractTest::INFRA_NRO_SP][] = $countryToCountryStatus;
            $this->additionalFindings[AbstractTest::INFRA_ETLR][] = $countryToCountryStatus;
            $this->additionalFindings[AbstractTest::INFRA_NRO_IDP][] = $countryToCountryStatus;
            switch ($countryToCountryStatus["STATUS"]) {
                case AbstractTest::STATUS_GOOD:
                    // all routes work
                    break;
                case AbstractTest::STATUS_PARTIAL:
                // at least one, or even all have a routing problem
                case AbstractTest::STATUS_DOWN:
                    // that's rather telling.
                    $this->possibleFailureReasons[AbstractTest::INFRA_NRO_SP] = 0.95;
            }
        }

        $this->normaliseResultSet();

        return ["SUSPECTS" => $this->possibleFailureReasons, "EVIDENCE" => $this->additionalFindings];
    }

}
