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

class Skinjob {

    /**
     * The skin that was selected
     * 
     * @var string
     */
    public $skin;

    public function __construct($selectedSkin) {
        // input may have been garbage. Sanity-check and fall back to default skin if needed
        if (!in_array($selectedSkin, CONFIG['APPEARANCE']['skins'])) {
            $selectedSkin = CONFIG['APPEARANCE']['skins'][0];
        }
        $this->skin = $selectedSkin;
    }

    /**
     * constructs a URL to the main resources (CSS and LOGO)
     * 
     * @param string $resourcetype which type of resource do we need a URL for?
     * @param bool $skinspecific is this a resource in the skin's own dir or a global one?
     * @return string the URL to the resource
     * @throws Exception if something went wrong during the URL construction
     */
    public function findResourceUrl($resourcetype, $skinspecific = FALSE) {
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
        $extrapath = "";
        if ($skinspecific) {
            $extrapath = "/skins/" . $this->skin;
        }
        $url = "//" . valid_host($_SERVER['HTTP_HOST']); // omitting http or https means "on same protocol"
        if ($url === FALSE) {
            throw new Exception("We don't know our own hostname?!");
        }
        // we need to construct the right path to the file; we are either
        // in the admin area or on the main index.php ...
        if (strpos($_SERVER['PHP_SELF'], "admin/") !== FALSE) {
            return $url . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/admin/")) . $extrapath . $path;
        }
        if (strpos($_SERVER['PHP_SELF'], "diag/") !== FALSE) {
            return $url . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/diag/")) . $extrapath . $path;
        }
        if (strpos($_SERVER['PHP_SELF'], "skins/") !== FALSE) {
            return $url . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/skins/")) . $extrapath . $path;
        }
        return $url . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/")) . $extrapath . $path;
    }

}
