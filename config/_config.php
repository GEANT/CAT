<?php
/**
 * This file sets a few paths. Usually, you don't have to change anything here!
 *
 * @package Configuration
 */

/**
 * 
 */
  $root = dirname(dirname(__FILE__));
  include($root."/config/config.php");
  set_include_path(get_include_path() . PATH_SEPARATOR . "$root/core" . PATH_SEPARATOR . "$root");