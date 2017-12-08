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
 */
class Sociopath extends AbstractTest {

    private $qaArray;
    private $previousQuestions;

    /**
     * 
     * @param array $previousGuess list of suspect elements and their current occurence factor (entries of sorts [ INFRA_DEVICE => 0.6, ... ]
     * @param array $alreadyAsked questions which were already asked before
     *
     */
    public function __construct($previousGuess, $alreadyAsked = []) {
        // here is an array with yes/no answers per failure category, and the factors by which a Yes modifies the score; No's modify it by 1/factor;
        // the order in this array is important: lower numbered questions will be asked first. So make sure you have high-quality questions in the beginning.
        // to be clear: "Yes" answers are elsewhere in the class the TRUE case; No's are FALSE, a possible "Don't know or N/A" is NULL
        $this->qaArray = [
            0 => ["AREA" => Telepath::INFRA_DEVICE, "TXT" => _("Did the device previously work at other hotspots?"), "FACTOR" => 0.33],
            1 => ["AREA" => Telepath::INFRA_DEVICE, "TXT" => _("Did you recently change the configuration on your device?"), "FACTOR" => 3],
            2 => ["AREA" => Telepath::INFRA_DEVICE, "TXT" => _("Do your other devices still work?"), "FACTOR" => 0.33],
            3 => ["AREA" => Telepath::INFRA_SP_80211, "TXT" => _("Does the connection get better when you move around?"), "FACTOR" => 3],
            4 => ["AREA" => Telepath::INFRA_SP_LAN, "TXT" => _("Do you see errors stating something similar to 'Unable to get IP address'?"), "FACTOR" => 3],
        ];
        // stow away the current state of guesswork
        $this->previousQuestions = $alreadyAsked;
        $this->possibleFailureReasons = $previousGuess;
    }

    public function revaluate($questionNumber, $answer) {
        $questionDetails = $this->qaArray[$questionNumber];
        if ($answer === TRUE) {
            $this->possibleFailureReasons[$questionDetails['AREA']] = $this->possibleFailureReasons[$questionDetails['AREA']] * $questionDetails["FACTOR"];
        }
        if ($answer === FALSE) {
            $this->possibleFailureReasons[$questionDetails['AREA']] = $this->possibleFailureReasons[$questionDetails['AREA']] / $questionDetails["FACTOR"];
        }
        $this->normaliseResultSet();
        $this->previousQuestions[] = $questionNumber;
        
    }
    
    public function questionOracle() {
        reset($this->possibleFailureReasons);
        $highestCategory = key($this->possibleFailureReasons);
        $nextCategory = key(next($this->possibleFailureReasons));
        if ($this->possibleFailureReasons[$highestCategory] != $this->possibleFailureReasons[$nextCategory]) {
            $nextCategory = $highestCategory;
        }
        // if both are identical, take any of the questions in the pool of both
        foreach ($this->qaArray as $questionNumber => $questionDetails) {
            // if we find a question we didn't ask before AND it is related to our currently high-scoring problem area, ask it
            if (!in_array($questionNumber, $this->previousQuestions) && ( $questionDetails["AREA"] == $highestCategory || $questionDetails["AREA"] == $nextCategory) ) {
                return json_encode(["NEXTEXISTS" => TRUE, "NUMBER" => $questionNumber, "TEXT" => $questionDetails["TXT"]]);
            }
        }
        // if we got here, we ran out of questions. Return that fact
        return json_encode(["NEXTEXISTS" => FALSE]);
    }
    
    public function getCurrentGuessState() {
        return json_encode([ "SUSPECTS" => $this->possibleFailureReasons, "PREVIOUSQUESTIONS" => $this->previousQuestions ]);
    }
    
}
