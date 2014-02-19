<?php
$FAQ = array();

array_push($FAQ,
      array(
        'title'=>sprintf(_("My institution is not listed. Can't I just use any of the other ones?")),
        'text'=>sprintf(_("No! The installers contain security settings which are specific to the institution. If you are not from that institution, your computer will detect that you are about to send your username and credential to an unauthorised server and will abort the login. Using a different institution installer is <i>guaranteed to not work</i>!"))
         ));

array_push($FAQ,
      array(
        'title'=>sprintf(_("What can I do to get my institution listed?")),
        'text'=>sprintf(_("Contact %s administrators at your home institution and complain. It will take at most one hour of their time to get things done."),Config::$CONSORTIUM['name'])
));

array_push($FAQ,
      array(
        'title'=>sprintf(_("My device is not listed! Does that mean I can't do %s?"),Config::$CONSORTIUM['name']),
        'text'=>sprintf(_("No. The CAT tool can only support Operating Systems which can be automatically configured in some way. Many other devices can still be used with %s, but must be configured manually. Please contact your %s Identity Provider to get help in setting up such a device."),Config::$CONSORTIUM['name'],Config::$CONSORTIUM['name'])
      ));

array_push($FAQ,
      array(
        'title'=>sprintf(_("I can connect to %s simply by providing username and password, what is the point of using an installer?"),Config::$CONSORTIUM['name']),
        'text'=>sprintf(_("When you are connecting from an unconfigured device your security is at risk. The very point of preconfiguration is to set up security, when this is done, your device will first confirm that it talks to the correct authentication server and will never send your password to an untrusted one."))
));

if (Config::$CONSORTIUM['name'] == "eduroam") {
   array_push($FAQ,
         array(
           'title'=>sprintf(_("What is this eduroam thing anyway?")),
           'text'=>sprintf(_("eduroam is a global WiFi roaming consortium which gives members of education and research access to the internet <i>for free</i> on all eduroam hotspots on the planet. There are several million eduroam users already, enjoying free internet access on more than 6.000 hotspots! Visit <a href='http://www.eduroam.org'>the eduroam homepage</a> for more details."))
         ));
}

array_push($FAQ,
      array(
        'title'=>sprintf(_("Is it safe to use %s installers?"),Config::$APPEARANCE['productname']),
        'text'=>sprintf(_("%s installers configure security settings on your device, therefore you should be sure that you are using genuine ones."),Config::$APPEARANCE['productname']).' '.( isset(Config::$CONSORTIUM['signer_name']) && Config::$CONSORTIUM['signer_name'] != "" ? sprintf(_("This is why %s installers are digitally signed by %s. Watch out for a system message confirming this."),Config::$APPEARANCE['productname'],Config::$CONSORTIUM['signer_name']):""),
        
));

array_push($FAQ,
      array(
        'title'=>_("Windows 'SmartScreen' or 'Internet Explorer' tell me that the file is not commonly downloaded and possibly harmful. Should I be concerned?"),
        'text'=>_("Contrary to what the name suggests, 'SmartScreen' isn't actually very smart. The warning merely means that the file has not yet been downloaded by enough users to make Microsoft consider it popular (which would strangely enough make it be considered 'safe'). This message alone is not a security problem.")." ".(isset(Config::$CONSORTIUM['signer_name']) && Config::$CONSORTIUM['signer_name'] != "" ? sprintf(_("So long as the file is carrying a valid signature from %s, the download is safe."),Config::$CONSORTIUM['signer_name'])." ":"").sprintf(_("Please see also Microsoft's FAQ regarding SmartScreen at %s."),"<a href='http://windows.microsoft.com/en-US/windows7/SmartScreen-Filter-frequently-asked-questions-IE9?SignedIn=1'>Microsoft FAQ</a>")
        
));

array_push($FAQ,
      array(
        'title'=>sprintf(_("Why is there no support for Android?")),
        'text'=>sprintf(_("%s relies on technologies being available on the target platform - we need to be able to 'feed' security settings from a configuration file which you can download. Many Operating Systems like Apple and Microsoft have that functionality, but for Android no such mechanism exists."), Config::$APPEARANCE['productname']) . 
               '<p>' .
               sprintf(_("That is - <i>to our knowledge</i>. If you are a developer with some good knowledge about Android's APIs, and you know how to do it - please let us know; we would be delighted!"))
      ));


array_push($FAQ,
      array(
        'title'=>sprintf(_("I can see %s network and my device is configured but it does not connect, what can be the cause?"),Config::$CONSORTIUM['name']),
      'text'=>sprintf(_("There can be a number of different reasons. The network you see may not be a genuine %s one and your device silently drops the connection attempt; there may be something wrong with the configuration of the network; your account may have expired; there may be a connection problem with your home authentication server; you may have broken the regulations of the network you are using and have been refused access as a consequence. You should contact your home institution and report the problem, the administrators should be able to trace your connections."),Config::$CONSORTIUM[
'name'])
));

array_push($FAQ,
      array(
        'title'=>sprintf(_("I have a question about this web site. Whom should I contact?")),
        'text'=>sprintf(_("You should send a mail to %s."),"cat-users@geant.net")
      ));



/*
array_push($FAQ,
      array(
        'title'=>sprintf(_("")),
        'text'=>sprintf(_(""))
      ));

*/






?>

<div>
    <h1>
        <?php echo _("Frequently Asked Questions"); ?>
    </h1>
    <div class="faq_toc" style="background:white; padding:5px;">
    <?php
    $i =0;
    foreach ($FAQ as $faq) {
       print '<a href="#toc'.$i.'">'.$faq['title']."</a><br>\n";
       $i++;
    }
    ?>
    </div>
    <dl>
    <?php
    $i =0;
    foreach ($FAQ as $faq) {
      print "<dt><a name=toc$i>".$faq['title']."</a></dt>\n<dd>".$faq['text']."</dd>\n";
      $i++;
    }
    ?>
</dl>
</div>
