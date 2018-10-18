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
 * This class talks to end users, asking them annoying questions to get to the
 * ground of where exactly the problem lies.
 */
class Sociopath extends AbstractTest {

    private $qaArray;
    private $previousQuestions;
    private $genericVerdictTexts;

    /**
     * Initialise the class. Mostly used to get translated versions of various status messages.
     */
    public function __construct() {
        parent::__construct();
        $this->languageInstance->setTextDomain("diagnostics");
        $this->previousQuestions = $_SESSION['EVIDENCE']['QUESTIONSASKED'] ?? [];
        $noCanDo = _("There is nothing you can do to solve this problem yourself.");
        $noChange = _("Please be patient and try again at a later time. Do NOT change your device configuration.");
        $infraInformed = _("The infrastructure operators have automatically been informed and will investigate the issue as soon as possible.");
        $this->genericVerdictTexts = [
        AbstractTest::INFRA_DEVICE => _("The system has identified possible issues regarding your local device configuration."),
        AbstractTest::INFRA_ETLR => _("The system has identified issues with a central infrastructure element. $noCanDo $noChange $infraInformed"),
        AbstractTest::INFRA_NRO_IDP => _("The system has identified issues with a central infrastructure element. $noCanDo $noChange  $infraInformed"),
        AbstractTest::INFRA_NRO_SP => _("The system has identified issues with a central infrastructure element. $noCanDo $noChange $infraInformed"),
        AbstractTest::INFRA_LINK_ETLR_NRO_IDP => _("The system has identified a network connectivity issue within our core infrastructure. $noCanDo $noChange $infraInformed"),
        AbstractTest::INFRA_LINK_ETLR_NRO_SP => _("The system has identified a network connectivity issue within our core infrastructure. $noCanDo $noChange $infraInformed"),
        AbstractTest::INFRA_IDP_RADIUS => _("The system has identified a problem with the authentication infrastructure at your home organisation. $noCanDo $noChange Your Identity Provider has been informed and is looking into the problem."),
        AbstractTest::INFRA_IDP_AUTHBACKEND => _("The system has identified a problem with the authentication infrastructure at your home organisation. $noCanDo $noChange Your Identity Provider has been informed and is looking into the problem."),
        AbstractTest::INFRA_NONEXISTENTREALM => _("The system can not find any information at all about the Identity Provider you described. Probably, this is not a participating institution and the account you tried to use does not exist."),
        AbstractTest::INFRA_SP_80211 => _("There are likely some issues around the wireless part of the network you are trying to connect to. Wireless networks do not always behave deterministically and consistently. All users at a given location have to share the available bandwidth, and the physical environment (concrete walls, objects in the way, elevation differences) can have a significant impact on your connectivity experience."),
        AbstractTest::INFRA_SP_LAN => _("There are likely some issues around the local network infrastructure of the hotspot you are connecting to. $noCanDo $noChange The local hotspot provider has been informed and will look into the issue at their earliest convenience."),
        AbstractTest::INFRA_SP_RADIUS => _("There is an issue with the local authentication infrastructure of the hotspot you are connecting to. $noCanDo $noChange The local hotspot provider has been informed and will look into the issue at their earliest convenience."),
        ];
        // here is an array with yes/no answers per failure category, and the factors by which a Yes modifies the score; No's modify it by 1/factor;
        // the order in this array is important: lower numbered questions will be asked first. So make sure you have high-quality questions in the beginning.
        // to be clear: "Yes" answers are elsewhere in the class the TRUE case; No's are FALSE, a possible "Don't know or N/A" is NULL
        // VERDICTLECTURE is text which is displayed to the end user if his answer led to a HIGHER score in the process. We are storing the answers to determine this.
        $confAssistantText = _("You should use appropriate configuration assistants [MGW: see if the realm exists in CAT, then display link to config] or contact your Identity Provider [MGW: show contact info].");
        // let's start the numbering at 1
        $this->qaArray = [
            1 => ["AREA" => AbstractTest::INFRA_DEVICE,
                  "TXT" => _("Have you ever used the network succesfully, e.g. at your home institution without roaming?"),
                  "FACTOR" => 0.5,
                  "VERDICTLECTURE" => sprintf(_("If your device has never worked before with this setup, then very likely your device configuation is wrong. %s"), $confAssistantText)],
            2 => ["AREA" => AbstractTest::INFRA_DEVICE, 
                  "TXT" => _("Did the device previously work when roaming, i.e. at other hotspots away from your home institution?"), 
                  "FACTOR" => 0.33,
                  "VERDICTLECTURE" => sprintf(_("If roaming consistently does not work, then very likely your device configuration is wrong. Typical errors causing this symptom include: using a routing ('outer') username without the @realm.tld suffix - those potentially work at your home organisation, but can not be used when roaming. %s"),$confAssistantText)],
            3 => ["AREA" => AbstractTest::INFRA_DEVICE, 
                  "TXT" => _("Did you recently change the configuration on your device?"), 
                  "FACTOR" => 3,
                  "VERDICTLECTURE" => _("Accounts only need to be configured once, and can then be used anywhere on the planet without any changes. If you recently changed the configuration, that change may very well be at fault. You should never change your network configuration unless explicitly instructed so by your Identity Provider; even in the case of temporary login issues.")],
            4 => ["AREA" => AbstractTest::INFRA_DEVICE, 
                  "TXT" => _("If you use more than one device: do your other devices still work?"),
                  "VERDICTLECTURE" => _("If all devices stopped working simultaneously, there may be a problem with your account as such. Maybe your account expired, or you were forced to change the password? These questions are best answered by your Identity Provider [MGW: display contact info]"),
                  "FACTOR" => 0.33],
            5 => ["AREA" => AbstractTest::INFRA_SP_80211, 
                  "TXT" => _("Is the place you are currently at heavily crowded, or is a network-intensive workload going on?"), 
                  "FACTOR" => 3,
                  "VERDICTLECTURE" => _("The network is likely overloaded at this location and point in time. You may have to wait until later before you get a better connectivity. If you think the network should be reinforced for more capacity at this place, you should inform the hotspot provider. [MGW: add contact info]")],            
            6 => ["AREA" => AbstractTest::INFRA_SP_80211, 
                  "TXT" => _("Does the connection get better when you move around?"), 
                  "FACTOR" => 3,
                  "VERDICTLECTURE" => _("You should move to a different location to achieve better network coverage and service. If you think the exact spot you are at deserves better coverage, you should inform the hotspot provider. [MGW: add contact info]")],
            
            7 => ["AREA" => AbstractTest::INFRA_SP_LAN, 
                  "TXT" => _("Do you see errors stating something similar to 'Unable to get IP address'?"), 
                  "FACTOR" => 3,
                  "VERDICTLECTURE" => _("The evidence at hand suggests that there may be an infrastructure problem at this particular hotspot provider. There is nothing you can do to solve this problem locally. Please be patient and try again at a later time.")],
        ];
    }

    /**
     * re-evaluates the occurence factor of the SUSPECTS, taking the answer to the given question into account
     * 
     * @param int $questionNumber
     * @param bool|NULL $answer TRUE if the answer was "Yes", FALSE if "No", NULL is "Dont know / N/A"
     */
    public function revaluate($questionNumber, $answer) {
        if ($questionNumber == "") {
            throw new Exception("We really need a question number!");
        }
        $questionDetails = $this->qaArray[$questionNumber];
        if ($answer === TRUE) {
            $this->possibleFailureReasons[$questionDetails['AREA']] = $this->possibleFailureReasons[$questionDetails['AREA']] * $questionDetails["FACTOR"];
            $this->loggerInstance->debug(3,"Adjusting ".$questionDetails['AREA']." by ".$questionDetails["FACTOR"]."\n");
            $factor = $questionDetails["FACTOR"];
        } elseif ($answer === FALSE) {
            $this->possibleFailureReasons[$questionDetails['AREA']] = $this->possibleFailureReasons[$questionDetails['AREA']] / $questionDetails["FACTOR"];
            $this->loggerInstance->debug(3,"Adjusting ".$questionDetails['AREA']." by 1/".$questionDetails["FACTOR"]."\n");
            $factor = 1/$questionDetails["FACTOR"];
        } else {
            $factor = 1;
        }
        $this->previousQuestions[$questionNumber] = $factor;
        $this->normaliseResultSet();
        $this->additionalFindings["QUESTIONSASKED"] = $this->previousQuestions;
        $_SESSION["SUSPECTS"] = $this->possibleFailureReasons;
        $_SESSION["EVIDENCE"] = $this->additionalFindings;
        $this->loggerInstance->debug(3,$_SESSION['SUSPECTS']);
        $this->loggerInstance->debug(3,$_SESSION['EVIDENCE']);
    }
    
    /**
     * takes a look at the current occurence factors, and which questions have
     * already been asked, then tells the caller which question to ask next.
     * @return string JSON encoded array with info on the next available question
     */
    public function questionOracle() {
        reset($this->possibleFailureReasons);
        $highestCategory = key($this->possibleFailureReasons);
        next($this->possibleFailureReasons);
        $nextCategory = key($this->possibleFailureReasons);
        if ($this->possibleFailureReasons[$highestCategory] != $this->possibleFailureReasons[$nextCategory]) {
            $nextCategory = $highestCategory;
        }
        // if both are identical, take any of the questions in the pool of both
        foreach ($this->qaArray as $questionNumber => $questionDetails) {
            // if we find a question we didn't ask before AND it is related to our currently high-scoring problem area, ask it
            if (!array_key_exists($questionNumber, $this->previousQuestions) && ( $questionDetails["AREA"] == $highestCategory || $questionDetails["AREA"] == $nextCategory) ) {
                return json_encode(["NEXTEXISTS" => TRUE, "NUMBER" => $questionNumber, "TEXT" => $questionDetails["TXT"]]);
            }
        }
        // if we got here, we ran out of questions. Return that fact
        return json_encode(["NEXTEXISTS" => FALSE]);
    }
    
    /**
     * returns the current state of play regarding SUSPECTS and related EVIDENCE
     * @return string JSON encoded array with all the info we have
     */
    public function getCurrentGuessState() {
        return json_encode([ "SUSPECTS" => $this->possibleFailureReasons, "EVIDENCE" => $this->additionalFindings ]);
    }
    
    /**
     * constructs the final diagnosis result text to show to the user
     * @param string $area
     * @return string
     */
    public function verdictText($area) {
        $text = $this->genericVerdictTexts[$area];
        foreach ($this->previousQuestions as $number => $factor) {
            if ($this->qaArray[$number]["AREA"] == $area && $factor > 1) {
                $text .= "\n\n".$this->qaArray[$number]["VERDICTLECTURE"];
            }
        }
        return $text;
    }
}
