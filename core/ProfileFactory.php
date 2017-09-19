<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */

namespace core;

/**
 * This factory class generates either a ProfileRADIUS or a ProfileSilverbullet
 * as needed. Indication which to choose is by supported EAP types in the
 * profile in question
 */
class ProfileFactory {

    /** is this profile a RADIUS profile or SILVERBULLET?
     * find out, and return an instance of the instantiated sub-class as appropriate
     * 
     * @param int $profileId ID of the profile in DB
     * @param IdP $idpObject optional. If the IdP is already instantiated then the instance can be passed here to avoid another instantiation
     * 
     * @return AbstractProfile a sub-class of AbstractProfile matching the type
     */
    public static function instantiate($profileId, $idpObject = NULL) {
        // we either need a ProfileRADIUS or ProfileSilverbullet. Try one, and
        // switch to the other if our guess was wrong
        $attempt = new ProfileRADIUS($profileId, $idpObject);
        $methods = $attempt->getEapMethodsinOrderOfPreference();
        if ((count($methods) == 1) && $methods[0]->getArrayRep() == \core\common\EAP::EAPTYPE_SILVERBULLET) {
            return new ProfileSilverbullet($profileId, $idpObject);
        }
        return $attempt;
    }

}
