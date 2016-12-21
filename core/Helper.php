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
require_once("Logging.php");

define("L_OK", 0);
define("L_REMARK", 4);
define("L_WARN", 32);
define("L_ERROR", 256);

function error($t) {
    print ("$t\n");
}

/**
 * this direcory delete function has been copied from PHP documentation
 */
function rrmdir($dir) {
    foreach (glob($dir . '/*') as $file) {
        if (is_dir($file)) {
            rrmdir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dir);
}

function downloadFile($url) {
    $loggerInstance = new Logging();
    if (preg_match("/:\/\//", $url)) {
        # we got a URL, download it
        $download = fopen($url, "rb");
        $data = stream_get_contents($download);
        if (!$data) {

            $loggerInstance->debug(2, "Failed to download the file from $url");
            return FALSE;
        }
        return $data;
    }
    $loggerInstance->debug(3, "The specified string does not seem to be a URL!");
    return FALSE;
}

function random_str(
$length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
) {
    $str = '';
    $max = strlen($keyspace) - 1;
    if ($max < 1) {
        throw new Exception('$keyspace must be at least two characters long');
    }
    for ($i = 0; $i < $length; ++$i) {
        $str .= $keyspace[random_int(0, $max)];
    }
    return $str;
}


/**
 * generates a UUID
 *
 * @param string $prefix an extra prefix to set before the UUID
 * @return string UUID (possibly prefixed)
 */
function uuid($prefix = '', $deterministicSource = NULL) {
    if ($deterministicSource === NULL) {
        $chars = md5(uniqid(mt_rand(), true));
    } else {
        $chars = md5($deterministicSource);
    }
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
    $loggerInstance = new Logging();
    $out = 0;
    if (count($valueArray) > 0) {
        $returnValue = [];
        foreach ($valueArray as $val) {
            $try = unserialize($val['value']);
            $returnValue[$try['lang']] = $try['content'];
        }
        if (isset($returnValue[$locale])) {
            $out = $returnValue[$locale];
        } elseif (isset($returnValue['C'])) {
            $out = $returnValue['C'];
        }
    }
    $loggerInstance->debug(4, "getLocalisedValue:$locale:$out\n");
    return $out;
}

/**
 * create a temporary directory and return the location
 * @param $purpose one of 'installer', 'logo', 'test' defined the purpose of the directory
 * @param $failIsFatal (default true) decides if a creation failure should cause an error
 * @return array the tuple of: base path, absolute path for directory, directory name
 */
function createTemporaryDirectory($purpose = 'installer', $failIsFatal = 1) {
    $loggerInstance = new Logging();
    $name = md5(time() . rand());
    $path = ROOT;
    switch ($purpose) {
        case 'silverbullet':
            $path .= '/var/silverbullet';
            break;
        case 'logo':
        case 'installer':
            $path .= '/var/installer_cache';
            break;
        case 'logo':
            $path .= '/web/downloads/logos';
            break;
        case 'test':
            $path .= '/var/tmp';
            break;
        default:
            throw new Exception("unable to create temporary directory due to unknown purpose: $purpose\n");
    }
    $tmpDir = $path . '/' . $name;
    $loggerInstance->debug(4, "temp dir: $purpose : $tmpDir\n");
    if (!mkdir($tmpDir, 0700, true)) {
        if ($failIsFatal) {
            throw new Exception("unable to create temporary directory: $tmpDir\n");
        }
        $loggerInstance->debug(4, "Directory creation failed for $tmpDir\n");
        return ['base' => $path, 'dir' => '', $name => ''];
    }
    $loggerInstance->debug(4, "Directory created: $tmpDir\n");
    return ['base' => $path, 'dir' => $tmpDir, 'name' => $name];
}

function png_inject_consortium_logo($inputpngstring, $symbolsize = 12, $marginsymbols = 4) {
    $loggerInstance = new Logging();
    $inputgd = imagecreatefromstring($inputpngstring);

    $loggerInstance->debug(4, "Consortium logo is at: " . ROOT . "/web/resources/images/consortium_logo_large.png");
    $logogd = imagecreatefrompng(ROOT . "/web/resources/images/consortium_logo_large.png");

    $sizeinput = [imagesx($inputgd), imagesy($inputgd)];
    $sizelogo = [imagesx($logogd), imagesy($logogd)];
    // Q level QR-codes can sustain 25% "damage"
    // make our logo cover approx 15% of area to be sure; mind that there's a $symbolsize * $marginsymbols pixel white border around each edge
    $totalpixels = ($sizeinput[0] - $symbolsize * $marginsymbols) * ($sizeinput[1] - $symbolsize * $marginsymbols);
    $totallogopixels = ($sizelogo[0]) * ($sizelogo[1]);
    $maxoccupy = $totalpixels * 0.04;
    // find out how much we have to scale down logo to reach 10% QR estate
    $scale = sqrt($maxoccupy / $totallogopixels);
    $loggerInstance->debug(4, "Scaling info: $scale, $maxoccupy, $totallogopixels\n");
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
    ob_start();
    imagepng($inputgd);
    return ob_get_clean();
}

function mailHandle() {
// use PHPMailer to send the mail
    $mail = new PHPMailer\PHPMailer\PHPMailer();
    $mail->isSMTP();
    $mail->SMTPAuth = true;
    $mail->Port = 587;
    $mail->SMTPSecure = 'tls';
    $mail->Host = CONFIG['MAILSETTINGS']['host'];
    $mail->Username = CONFIG['MAILSETTINGS']['user'];
    $mail->Password = CONFIG['MAILSETTINGS']['pass'];
// formatting nitty-gritty
    $mail->WordWrap = 72;
    $mail->isHTML(FALSE);
    $mail->CharSet = 'UTF-8';
    $mail->From = CONFIG['APPEARANCE']['from-mail'];
    // are we fancy? i.e. S/MIME signing?
    if (isset(CONFIG['CONSORTIUM']['certfilename'], CONFIG['CONSORTIUM']['keyfilename'], CONFIG['CONSORTIUM']['keypass'])) {
            $mail->sign(CONFIG['CONSORTIUM']['certfilename'], CONFIG['CONSORTIUM']['keyfilename'], CONFIG['CONSORTIUM']['keypass']);
        }
    return $mail;
}