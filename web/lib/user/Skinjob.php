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

/**
 * This file contains a class for handling switching between skin frontends.
 *
 * @package Developer
 */
/**
 * 
 */

namespace web\lib\user;

use \Exception;

/**
 * This class handles user UI skin handling.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 */
class Skinjob {

    /**
     * The skin that was selected
     * 
     * @var string
     */
    public $skin;
    
    /**
     * Initialise the skin.
     * 
     * @param string $selectedSkin the name of the skin to use
     */
    public function __construct($selectedSkin = NULL) {
        // input may have been garbage. Sanity-check and fall back to default skin if needed
        $actualSkin = \config\Master::APPEARANCE['skins'][0];
        if (in_array($selectedSkin, \config\Master::APPEARANCE['skins'])) {
            $correctIndex = array_search($selectedSkin, \config\Master::APPEARANCE['skins']);
            $actualSkin = \config\Master::APPEARANCE['skins'][$correctIndex];
        }

        $this->skin = $actualSkin;
        $_SESSION['skin'] = $actualSkin;

    }

    /**
     * constructs a URL to the main resources directories. Searches for the file
     * first in the current skin's resource dir, then falls back to the global
     * resources dir, or returns FALSE if the requested file could not be found
     * at either location.
     * 
     * @param string $resourcetype which type of resource do we need a URL for?
     * @param string $filename     the name of the file being searched.
     * @param string $submodule    an area (diag, admin, ...) where to look
     * @return string|boolean the URL to the resource, or FALSE if this file does not exist
     * @throws Exception if something went wrong during the URL construction
     */
    public function findResourceUrl($resourcetype, $filename, $submodule = '') {
        switch ($resourcetype) {
            case "CSS":
                $path = "/resources/css/";
                break;
            case "IMAGES":
                $path = "/resources/images/";
                break;
            case "BASE":
                $path = "/";
                break;
            case "EXTERNAL":
                $path = "/external/";
                break;
            default:
                throw new Exception("findResourceUrl: unknown type of resource requested");
        }

        // does the file exist in the current skin's directory? Has precedence
        if ($submodule !== '' && file_exists(__DIR__ . "/../../skins/" . $this->skin . "/" . $submodule . $path . $filename)) {
            $extrapath = "/skins/" . $this->skin . "/" . $submodule;
        }
        elseif (file_exists(__DIR__ . "/../../skins/" . $this->skin . $path . $filename)) {
            $extrapath = "/skins/" . $this->skin;
        } elseif (file_exists(__DIR__ . "/../../" . $path . $filename)) {
            $extrapath = "";
        } else {
            return FALSE;
        }       
        return htmlspecialchars(\core\CAT::getRootUrlPath() . $extrapath . $path . $filename, ENT_QUOTES);
    }

}
