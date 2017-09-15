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
class Telepath {

    // list of elements of the infrastructure which could be broken
    // along with their occurence probability (guesswork!)
    const INFRA_ETLR = "INFRA_ETLR";
    const INFRA_LINK_ETLR_NRO_IdP = "INFRA_LINK_ETLR_NRO_IdP";
    const INFRA_LINK_ETLR_NRO_SP = "INFRA_LINK_ETLR_NRO_SP";
    const INFRA_NRO_SP = "INFRA_NRO_SP";
    const INFRA_NRO_IdP = "INFRA_NRO_IdP";
    const INFRA_SP_RADIUS = "INFRA_SP_RADIUS";
    const INFRA_IdP_RADIUS = "INFRA_IdP_RADIUS";
    const INFRA_IdP_AUTHBACKEND = "INFRA_IdP_AUTHBACKEND";
    const INFRA_SP_80211 = "INFRA_SP_80211";
    const INFRA_DEVICE = "INFRA_DEVICE";
    const INFRA_NONEXISTENTREALM = "INFRA_NONEXISTENTREALM";

    private $probabilities;
    private $possibleFailureReasons;
    private $additionalFindings;
    private $realm;
    private $visitedFlr;
    private $visitedHotspot;
    private $catIdP;
    private $dbIdP;
    private $idPFederation;
    private $logHandle;

    public function __construct(string $realm, $visitedFlr = NULL, $visitedHotspot = NULL) {
        // everyone could be guilty
        $this->possibleFailureReasons = [
            Telepath::INFRA_ETLR,
            Telepath::INFRA_LINK_ETLR_NRO_IdP,
            Telepath::INFRA_LINK_ETLR_NRO_SP,
            Telepath::INFRA_NRO_SP,
            Telepath::INFRA_NRO_IdP,
            Telepath::INFRA_SP_RADIUS,
            Telepath::INFRA_IdP_RADIUS,
            Telepath::INFRA_IdP_AUTHBACKEND,
            Telepath::INFRA_SP_80211,
            Telepath::INFRA_DEVICE,
            Telepath::INFRA_NONEXISTENTREALM,
        ];

        // these are NOT constant - in the course of checks, we may find a "smoking gun" and elevate the probability
        // in the end, use the numbers of those elements which were not deterministically excluded and normalise to 1
        // to get a percentage to report on.
        $this->probabilities = [
            Telepath::INFRA_ETLR => 0.01,
            Telepath::INFRA_LINK_ETLR_NRO_IdP => 0.01,
            Telepath::INFRA_LINK_ETLR_NRO_SP => 0.01,
            Telepath::INFRA_NRO_SP => 0.02,
            Telepath::INFRA_NRO_IdP => 0.02,
            Telepath::INFRA_SP_RADIUS => 0.04,
            Telepath::INFRA_IdP_RADIUS => 0.04,
            Telepath::INFRA_SP_80211 => 0.05,
            Telepath::INFRA_IdP_AUTHBACKEND => 0.02,
            Telepath::INFRA_DEVICE => 0.3,
            Telepath::INFRA_NONEXISTENTREALM => 0.7, /* if the eduroam DB were fully and consistently populated, this would have 1.0 - if we don't know anything about the realm, then this is not a valid eduroam realm. But reality says we don't have complete info in the DBs. */
        ];
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
        $this->logHandle = new \core\common\Logging();
        $this->logHandle->debug(4, "XYZ: IdP-side NRO is " . $this->idPFederation . "\n");
    }

    const STATUS_GOOD = 0;
    const STATUS_PARTIAL = -1;
    const STATUS_DOWN = -2;

    private function checkEtlrStatus() {
        // TODO: this is a stub, need eduroam OT API to query the current server status
        // APIQueryETLR();
        return Telepath::STATUS_GOOD;
    }

    private function checkFedEtlrUplink($flr) {
        // TODO: this is a stub, need eduroam OT API to query the current server status
        // APIQueryNROviaETLR($flr);
        return Telepath::STATUS_GOOD;
    }

    private function checkFlrServerStatus($flr) {
        // TODO: this is a stub, need eduroam OT API to query the current server status
        // APIQueryNRODirect($flr);
        return Telepath::STATUS_GOOD;
    }

    private function checkNROFlow($visitedFlr, $homeFlr) {
        // TODO: this is a stub, need eduroam OT API to query the current server status
        // APIQueryNRODirect($visitedFlr, $homeFlr);
        return Telepath::STATUS_GOOD;
    }

    public function magic() {

        // simple things first: do we know anything about the realm, either
        // because it's a CAT participant or because it's in the eduroam DB?
        // if so, we can exclude the INFRA_NONEXISTENTREALM cause

        $this->additionalFindings[Telepath::INFRA_NONEXISTENTREALM][] = ["ID1" => $this->catIdP, "ID2" => $this->dbIdP];

        if ($this->catIdP != \core\Federation::UNKNOWN_IDP || $this->dbIdP != \core\Federation::UNKNOWN_IDP) {
            $this->possibleFailureReasons = array_diff($this->possibleFailureReasons, [Telepath::INFRA_NONEXISTENTREALM]);
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
                $this->additionalFindings[Telepath::INFRA_IdP_RADIUS][] = ["Profile" => $bestProfile->identifier];
                $testsuite = new RADIUSTests($this->realm, $bestProfile->getRealmCheckOuterUsername(), $bestProfile->getEapMethodsinOrderOfPreference(1), $bestProfile->getCollapsedAttributes()['eap:server_name'], $bestProfile->getCollapsedAttributes()["eap:ca_file"]);
            } else {
                $this->additionalFindings[Telepath::INFRA_IdP_RADIUS][] = ["Profile" => "UNCONCLUSIVE"];
                $testsuite = new RADIUSTests($this->realm, "anonymous@" . $this->realm);
            }
        } else {
            $testsuite = new RADIUSTests($this->realm, "anonymous@" . $this->realm);
        }

        // we are expecting to get a REJECT from all runs, because that means the packet got through to the IdP.
        // (the ETLR sometimes does a "Reject instead of Ignore" but that is filtered out and changed into a timeout
        // by the test suite automatically, so it does not disturb the measurement)
        // If that's true, we can exclude two sources of problems (both proxy levels). Hooray!
        $allAreConversationReject = TRUE;
        $atLeastOneConversationReject = FALSE;

        foreach (CONFIG_DIAGNOSTICS['RADIUSTESTS']['UDP-hosts'] as $probeindex => $probe) {
            $reachCheck = $testsuite->UDP_reachability($probeindex);
            if ($reachCheck != RADIUSTests::RETVAL_CONVERSATION_REJECT) {
                $allAreConversationReject = FALSE;
            } else {
                $atLeastOneConversationReject = TRUE;
            }

            $this->additionalFindings[Telepath::INFRA_ETLR][] = ["DETAIL" => $testsuite->consolidateUdpResult($probeindex)];
            $this->additionalFindings[Telepath::INFRA_NRO_IdP][] = ["DETAIL" => $testsuite->consolidateUdpResult($probeindex)];
            $this->additionalFindings[Telepath::INFRA_IdP_RADIUS][] = ["DETAIL" => $testsuite->consolidateUdpResult($probeindex)];
        }

        if ($allAreConversationReject) {
            $this->additionalFindings[Telepath::INFRA_ETLR][] = ["CONNCHECK" => RADIUSTests::RETVAL_CONVERSATION_REJECT];
            $this->additionalFindings[Telepath::INFRA_NRO_IdP][] = ["CONNCHECK" => RADIUSTests::RETVAL_CONVERSATION_REJECT];
            $this->additionalFindings[Telepath::INFRA_IdP_RADIUS][] = ["CONNCHECK" => RADIUSTests::RETVAL_CONVERSATION_REJECT];
            $this->additionalFindings[Telepath::INFRA_LINK_ETLR_NRO_IdP][] = ["LINKCHECK" => RADIUSTests::L_OK];
            // we have actually reached an IdP, so all links are good, and the
            // realm is routable in eduroam. So even if it exists in neither DB
            // we can exclude the NONEXISTENTREALM case
            $this->possibleFailureReasons = array_diff($this->possibleFailureReasons, [Telepath::INFRA_ETLR, Telepath::INFRA_NRO_IdP, Telepath::INFRA_LINK_ETLR_NRO_IdP]);
        };

        if ($atLeastOneConversationReject) {
            // at least we can be sure it exists
            $this->possibleFailureReasons = array_diff($this->possibleFailureReasons, [Telepath::INFRA_NONEXISTENTREALM]);
            // It could still be an IdP RADIUS problem in that some cert oddities 
            // in combination with the device lead to a broken auth
            // if there is nothing beyond the "REMARK" level, then it's not an IdP problem
            // otherwise, add the corresponding warnings and errors to $additionalFindings
            switch ($this->additionalFindings[Telepath::INFRA_IdP_RADIUS][0]['DETAIL']['level']) {
                case RADIUSTests::L_OK:
                case RADIUSTests::L_REMARK:
                    // both are fine - the IdP is working and the user problem
                    // is not on the IdP RADIUS level
                    $this->additionalFindings[Telepath::INFRA_IdP_RADIUS][] = ["ODDITYLEVEL" => $this->additionalFindings[Telepath::INFRA_IdP_RADIUS][0]['DETAIL']['level']];
                    $this->possibleFailureReasons = array_diff($this->possibleFailureReasons, [Telepath::INFRA_IdP_RADIUS]);
                    break;
                case RADIUSTests::L_WARN:
                    $this->additionalFindings[Telepath::INFRA_IdP_RADIUS][] = ["ODDITYLEVEL" => RADIUSTests::L_WARN];
                    $this->probabilities[Telepath::INFRA_IdP_RADIUS] = 0.3; // possibly we found the culprit - if RADIUS server is misconfigured AND user is on a device which reacts picky about exactly this oddity.
                    break;
                case RADIUSTests::L_ERROR:
                    $this->additionalFindings[Telepath::INFRA_IdP_RADIUS][] = ["ODDITYLEVEL" => RADIUSTests::L_ERROR];
                    $this->probabilities[Telepath::INFRA_IdP_RADIUS] = 0.8; // errors are never good, so we can be reasonably sure we've hit the spot!
            }
        }

        // - if the test does NOT go through, we need to find out which of the three is guilty
        // - then, the international "via ETLR" check can be used to find out if the IdP alone
        //   is guilty. If that one fails, the direct monitoring of servers and ETLRs themselves
        //   closes the loop.
        // let's see if the ETLRs are up

        $etlrStatus = $this->checkEtlrStatus();
        switch ($etlrStatus) {
            case Telepath::STATUS_GOOD:
                $this->additionalFindings[Telepath::INFRA_ETLR][] = ["STATUS" => Telepath::STATUS_GOOD];
                $this->possibleFailureReasons = array_diff($this->possibleFailureReasons, [Telepath::INFRA_ETLR]);
                break;
            case Telepath::STATUS_PARTIAL:
                // one of the ETLRs is down? This probably doesn't impact the user unless he's unlucky and has his session fall into failover.
                // keep ETLR as a possible problem with original probability
                $this->additionalFindings[Telepath::INFRA_ETLR][] = ["STATUS" => Telepath::STATUS_PARTIAL];
                break;
            case Telepath::STATUS_DOWN:
                $this->additionalFindings[Telepath::INFRA_ETLR][] = ["STATUS" => Telepath::STATUS_DOWN];
                // Oh! Well if it is not international roaming, that still doesn't have an effect /in this case/. 
                if ($this->idPFederation == $this->visitedFlr) {
                    $this->possibleFailureReasons = array_diff($this->possibleFailureReasons, [Telepath::INFRA_ETLR]);
                    break;
                }
                // But it is about int'l roaming, and we are spot on here.
                // Raise probability by much (even monitoring is sometimes wrong, or a few minutes behind reality)
                $this->probabilities[Telepath::INFRA_ETLR] = 0.95;
        }
        // next up: if the ETLR was okay, check the the FLR and its ETLR 
        // uplink (if we know which federation we are talking about)

        if (!in_array(Telepath::INFRA_ETLR, $this->possibleFailureReasons) && $this->idPFederation != NULL) {
            // first the direct connectivity to the server
            $flrServerStatus = $this->checkFlrServerStatus($this->idPFederation);
            switch ($flrServerStatus) {
                case Telepath::STATUS_GOOD:
                    $this->additionalFindings[Telepath::INFRA_NRO_IdP][] = ["STATUS" => Telepath::STATUS_GOOD];
                    $this->possibleFailureReasons = array_diff($this->possibleFailureReasons, [Telepath::INFRA_NRO_IdP]);
                    break;
                case Telepath::STATUS_PARTIAL:
                    // a subset of the FLRs is down? This probably doesn't impact the user unless he's unlucky and has his session fall into failover.
                    // keep FLR as a possible problem with original probability
                    $this->additionalFindings[Telepath::INFRA_NRO_IdP][] = ["STATUS" => Telepath::STATUS_PARTIAL];
                    break;
                case Telepath::STATUS_DOWN:
                    $this->additionalFindings[Telepath::INFRA_NRO_IdP][] = ["STATUS" => Telepath::STATUS_DOWN];
                    // Raise probability by much (even monitoring is sometimes wrong, or a few minutes behind reality)
                    $this->probabilities[Telepath::INFRA_NRO_IdP] = 0.95;
            }
            // then its uplink 
            $flrUplinkStatus = $this->checkFedEtlrUplink($this->idPFederation);
            switch ($flrUplinkStatus) {
                case Telepath::STATUS_GOOD:
                    $this->additionalFindings[Telepath::INFRA_NRO_IdP][] = ["STATUS" => Telepath::STATUS_GOOD];
                    $this->possibleFailureReasons = array_diff($this->possibleFailureReasons, [Telepath::INFRA_NRO_IdP, Telepath::INFRA_LINK_ETLR_NRO_IdP]);
                    break;
                case Telepath::STATUS_PARTIAL:
                    // a subset of the FLRs is down? This probably doesn't impact the user unless he's unlucky and has his session fall into failover.
                    // keep FLR as a possible problem with original probability
                    $this->additionalFindings[Telepath::INFRA_NRO_IdP][] = ["STATUS" => Telepath::STATUS_PARTIAL];
                    break;
                case Telepath::STATUS_DOWN:
                    $this->additionalFindings[Telepath::INFRA_NRO_IdP][] = ["STATUS" => Telepath::STATUS_DOWN];
                    // Raise probability by much (even monitoring is sometimes wrong, or a few minutes behind reality)
                    // if earlier test found the server itself to be the problem, keep it, otherwise put the blame on the link
                    if ($this->probabilities[Telepath::INFRA_NRO_IdP] != 0.95) {
                        $this->probabilities[Telepath::INFRA_LINK_ETLR_NRO_IdP] = 0.95;
                    }
            }
        }

        // now, if we know the country the user is currently in, let's see 
        // if the NRO SP-side is up
        if ($this->visitedFlr !== NULL) {
            $visitedFlrStatus = $this->checkFlrServerStatus($this->visitedFlr);
            // direct query to server
            switch ($visitedFlrStatus) {
                case Telepath::STATUS_GOOD:
                    $this->additionalFindings[Telepath::INFRA_NRO_SP][] = ["STATUS" => Telepath::STATUS_GOOD];
                    $this->possibleFailureReasons = array_diff($this->possibleFailureReasons, [Telepath::INFRA_NRO_SP]);
                    break;
                case Telepath::STATUS_PARTIAL:
                    // a subset of the FLRs is down? This probably doesn't impact the user unless he's unlucky and has his session fall into failover.
                    // keep FLR as a possible problem with original probability
                    $this->additionalFindings[Telepath::INFRA_NRO_SP][] = ["STATUS" => Telepath::STATUS_PARTIAL];
                    break;
                case Telepath::STATUS_DOWN:
                    $this->additionalFindings[Telepath::INFRA_NRO_SP][] = ["STATUS" => Telepath::STATUS_DOWN];
                    // Raise probability by much (even monitoring is sometimes wrong, or a few minutes behind reality)
                    $this->probabilities[Telepath::INFRA_NRO_SP] = 0.95;
            }
            // and again its uplink to the ETLR
            $visitedFlrUplinkStatus = $this->checkFedEtlrUplink($this->visitedFlr);
            switch ($visitedFlrUplinkStatus) {
                case Telepath::STATUS_GOOD:
                    $this->additionalFindings[Telepath::INFRA_NRO_SP][] = ["STATUS" => Telepath::STATUS_GOOD];
                    $this->possibleFailureReasons = array_diff($this->possibleFailureReasons, [Telepath::INFRA_NRO_SP, Telepath::INFRA_LINK_ETLR_NRO_SP]);
                    break;
                case Telepath::STATUS_PARTIAL:
                    // a subset of the FLRs is down? This probably doesn't impact the user unless he's unlucky and has his session fall into failover.
                    // keep FLR as a possible problem with original probability
                    $this->additionalFindings[Telepath::INFRA_NRO_SP][] = ["STATUS" => Telepath::STATUS_PARTIAL];
                    break;
                case Telepath::STATUS_DOWN:
                    $this->additionalFindings[Telepath::INFRA_NRO_SP][] = ["STATUS" => Telepath::STATUS_DOWN];
                    // Raise probability by much (even monitoring is sometimes wrong, or a few minutes behind reality)
                    // if earlier test found the server itself to be the problem, keep it, otherwise put the blame on the link
                    if ($this->probabilities[Telepath::INFRA_NRO_SP] != 0.95) {
                        $this->probabilities[Telepath::INFRA_LINK_ETLR_NRO_SP] = 0.95;
                    }
            }
            // the last thing we can do (but it's a bit redundant): check the country-to-country link
            // it's only needed if all three and their links are up, but we want to exclude funny routing blacklists 
            // which occur only in the *combination* of source and dest
            // if there is an issue at that point, blame the SP: once a request would have reached the ETLRs, things would be all good. So they apparently didn't.
            if (!in_array(Telepath::INFRA_ETLR, $this->possibleFailureReasons) &&
                    !in_array(Telepath::INFRA_LINK_ETLR_NRO_IdP, $this->possibleFailureReasons) &&
                    !in_array(Telepath::INFRA_NRO_IdP, $this->possibleFailureReasons) &&
                    !in_array(Telepath::INFRA_LINK_ETLR_NRO_SP, $this->possibleFailureReasons) &&
                    !in_array(Telepath::INFRA_NRO_SP, $this->possibleFailureReasons)
            ) {
                $countryToCountryStatus = $this->checkNROFlow($this->visitedFlr, $this->idPFederation);
                switch ($countryToCountryStatus) {
                    case Telepath::STATUS_GOOD:
                        // all routes work
                        break;
                    case Telepath::STATUS_PARTIAL:
                    // at least one, or even all have a routing problem
                    case Telepath::STATUS_DOWN:
                        // that's rather telling.
                        $this->additionalFindings[Telepath::INFRA_NRO_SP][] = ["C2CLINK" => Telepath::STATUS_DOWN];
                        $this->probabilities[Telepath::INFRA_NRO_SP] = 0.95;
                }
            }
        }

        // done. return both the list of possible problem sources with their probabilities, and the additional findings we collected along the way.
        $totalScores = 0.;
        foreach ($this->possibleFailureReasons as $oneReason) {
            $totalScores += $this->probabilities[$oneReason];
        }
        $probArray = [];
        foreach ($this->possibleFailureReasons as $oneReason) {
            $probArray[$oneReason] = $this->probabilities[$oneReason] / $totalScores;
        }
        array_multisort($probArray, SORT_DESC, SORT_NUMERIC, $this->possibleFailureReasons);

        return ["SUSPECTS" => $this->possibleFailureReasons, "PROBABILITIES" => $probArray, "EVIDENCE" => $this->additionalFindings];
    }

}
