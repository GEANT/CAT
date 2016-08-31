<?php

/* * ********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

/**
 * This file contains a random assortment of useful functions and classes.
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 */
/**
 * necessary includes
 */
require_once(dirname(dirname(__FILE__)) . "/config/_config.php");

define("L_OK", 0);
define("L_REMARK", 4);
define("L_WARN", 32);
define("L_ERROR", 256);

function error($t) {
    print ("$t\n");
}

/**
 * write debug messages to the log
 *
 */
function debug($level, $t) {
    if (Config::$DEBUG_LEVEL < $level) {
        return;
    }
    ob_start();
    printf("%-015s", microtime(TRUE));
    print " ($level) ";
    print_r($t);
    $output = ob_get_clean();
    if (Config::$PATHS['logdir']) {
        $file = fopen(Config::$PATHS['logdir'] . "/debug.log", "a");
        fwrite($file, $output);
        fclose($file);
        return;
    }
    print $output;
}

/**
 * this direcory delete function has been copied from PHP documentation
 */
function rrmdir($dir) {
    foreach (glob($dir . '/*') as $file) {
        if (is_dir($file))
            rrmdir($file);
        else
            unlink($file);
    }
    rmdir($dir);
}

function downloadFile($url) {
    if (preg_match("/:\/\//", $url)) {
        # we got a URL, download it
        $download = fopen($url, "rb");
        $data = stream_get_contents($download);
        if (!$data) {
            debug(2, "Failed to download the file from $url");
            return FALSE;
        }
        return $data;
    }
    debug(3, "The specified string does not seem to be a URL!");
    return FALSE;
}

/**
 * generates a UUID
 *
 * @param string $prefix an extra prefix to set before the UUID
 * @return UUID (possibly prefixed)
 */
function uuid($prefix = '', $deterministicSource = NULL) {
    if ($deterministicSource === NULL)
        $chars = md5(uniqid(mt_rand(), true));
    else
        $chars = md5($deterministicSource);
    $uuid = substr($chars, 0, 8) . '-';
    $uuid .= substr($chars, 8, 4) . '-';
    $uuid .= substr($chars, 12, 4) . '-';
    $uuid .= substr($chars, 16, 4) . '-';
    $uuid .= substr($chars, 20, 12);
    return $prefix . $uuid;
}

/**
 * pick a proper value for a given language
 * @param array $valueArray an array of (locale,content) records
 * @param string locale language code
 * @return string localised value corresponding to the chosen
 * locale or to the defalut locale C if a better mach was not available
 */
function getLocalisedValue($valueArray, $locale) {
    $out = 0;
    if (count($valueArray) > 0) {
        $returnValue = [];
        foreach ($valueArray as $val) {
            $try = unserialize($val['value']);
            $returnValue[$try['lang']] = $try['content'];
        }
        if (isset($returnValue[$locale])) {
            $out = $returnValue[$locale];
        }
        elseif (isset($returnValue['C'])) {
            $out = $returnValue['C'];
        }
    }
    debug(4, "getLocalisedValue:$locale:$out\n");
    return $out;
}

/**
 * create a temporary directory and return the location
 * @param $purpose one of 'installer', 'logo', 'test' defined the purpose of the directory
 * @param $failIsFatal (default true) decides if a creation failure should cause an error
 * @return - the tupple full directory path, directory name
 */
function createTemporaryDirectory($purpose = 'installer', $failIsFatal = 1) {
    $name = md5(time() . rand());
    switch ($purpose) {
        case 'installer':
            $path = CAT::$root . '/var/installer_cache';
            break;
        case 'logo':
            $path = CAT::$root . '/web/downloads/logos';
            break;
        case 'test':
            $path = CAT::$root . '/var/tmp';
            break;
        default:
            throw new Exception("unable to create temporary directory due to unknown purpose: $purpose\n");
    }
    $tmpDir = $path . '/' . $name;
    debug(4, "temp dir: $purpose : $tmpDir\n");
    if (!mkdir($tmpDir, 0700, true)) {
        if ($failIsFatal) {
            throw new Exception("unable to create temporary directory: $tmpDir\n");
        }
        debug(4, "Directory creation failed for $tmpDir\n");
        return ['base' => $path, 'dir' => '', $name => ''];
    }
    debug(4, "Directory created: $tmpDir\n");
    return ['base' => $path, 'dir' => $tmpDir, 'name' => $name];
}

function png_inject_consortium_logo($inputpngstring, $symbolsize = 12, $marginsymbols = 4) {
    $inputgd = imagecreatefromstring($inputpngstring);

    debug(4, "Consortium logo is at: " . CAT::$root . "/web/resources/images/consortium_logo_large.png");
    $logogd = imagecreatefrompng(CAT::$root . "/web/resources/images/consortium_logo_large.png");

    $sizeinput = [imagesx($inputgd), imagesy($inputgd)];
    $sizelogo = [imagesx($logogd), imagesy($logogd)];
    // Q level QR-codes can sustain 25% "damage"
    // make our logo cover approx 15% of area to be sure; mind that there's a $symbolsize * $marginsymbols pixel white border around each edge
    $totalpixels = ($sizeinput[0] - $symbolsize * $marginsymbols) * ($sizeinput[1] - $symbolsize * $marginsymbols);
    $totallogopixels = ($sizelogo[0]) * ($sizelogo[1]);
    $maxoccupy = $totalpixels * 0.04;
    // find out how much we have to scale down logo to reach 10% QR estate
    $scale = sqrt($maxoccupy / $totallogopixels);
    debug(4, "Scaling info: $scale, $maxoccupy, $totallogopixels\n");
    // determine final pixel size - round to multitude of $symbolsize to match exact symbol boundary
    $targetwidth = $symbolsize * round($sizelogo[0] * $scale / $symbolsize);
    $targetheight = $symbolsize * round($sizelogo[1] * $scale / $symbolsize);
    // paint white below the logo, in case it has transparencies (looks bad)
    // have one symbol in each direction extra white space
    $whiteimage = imagecreate($targetwidth + 2 * $symbolsize, $targetheight + 2 * $symbolsize);
    imagecolorallocate($whiteimage, 255, 255, 255);
    // also make sure the initial placement is a multitude of 12; otherwise "two half" symbols might be affected
    $targetplacementx = $symbolsize * round(($sizeinput[0] / 2 - ($targetwidth - $symbolsize) / 2) / $symbolsize);
    $targetplacementy = $symbolsize * round(($sizeinput[1] / 2 - ($targetheight - $symbolsize) / 2) / $symbolsize);
    imagecopyresized($inputgd, $whiteimage, $targetplacementx - $symbolsize, $targetplacementy - $symbolsize, 0, 0, $targetwidth + 2 * $symbolsize, $targetheight + 2 * $symbolsize, $targetwidth + 2 * $symbolsize, $targetheight + 2 * $symbolsize);
    imagecopyresized($inputgd, $logogd, $targetplacementx, $targetplacementy, 0, 0, $targetwidth, $targetheight, $sizelogo[0], $sizelogo[1]);
    // imagecopyresized($dst_image, $src_image, $dst_x,                               $dst_y,                                $src_x, $src_y, $dst_w,       $dst_h,        $src_w,       $src_h);
    ob_start();
    imagepng($inputgd);
    return ob_get_clean();
}
