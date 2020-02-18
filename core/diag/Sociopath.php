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
 * This class talks to end users, asking them annoying questions to get to the
 * ground of where exactly the problem lies.
 */
class Sociopath extends AbstractTest
{

    /**
     * list of questions to ask and the answer weights
     * 
     * @var array
     */
    private $qaArray;

    /**
     * list of questions that were already asked, along with the answers we got
     * @var array
     */
    private $previousQuestions;

    /**
     * things to say once we have enough certainty that this is the root cause
     * 
     * @var array
     */
    private $genericVerdictTexts;

    /**
     * Initialise the class. Mostly used to get translated versions of various status messages.
     */
    public function __construct()
    {
        parent::__construct();
        \core\common\Entity::intoThePotatoes();
        $this->previousQuestions = $_SESSION['EVIDENCE']['QUESTIONSASKED'] ?? [];
        $this->testId = $_SESSION['TESTID'];
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
                "FACTOR_YES" => 0.8, // that's good, but it doesn't mean strikingly much
                "FACTOR_NO" => 2, // that's bad, and points strongly to a config on this end
                "VERDICTLECTURE" => sprintf(_("If your device has never worked before with this setup, then very likely your device configuation is wrong. %s"), $confAssistantText)],
            2 => ["AREA" => AbstractTest::INFRA_DEVICE,
                "TXT" => _("Did the device previously work when roaming, i.e. at other hotspots away from your home institution?"),
                "FACTOR_YES" => 0.6, // that's good, and somewhat encouraging
                "FACTOR_NO" => 3, // that is almost a smoking gun
                "VERDICTLECTURE" => sprintf(_("If roaming consistently does not work, then very likely your device configuration is wrong. Typical errors causing this symptom include: using a routing ('outer') username without the @realm.tld suffix - those potentially work at your home organisation, but can not be used when roaming. %s"), $confAssistantText)],
            3 => ["AREA" => AbstractTest::INFRA_DEVICE,
                "TXT" => _("Did you recently change the configuration on your device?"),
                "FACTOR_YES" => 3, // that is almost a smoking gun
                "FACTOR_NO" => 0.6, // encouraging
                "VERDICTLECTURE" => _("Accounts only need to be configured once, and can then be used anywhere on the planet without any changes. If you recently changed the configuration, that change may very well be at fault. You should never change your network configuration unless explicitly instructed so by your Identity Provider; even in the case of temporary login issues.")],
            4 => ["AREA" => AbstractTest::INFRA_DEVICE,
                "TXT" => _("Did you recently change your password?"),
                "FACTOR_YES" => 1.5, // that doesn't mean it is the source of the problem, but it /might/ be that the user forgot to provide the new password
                "FACTOR_NO" => 0.6, // encouraging
                "VERDICTLECTURE" => _("When you change your password, you also need to supply the new password in the device configuration.")],
            5 => ["AREA" => AbstractTest::INFRA_DEVICE,
                "TXT" => _("If you use more than one device: do your other devices still work?"),
                "FACTOR_YES" => 0.33, // seems that all is okay with the account as such
                "FACTOR_NO" => 3, // now that is suspicious indeed
                "VERDICTLECTURE" => _("If all devices stopped working simultaneously, there may be a problem with your account as such. Maybe your account expired, or you were forced to change the password? These questions are best answered by your Identity Provider [MGW: display contact info]"),],
            6 => ["AREA" => AbstractTest::INFRA_SP_80211,
                "TXT" => _("Is the place you are currently at heavily crowded, or is a network-intensive workload going on?"),
                "FACTOR_YES" => 3,
                "FACTOR_NO" => 0.33,
                "VERDICTLECTURE" => _("The network is likely overloaded at this location and point in time. You may have to wait until later before you get a better connectivity. If you think the network should be reinforced for more capacity at this place, you should inform the hotspot provider. [MGW: add contact info]")],
            7 => ["AREA" => AbstractTest::INFRA_SP_80211,
                "TXT" => _("Does the connection get better when you move around?"),
                "FACTOR_YES" => 3,
                "FACTOR_NO" => 0.33,
                "VERDICTLECTURE" => _("You should move to a different location to achieve better network coverage and service. If you think the exact spot you are at deserves better coverage, you should inform the hotspot provider. [MGW: add contact info]")],
            8 => ["AREA" => AbstractTest::INFRA_SP_LAN,
                "TXT" => _("Do you see errors stating something similar to 'Unable to get IP address'?"),
                "FACTOR_YES" => 5, // gotcha
                "FACTOR_YES" => 0.5, // the user saying no is not conclusive; maybe the device isn't that verbose or he's not looking at the right spot
                "VERDICTLECTURE" => _("The evidence at hand suggests that there may be an infrastructure problem at this particular hotspot provider. There is nothing you can do to solve this problem locally. Please be patient and try again at a later time.")],
        ];
        \core\common\Entity::outOfThePotatoes();
    }

    /**
     * re-evaluates the occurence factor of the SUSPECTS, taking the answer to the given question into account
     * 
     * @param int       $questionNumber number of the question that was answered
     * @param bool|NULL $answer         TRUE if the answer was "Yes", FALSE if "No", NULL is "Dont know / N/A"
     * @return void
     * @throws Exception
     */
    public function revaluate($questionNumber, $answer)
    {
        if ($questionNumber == "") {
            throw new Exception("We really need a question number!");
        }
        $questionDetails = $this->qaArray[$questionNumber];
        if ($answer === TRUE) {
            $this->possibleFailureReasons[$questionDetails['AREA']] = $this->possibleFailureReasons[$questionDetails['AREA']] * $questionDetails["FACTOR_YES"];
            $this->loggerInstance->debug(3, "Adjusting " . $questionDetails['AREA'] . " by " . $questionDetails["FACTOR_YES"] . "\n");
            $factor = $questionDetails["FACTOR_YES"];
        } elseif ($answer === FALSE) {
            $this->possibleFailureReasons[$questionDetails['AREA']] = $this->possibleFailureReasons[$questionDetails['AREA']] * $questionDetails["FACTOR_NO"];
            $this->loggerInstance->debug(3, "Adjusting " . $questionDetails['AREA'] . " by " . $questionDetails["FACTOR_NO"] . "\n");
            $factor = $questionDetails["FACTOR_NO"];
        } else {
            $factor = 1;
        }
        $this->previousQuestions[$questionNumber] = $factor;
        $this->normaliseResultSet();
        $jsonQuestions = json_encode($this->previousQuestions, JSON_PRETTY_PRINT);
        $jsonEvidence = json_encode($this->additionalFindings, JSON_PRETTY_PRINT);
        $jsonSuspects = json_encode($this->possibleFailureReasons, JSON_PRETTY_PRINT);
        $this->databaseHandle->exec("UPDATE diagnosticrun SET questionsasked = ?, suspects = ?, evidence = ? WHERE test_id = ?", "ssss", $jsonQuestions, $jsonSuspects, $jsonEvidence, $this->testId);
        $this->additionalFindings["QUESTIONSASKED"] = $this->previousQuestions;
        $_SESSION["SUSPECTS"] = $this->possibleFailureReasons;
        $_SESSION["EVIDENCE"] = $this->additionalFindings;
        $this->loggerInstance->debug(3, $_SESSION['SUSPECTS']);
        $this->loggerInstance->debug(3, $_SESSION['EVIDENCE']);
    }

    /**
     * takes a look at the current occurence factors, and which questions have
     * already been asked, then tells the caller which question to ask next.
     * @return string JSON encoded array with info on the next available question
     */
    public function questionOracle()
    {
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
            if (!array_key_exists($questionNumber, $this->previousQuestions) && ( $questionDetails["AREA"] == $highestCategory || $questionDetails["AREA"] == $nextCategory)) {
                return json_encode(["NEXTEXISTS" => TRUE, "NUMBER" => $questionNumber, "TEXT" => $questionDetails["TXT"]]);
            }
        }
        // if we got here, we ran out of questions. Return that fact
        $this->databaseHandle->exec("UPDATE diagnosticrun SET concluded = 1 WHERE test_id = ?", "s", $this->testId);
        return json_encode(["NEXTEXISTS" => FALSE]);
    }

    /**
     * returns the current state of play regarding SUSPECTS and related EVIDENCE
     * @return string JSON encoded array with all the info we have
     */
    public function getCurrentGuessState()
    {
        return json_encode(["SUSPECTS" => $this->possibleFailureReasons, "EVIDENCE" => $this->additionalFindings]);
    }

    /**
     * constructs the final diagnosis result text to show to the user
     * @param string $area retrieve lecture texts for this area
     * @return string
     */
    public function verdictText($area)
    {
        $text = $this->genericVerdictTexts[$area];
        foreach ($this->previousQuestions as $number => $factor) {
            if ($this->qaArray[$number]["AREA"] == $area && $factor > 1) {
                $text .= "\n\n" . $this->qaArray[$number]["VERDICTLECTURE"];
            }
        }
        return $text;
    }
}