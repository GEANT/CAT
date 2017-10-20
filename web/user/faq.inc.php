<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */
/* 
    this is just an include file for Gui class definition
*/
    $Faq = [
      [
        'id'=>'idp_not_listed',
        'title'=>sprintf(_("My %s is not listed. Can't I just use any of the other ones?"),$Gui->nomenclature_inst),
        'text'=>sprintf(_("No! The installers contain security settings which are specific to the %s. If you are not from that %s, your computer will detect that you are about to send your username and credential to an unauthorised server and will abort the login. Using a different %s installer is <i>guaranteed to not work</i>!"), $Gui->nomenclature_inst, $Gui->nomenclature_inst, $Gui->nomenclature_inst)
         ],
      [
        'id'=>'idp_not_listed',
        'title'=>sprintf(_("What can I do to get my %s listed?"), $Gui->nomenclature_inst),
        'text'=>sprintf(_("Contact %s administrators at your %s and complain. It will take at most one hour of their time to get things done."),CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], $Gui->nomenclature_inst)
],
      [
        'id'=>'device_not_listed',
        'title'=>sprintf(_("My device is not listed! Does that mean I can't do %s?"),CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']),
        'text'=>sprintf(_("No. The CAT tool can only support Operating Systems which can be automatically configured in some way. Many other devices can still be used with %s, but must be configured manually. Please contact your %s Identity Provider to get help in setting up such a device."),CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'],CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'])
      ],

      [
        'title'=>sprintf(_("I can connect to %s simply by providing username and password, what is the point of using an installer?"),CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']),
        'text'=>sprintf(_("When you are connecting from an unconfigured device your security is at risk. The very point of preconfiguration is to set up security, when this is done, your device will first confirm that it talks to the correct authentication server and will never send your password to an untrusted one."))
],
      [
        'title'=>sprintf(_("Is it safe to use %s installers?"),CONFIG['APPEARANCE']['productname']),
        'text'=>sprintf(_("%s installers configure security settings on your device, therefore you should be sure that you are using genuine ones."),CONFIG['APPEARANCE']['productname']).' '.( isset(CONFIG_CONFASSISTANT['CONSORTIUM']['signer_name']) && CONFIG_CONFASSISTANT['CONSORTIUM']['signer_name'] != "" ? sprintf(_("This is why %s installers are digitally signed by %s. Watch out for a system message confirming this."),CONFIG['APPEARANCE']['productname'],CONFIG_CONFASSISTANT['CONSORTIUM']['signer_name']):""),
        
],
      [
        'title'=>_("Windows 'SmartScreen' or 'Internet Explorer' tell me that the file is not commonly downloaded and possibly harmful. Should I be concerned?"),
        'text'=>_("Contrary to what the name suggests, 'SmartScreen' isn't actually very smart. The warning merely means that the file has not yet been downloaded by enough users to make Microsoft consider it popular (which would strangely enough make it be considered 'safe'). This message alone is not a security problem.")." ".(isset(CONFIG_CONFASSISTANT['CONSORTIUM']['signer_name']) && CONFIG_CONFASSISTANT['CONSORTIUM']['signer_name'] != "" ? sprintf(_("So long as the file is carrying a valid signature from %s, the download is safe."),CONFIG_CONFASSISTANT['CONSORTIUM']['signer_name'])." ":"").sprintf(_("Please see also Microsoft's FAQ regarding SmartScreen at %s."),"<a href='http://windows.microsoft.com/en-US/windows7/SmartScreen-Filter-frequently-asked-questions-IE9?SignedIn=1'>Microsoft FAQ</a>")
        
],
      [
        'title'=>sprintf(_("I can see %s network and my device is configured but it does not connect, what can be the cause?"),CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']),
      'text'=>sprintf(_("There can be a number of different reasons. The network you see may not be a genuine %s one and your device silently drops the connection attempt; there may be something wrong with the configuration of the network; your account may have expired; there may be a connection problem with your home authentication server; you may have broken the regulations of the network you are using and have been refused access as a consequence. You should contact your %s and report the problem, the administrators should be able to trace your connections."),CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], $Gui->nomenclature_inst)
],
      [
        'id'=>'contact',
        'title'=>sprintf(_("I have a question about this web site. Whom should I contact?")),
        'text'=>sprintf(_("You should send a mail to %s."),CONFIG['APPEARANCE']['support-contact']['display'])
      ],
];

    if (CONFIG_CONFASSISTANT['CONSORTIUM']['name'] == "eduroam") {
       array_push($Faq,
         [
           'id'=>'what_is_'.CONFIG_CONFASSISTANT['CONSORTIUM']['name'],
           'title'=>sprintf(_("What is this %s thing anyway?"), CONFIG_CONFASSISTANT['CONSORTIUM']['display_name']),
           'text'=>sprintf(_("%s is a global WiFi roaming consortium which gives members of education and research access to the internet <i>for free</i> on all %s hotspots on the planet. There are several million %s users already, enjoying free internet access on more than 6.000 hotspots! Visit <a href='http://www.eduroam.org'>the %s homepage</a> for more details."),CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'],CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'],CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'],CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'])
         ]);
    }