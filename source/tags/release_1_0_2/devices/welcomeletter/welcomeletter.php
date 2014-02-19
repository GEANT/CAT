<?php

/* * *********************************************************************************
 * (c) 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php

/**
 * This file contains the installer for iOS devices and Apple 10.7 Lion
 *
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @package Developer
 */
/**
 * 
 */
require_once('DeviceConfig.php');
require_once('X509.php');

// set_locale("devices");

/**
 * This is the main implementation class of the module
 *
 * The class should only define one public method: writeInstaller.
 *
 * All other methods and properties should be private. This example sets zipInstaller method to protected, so that it can be seen in the documentation.
 *
 * @package Developer
 */
class Device_welcomeletter extends DeviceConfig {

    final public function __construct() {
        $this->supportedEapMethods = array(EAP::$TLS, EAP::$PEAP_MSCHAP2, EAP::$TTLS_PAP, EAP::$TTLS_GTC, EAP::$FAST_GTC, EAP::$TTLS_MSCHAP2, EAP::$PWD);
        debug(4, "This device supports the following EAP methods: ");
        debug(4, $this->supportedEapMethods);
    }

    /**
     * this array holds the list of EAP methods supported by this device
     */
    // public static $my_eap_methods = array(EAP::$TLS, EAP::$PEAP_MSCHAP2, EAP::$TTLS_PAP, EAP::$FAST_GTC);
//   public static $my_eap_methods = array(array("OUTER" => TLS, "INNER" => NONE), array("OUTER" => PEAP, "INNER" => MSCHAPv2), array("OUTER" => TTLS, "INNER" => NONE));

    /**
     * prepare a zip archive containing files and settings which normally would be used inside the module to produce an installer
     *
     * {@source}
     */
    public function writeInstaller() {
        /** run innitial setup
          this will:
          - create the temporary directory and save its path as $this->FPATH
          - process the CA certificates and store results in $this->attributes['internal:CAs'][0]
          $this->attributes['internal:CAs'][0] is an array of processed CA certificates
          a processed certifincate is an array
          'pem' points to pem feromat certificate
          'der' points to der format certificate
          'md5' points to md5 fingerprint
          'sha1' points to sha1 fingerprint
          'name' points to the certificate subject
          - save the info_file (if exists) and put the name in $this->attributes['internal:info_file_name'][0]
         */
//    $this->supportedEapMethods = Device_welcomeletter::$my_eap_methods;

        debug(4, "mobileconfig Module Installer start\n");

        $this->copyFile('welcomeletter.odt');

        $o = system('unzip -q -d ./ welcomeletter.odt');

        $source_content = fopen('content.xml', 'r+');
        $source_styles = fopen('styles.xml', 'r+');

        $content = fread($source_content, 1000000);
        $style = fread($source_styles, 1000000);

        ftruncate($source_content, 0);
        ftruncate($source_styles, 0);
        /// welcomletter: header of document
        $style = preg_replace('/XXXDOCUMENT_HEADXXX/', _("Welcome Letter"), $style);
        /// welcomletter: footer
        $style = preg_replace('/XXXFOOTERXXX/', _("eduroam® - supporting academic mobility since 2003"),$style);
        /// welcomeletter: the headline (bold, large)
        $content = preg_replace('/XXXDOCUMENT_HEADLINEXXX/', _("Welcome aboard the eduroam® user community!"), $content);
        /// welcomeletter: initial greeting
        $content = preg_replace('/XXXDOCUMENT_USERGREETXXX/', sprintf(_("Dear user from %s,"), $this->attributes['general:instname'][0]), $content);
        /// welcomeletter: sales pitch paragraph :-)
        $content = preg_replace('/XXXDOCUMENT_SALESPITCHXXX/', _("we would like to warmly welcome you among the several million users of eduroam®! From now on, you will be able to use internet access resources on thousands of universities, research centres and other places all over the globe. All of this completely free of charge!"), $content);
        /// welcomeletter: log in now paragraph
        $content = preg_replace('/XXXDOCUMENT_LOGINXXX/', _("Now that you have downloaded and installed a client configurator, all you need to do is find an eduroam® hotspot in your vicinity and enter your user credentials (this is our fancy name for 'username and password' or 'personal certificate') - and be online!"), $content);
        /// welcomeletter: explain support procedure
        $content = preg_replace('/XXXDOCUMENT_SUPPORTPROCEDUREXXX/', sprintf(_("Should you have any problems using this service, please always contact the helpdesk of %s. They will diagnose the problem and help you out. You can reach them via the means below:"), $this->attributes['general:instname'][0]), $content);
        /// welcomeletter: explain manual config
        $content = preg_replace('/XXXDOCUMENT_MANUALADVICEXXX/', _("If you ever need to configure a different device for eduroam manually, you will need the configuration information for your institution. We have summarised this information in the appendix on page 2 for you."), $content);
        /// welcomeletter: have fun
        $content = preg_replace('/XXXHAVEFUNXXX/', _("We wish you a lot of fun on eduroam® hotspots all over the planet."), $content);
        /// welcomeletter: title of the big boss
        $content = preg_replace('/XXXBOSS_DESIGNATIONXXX/', _("Chairman of the Global eduroam® Governance Committee"), $content);
        /// welcomeletter: header: appendix with config info
        $content = preg_replace('/XXXHEADER_CONFIGAPPENDIXXXX/', _("Appendix: Configuration Information"),$content);
        /// welcomeletter: header: appendix with config info
        $content = preg_replace('/XXXCONFIGAPPENDIXXXX/', sprintf(_("Use the information below to configure your devices manually. Please keep in mind that these settings are only valid for users of profile '%s' from %s – they are different for everyone else, and there is no use sharing them with other people."),$this->attributes['profile:name'][0],$this->attributes['general:instname'][0]),$content);
        $helpdesktext = "";
        if ($this->attributes['support:email'])
            foreach ($this->attributes['support:email'] as $number => $option)
                $helpdesktext .= _("E-Mail:")." <text:span text:style-name=\"T2\">" . $option . "</text:span></text:p><text:p style-name=\"Standard\">";

        if (isset($this->attributes['support:eap_types']))
            foreach ($this->attributes['support:eap_types'] as $number => $option)
                $helpdesktext .= _("Special EAP Type support URL:")." <text:span text:style-name=\"T1\">" . $option . "</text:span></text:p><text:p style-name=\"Standard\">";

        if (isset($this->attributes['support:phone']))
            foreach ($this->attributes['support:phone'] as $number => $option)
                $helpdesktext .= _("Phone:")." <text:span text:style-name=\"T2\">" . $option . "</text:span></text:p><text:p style-name=\"Standard\">";

        if (isset($this->attributes['support:url']))
            foreach ($this->attributes['support:url'] as $number => $option)
                $helpdesktext .= _("Web:")." <text:span text:style-name=\"T2\">" . $option . "</text:span></text:p><text:p style-name=\"Standard\">";

        $content = preg_replace('/XXXHELPDESKMAILXXX/', $helpdesktext, $content);

        $eapinfo = "";
        if (isset($this->attributes['eap:server_name']))
            foreach ($this->attributes['eap:server_name'] as $number => $option)
                $eapinfo .= _("Server Name:")." <text:span text:style-name=\"T2\">" . $option . "</text:span></text:p><text:p style-name=\"Standard\">";

        $content = preg_replace('/XXXEAPINFOXXX/', $eapinfo, $content);


        /*    if ($this->attributes['support:eap_types'])
          foreach ($this->attributes['support:eap_types'] as $number => $option)
          $helpdesktext .= "Special EAP Type support URL: <text:span text:style-name=\"T1\">".$option."</text:span></text:p><text:p style-name=\"Standard\">";
          if ($this->attributes['support:phone'])
          foreach ($this->attributes['support:phone'] as $number => $option)
          $helpdesktext .= "Phone: <text:span text:style-name=\"T2\">".$option."</text:span></text:p><text:p style-name=\"Standard\">";
          if ($this->attributes['support:url'])
          foreach ($this->attributes['support:url'] as $number => $option)
          $helpdesktext .= "Web: <text:span text:style-name=\"T2\">".$option."</text:span></text:p><text:p style-name=\"Standard\">";
         */

        fseek($source_content, 0, SEEK_SET);
        fwrite($source_content, $content);
        fclose($source_content);

        fseek($source_styles, 0, SEEK_SET);
        fwrite($source_styles, $style);
        fclose($source_styles);

        $o = system('zip -q welcomeletter.odt * -x local-info.* -x logo*.*');
        // openoffice needs to be running on headless TCP/8100 mode on the system...
        // python needs to be python2.7
        //$o2 = system('python /root/DocumentConverter.py '.$this->FPATH.'/welcomeletter.odt '.$this->FPATH.'/welcomeletter.pdf');
        $e = $this->installerBasename.'.odt';
        rename("welcomeletter.odt",$e);
        return $e;
    }

    public function writeDeviceInfo() {
        $out = "<p>";
        $out .= _("This button creates a personalised welcome letter from eduroam Operations for you. The file is in OpenDocument format (ODT); you can open it e.g. with LibreOffice.");
        return $out;
    }

}
