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
 * This file contains a number of functions for talking to the outside world
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 */

namespace core;

class OutsideComm {

    public static function downloadFile($url) {
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

    public static function mailHandle() {
// use PHPMailer to send the mail
        $mail = new \PHPMailer\PHPMailer\PHPMailer();
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

}
