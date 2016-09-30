<?php

/* * ********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

require_once("ProfileRADIUS.php");
require_once("ProfileSilverbullet.php");

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
     * @return AbstractProfile a sub-class of AbstractProfile matching the type
     */
    public static function instantiate($profileId, $idpObject = NULL) {
        // we either need a ProfileRADIUS or ProfileSilverbullet. Try one, and
        // switch to the other if our guess was wrong
        $attempt = new ProfileRADIUS($profileId, $idpObject);
        $methods = $attempt->getEapMethodsinOrderOfPreference();
        if ((count($methods) == 1) && $methods[0] == EAPTYPE_SILVERBULLET) {
            return new ProfileSilverbullet($profileId, $idpObject);
        }
        return $attempt;
    }

}
