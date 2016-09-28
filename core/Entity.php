<?php

/* * ********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

/**
 * This file contains Federation, IdP and Profile classes.
 * These should be split into separate files later.
 *
 * @package Developer
 */
/**
 * 
 */
require_once("Logging.php");
require_once("Language.php");

/**
 * This class represents an Entity in its widest sense. Every entity can log
 * and query/change the language settings where needed.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
abstract class Entity {

    /**
     * We occasionally log stuff (debug/audit). Have an initialised Logging
     * instance nearby is sure helpful.
     * 
     * @var Logging
     */
    protected $loggerInstance;

    /**
     * access to language settings to be able to switch textDomain
     * 
     * @var Language
     */
    protected $languageInstance;
    
    public function __construct() {
        $this->loggerInstance = new Logging();
        $this->languageInstance = new Language();
    }

}
