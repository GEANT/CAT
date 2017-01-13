<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

/**
 * This file contains Federation, IdP and Profile classes.
 * These should be split into separate files later.
 *
 * @package Developer
 */
/**
 * 
 */

namespace core;

use \Exception;

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
        $KNOWN_SUFFIXES = ["admin/", "diag/", "skins/", "user/"];

        foreach ($KNOWN_SUFFIXES as $suffix) {
            if (strpos($_SERVER['PHP_SELF'], $suffix) !== FALSE) {
                return $url . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], $suffix)) . $extrapath . $path;
            }
        }
        return $url . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/")) . $extrapath . $path;
    }

}
