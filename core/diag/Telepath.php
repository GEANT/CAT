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

use \Exception;

/**
 * The overall coordination class that runs all kinds of tests to find out where
 * and what is wrong. Operates on the realm of a user. Can do more magic if it
 * also knows which federation the user is currently positioned in, or even 
 * which exact hotspot to analyse.
 */
class Telepath extends AbstractTest
{

    /**
     * the realm we are testing
     * 
     * @var string
     */
    private $realm;

    /**
     * the federation where the user currently is
     * 
     * @var string|NULL
     */
    private $visitedFlr;

    /**
     * the identifier of the hotspot where the user currently is
     * 
     * @var string|NULL
     */
    private $visitedHotspot;

    /**
     * the CAT profile to which the realm belongs, if any
     * 
     * @var integer
     */
    private $catProfile;

    /**
     * the identifier of the associated IdP in the external DB, if any
     * 
     * @var string
     */
    private $dbIdP;

    /**
     * the federation to which the realm belongs; can be NULL if we can't infer
     * from domain ending nor find it in the DB
     * 
     * @var string|NULL
     */
    private $idPFederation;

    /**
     * instance of the RADIUSTests suite we use to meditate with
     * 
     * @var \core\diag\RADIUSTests
     */
    private $testsuite;

    /**
     * prime the Telepath with info it needs to know to successfully meditate over the problem
     * @param string      $realm          the realm of the user
     * @param string|null $visitedFlr     which NRO is the user visiting
     * @param string|null $visitedHotspot external DB ID of the hotspot he visited
     */
    public function __construct(string $realm, $visitedFlr = NULL, $visitedHotspot = NULL)
    {
        // Telepath is the first one in a chain, no previous inputs allowed
        if (isset($_SESSION) && isset($_SESSION["SUSPECTS"])) {
            unset($_SESSION["SUSPECTS"]);
        }
        if (isset($_SESSION) && isset($_SESSION["EVIDENCE"])) {
            unset($_SESSION["EVIDENCE"]);
        }
        // now fill with default values
        parent::__construct();
        $this->realm = $realm;
        $this->additionalFindings['REALM'] = $this->realm;
        $this->visitedFlr = $visitedFlr;
        $this->visitedHotspot = $visitedHotspot;
        $links = \core\Federation::determineIdPIdByRealm($realm);
        $this->catProfile = $links["CAT"];
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

    /**
     * ask the monitoring API about the things it knows
     * 
     * @param string $type   which type of test to execute
     * @param string $param1 test-specific parameter number 1, if any
     * @param string $param2 test-specific parameter number 2, if any
     * @return array
     */
    private function genericAPIStatus($type, $param1 = NULL, $param2 = NULL)
    {
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
        $retval = [];
        if ($jsonResult === FALSE) { // monitoring API didn't respond at all!
            $retval["STATUS"] = AbstractTest::STATUS_MONITORINGFAIL;
            return $retval;
        }
        $decoded = json_decode($jsonResult, TRUE);
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

    /**
     * Are the ETLR servers in order?
     * @return array
     */
    private function checkEtlrStatus()
    {
        // TODO: we always check the European TLRs even though the connection in question might go via others and/or this one
        // needs a table to determine what goes where :-(
        $ret = $this->genericAPIStatus("tlr_test", "TLR_EU");
        $this->additionalFindings[AbstractTest::INFRA_ETLR][] = $ret;
        switch ($ret["STATUS"]) {
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

    /**
     * Is the uplink between an NRO server and the ETLRs in order?
     * @param string $whichSide test towards the IdP or SP side?
     * @return array
     * @throws Exception
     */
    private function checkFedEtlrUplink($whichSide)
    {
        // TODO: we always check the European TLRs even though the connection in question might go via others and/or this one
        // needs a table to determine what goes where :-(
        switch ($whichSide) {
            case AbstractTest::INFRA_NRO_IDP:
                $fed = $this->idPFederation;
                $linkVariant = AbstractTest::INFRA_LINK_ETLR_NRO_IDP;
                break;
            case AbstractTest::INFRA_NRO_SP:
                $fed = $this->visitedFlr;
                $linkVariant = AbstractTest::INFRA_LINK_ETLR_NRO_SP;
                break;
            default:
                throw new Exception("This function operates on the IdP- or SP-side FLR, nothing else!");
        }

        $ret = $this->genericAPIStatus("federation_via_tlr", $fed);
        $this->additionalFindings[AbstractTest::INFRA_NRO_IDP][] = $ret;
        switch ($ret["STATUS"]) {
            case AbstractTest::STATUS_GOOD:
                unset($this->possibleFailureReasons[$whichSide]);
                unset($this->possibleFailureReasons[$linkVariant]);
                break;
            case AbstractTest::STATUS_PARTIAL:
                // a subset of the FLRs is down? This probably doesn't impact the user unless he's unlucky and has his session fall into failover.
                // keep FLR as a possible problem with original probability
                break;
            case AbstractTest::STATUS_DOWN:
                // Raise probability by much (even monitoring is sometimes wrong, or a few minutes behind reality)
                // if earlier test found the server itself to be the problem, keep it, otherwise put the blame on the link
                if ($this->possibleFailureReasons[$whichSide] != 0.95) {
                    $this->possibleFailureReasons[$linkVariant] = 0.95;
                }
        }
    }

    /**
     * Is the NRO server itself in order?
     * @param string $whichSide test towards the IdP or SP side?
     * @return array
     * @throws Exception
     */
    private function checkFlrServerStatus($whichSide)
    {
        switch ($whichSide) {
            case AbstractTest::INFRA_NRO_IDP:
                $fed = $this->idPFederation;
                break;
            case AbstractTest::INFRA_NRO_SP:
                $fed = $this->visitedFlr;
                break;
            default:
                throw new Exception("This function operates on the IdP- or SP-side FLR, nothing else!");
        }

        $ret = $this->genericAPIStatus("flrs_test", $fed);
        $this->additionalFindings[$whichSide][] = $ret;
        switch ($ret["STATUS"]) {
            case AbstractTest::STATUS_GOOD:
                unset($this->possibleFailureReasons[$whichSide]);
                break;
            case AbstractTest::STATUS_PARTIAL:
                // a subset of the FLRs is down? This probably doesn't impact the user unless he's unlucky and has his session fall into failover.
                // keep FLR as a possible problem with original probability
                break;
            case AbstractTest::STATUS_DOWN:
                // Raise probability by much (even monitoring is sometimes wrong, or a few minutes behind reality)
                $this->possibleFailureReasons[$whichSide] = 0.95;
        }
    }

    /**
     * Does authentication traffic flow between a given source and destination NRO?
     * @return array
     */
    private function checkNROFlow()
    {
        return $this->genericAPIStatus("flr_by_federation", $this->idPFederation, $this->visitedFlr);
    }

    /**
     * Runs the CAT-internal diagnostics tests. Determines the state of the 
     * realm (and indirectly that of the links and statuses of involved proxies
     * and returns a judgment whether external Monitoring API tests are warranted
     * or not
     * @return boolean TRUE if external tests have to be run
     */
    private function CATInternalTests()
    {
        // we are expecting to get a REJECT from all runs, because that means the packet got through to the IdP.
        // (the ETLR sometimes does a "Reject instead of Ignore" but that is filtered out and changed into a timeout
        // by the test suite automatically, so it does not disturb the measurement)
        // If that's true, we can exclude two sources of problems (both proxy levels). Hooray!
        $allAreConversationReject = TRUE;
        $atLeastOneConversationReject = FALSE;

        foreach (\config\Diagnostics::RADIUSTESTS['UDP-hosts'] as $probeindex => $probe) {
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
            $highestObservedErrorLevel = 0;
            foreach ($this->additionalFindings[AbstractTest::INFRA_IDP_RADIUS] as $oneRun) {
                $highestObservedErrorLevel = max($highestObservedErrorLevel, $oneRun['DETAIL']['level'] ?? 0);
            }
            switch ($highestObservedErrorLevel) {
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

    /**
     * can we run thorough checks or not? Thorough can only be done if we can
     * deterministically map the realm to be checked against a CAT Profile, and
     * then only if the profile is complete.
     * 
     * @return void
     */
    private function determineTestsuiteParameters()
    {
        if ($this->catProfile > 0) {
            $profileObject = \core\ProfileFactory::instantiate($this->catProfile);
            $readinessLevel = $profileObject->readinessLevel();

            switch ($readinessLevel) {
                case \core\AbstractProfile::READINESS_LEVEL_SHOWTIME:
                // fall-througuh intended: use the data even if non-public but complete
                case \core\AbstractProfile::READINESS_LEVEL_SUFFICIENTCONFIG:
                    $this->additionalFindings[AbstractTest::INFRA_IDP_RADIUS][] = ["Profile" => $profileObject->identifier];
                    $this->testsuite = new RADIUSTests($this->realm, $profileObject->getRealmCheckOuterUsername(), $profileObject->getEapMethodsinOrderOfPreference(1), $profileObject->getCollapsedAttributes()['eap:server_name'], $profileObject->getCollapsedAttributes()["eap:ca_file"]);
                    break;
                case \core\AbstractProfile::READINESS_LEVEL_NOTREADY:
                    $this->additionalFindings[AbstractTest::INFRA_IDP_RADIUS][] = ["Profile" => "UNCONCLUSIVE"];
                    $this->testsuite = new RADIUSTests($this->realm, "anonymous@" . $this->realm);
                    break;
                default:
            }
        } else {
            $this->testsuite = new RADIUSTests($this->realm, "anonymous@" . $this->realm);
        }
    }

    /**
     * Does the main meditation job
     * @return array the findings
     */
    public function magic()
    {
        $this->testId = \core\CAT::uuid();
        $this->databaseHandle->exec("INSERT INTO diagnosticrun (test_id, realm, suspects, evidence) VALUES ('$this->testId', '$this->realm', NULL, NULL)");
        // simple things first: do we know anything about the realm, either
        // because it's a CAT participant or because it's in the eduroam DB?
        // if so, we can exclude the INFRA_NONEXISTENTREALM cause
        $this->additionalFindings[AbstractTest::INFRA_NONEXISTENTREALM]['DATABASE_STATUS'] = ["ID1" => $this->catProfile, "ID2" => $this->dbIdP];
        if ($this->catProfile != \core\Federation::UNKNOWN_IDP || $this->dbIdP != \core\Federation::UNKNOWN_IDP) {
            unset($this->possibleFailureReasons[AbstractTest::INFRA_NONEXISTENTREALM]);
        }
        // do we operate on a non-ambiguous, fully configured CAT profile? Then
        // we run the more thorough check, otherwise the shallow one.
        $this->determineTestSuiteParameters();
        // let's do the least amount of testing needed:
        // - The CAT reachability test already covers ELTRs, IdP NRO level and the IdP itself.
        //   if the realm maps to a CAT IdP, we can run the more thorough tests; otherwise just
        //   the normal shallow ones
        // these are the normal "realm check" tests covering ETLR, LINK_NRO_IDP, NRO, IDP_RADIUS
        $this->CATInternalTests();
        // - if the test does NOT go through, we need to find out which of the three is guilty
        // - then, the international "via ETLR" check can be used to find out if the IdP alone
        //   is guilty. If that one fails, the direct monitoring of servers and ETLRs themselves
        //   closes the loop.
        // let's see if the ETLRs are up
        if (array_key_exists(AbstractTest::INFRA_ETLR, $this->possibleFailureReasons)) {
            $this->checkEtlrStatus();
        }

        // then let's check the IdP's FLR, if we know the IdP federation at all
        if ($this->idPFederation !== NULL) {
            if (array_key_exists(AbstractTest::INFRA_NRO_IDP, $this->possibleFailureReasons)) {
                // first the direct connectivity to the server
                $this->checkFlrServerStatus(AbstractTest::INFRA_NRO_IDP);
            }
            // now let's theck the link
            if (array_key_exists(AbstractTest::INFRA_LINK_ETLR_NRO_IDP, $this->possibleFailureReasons)) {
                $this->checkFedEtlrUplink(AbstractTest::INFRA_NRO_IDP);
            }
        }
        // now, if we know the country the user is currently in, let's see 
        // if the NRO SP-side is up
        if ($this->visitedFlr !== NULL) {
            $this->checkFlrServerStatus(AbstractTest::INFRA_NRO_SP);
            // and again its uplink to the ETLR
            $this->checkFedEtlrUplink(AbstractTest::INFRA_NRO_SP);
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
        $jsonSuspects = json_encode($this->possibleFailureReasons, JSON_PRETTY_PRINT);
        $jsonEvidence = json_encode($this->additionalFindings, JSON_PRETTY_PRINT);
        $this->databaseHandle->exec("UPDATE diagnosticrun SET realm = ?, visited_flr = ?, visited_hotspot = ?, suspects = ?, evidence = ? WHERE test_id = ?", "ssssss", $this->realm, $this->visitedFlr, $this->visitedHotspot, $jsonSuspects, $jsonEvidence, $this->testId);
        $_SESSION['TESTID'] = $this->testId;
        $_SESSION["SUSPECTS"] = $this->possibleFailureReasons;
        $_SESSION["EVIDENCE"] = $this->additionalFindings;
        return ["SUSPECTS" => $this->possibleFailureReasons, "EVIDENCE" => $this->additionalFindings];
    }
}