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


/* General AJAX comment: im many places we do not specfy the json argument in the
 * handler function and do the JSON decoding explicitely. The reason for this
 * is debugging - it is much easier to dump the raw JSON output fr a quick look.
 * To make the code cleaner it might be a good idea to change these calls in the future.
 */

use web\lib\user;

$cat = new \web\lib\user\Gui();
$idpId = filter_input(INPUT_GET, 'idp', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'idp', FILTER_VALIDATE_INT) ?? 0;
$profileId = filter_input(INPUT_GET, 'profile', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'profile', FILTER_VALIDATE_INT) ?? 0;
$skinObject = $Gui->skinObject;
if (\config\ConfAssistant::PRELOAD_IDPS) {
    print "const preloadIdPs = true;\n";
} else {
    print "const preloadIdPs = false;\n";
}
#print "idp = $idpId;\n";
    ?>

const apiURL = "<?php echo $skinObject->findResourceUrl("BASE", "user/API.php") ?>";
const catInfo = "<?php echo $skinObject->findResourceUrl("BASE", "user/cat_info.php")?>";
const overviewUser = "<?php echo $skinObject->findResourceUrl("BASE", "admin/overview_user.php")?>";
const remindIdP = "<?php echo $skinObject->findResourceUrl("BASE", "user/remindIdP.php") ?>";
const profile_list_size = <?php echo $profile_list_size ?>;
const generation_error = "<?php $cat->javaScriptEscapedEcho(_("This is embarrassing. Generation of your installer failed. System admins have been notified. We will try to take care of the problem as soon as possible.")) ?>";


var n;
var profile;
// var device_button_bg ="#0a698e";
var generateTimer;
var pageWidth = 0;
var device_button_fg;
var catWelcome;
var hide_images = 0;
var front_page = 1;
var download_link;
var openroaming = 'none';
var preagreed = false;
var pressedButton;
var profileDevices;
const discoCountries = {
<?php 
    $C = $Gui->printCountryList(1);
    $ret = '';
    foreach ($C as $key => $val) {
        $ret .= "'$key': \"$val\",";
    }
    echo substr($ret, 0, -1);
?>
  };
var idpsLoaded = false;

const guiTexts = {
  "noMatchingData": "<?php $cat->javaScriptEscapedEcho(_("no matching data found"))?>",
  "select": "<?php $cat->javaScriptEscapedEcho(_("select")) ?>",
  "www": "<?php $cat->javaScriptEscapedEcho(_("WWW:")) ?>",
  "email": "<?php $cat->javaScriptEscapedEcho(_("email:")) ?>",
  "tel": "<?php $cat->javaScriptEscapedEcho(_("tel:")) ?>",
  "problems": "<?php $cat->javaScriptEscapedEcho(_("If you encounter problems, then you can obtain direct assistance from your organisation at:")) ?>",
  "problemsGeneric": "<?php $cat->javaScriptEscapedEcho(_("If you encounter problems you should ask those who gave you your account for help.")) ?>",
  "unconfigurable": "<?php $cat->javaScriptEscapedEcho(_("This device cannot be configured with the settings used in your organisation."))?>",
  "redirect": "<?php $cat->javaScriptEscapedEcho(_("Your site administrator has specified that this device should be configured with resources located on a local page. When you click <b>Continue</b> this page will be opened in a new window/tab."))?>",
  "profile_redirect": "<?php $cat->javaScriptEscapedEcho(_($cat->textTemplates->templates[web\lib\user\DOWNLOAD_REDIRECT])) ?>",
  "continue": "<?php $cat->javaScriptEscapedEcho(_("Continue")) ?>",
  "close": "<?php $cat->javaScriptEscapedEcho(_("Close")) ?>",
  "noProviders": "<?php $cat->javaScriptEscapedEcho(_("No providers found for this email")) ?>",
  "yourIdP": "<?php $cat->javaScriptEscapedEcho(_("Your IdP is:")) ?>",
  "yourIdPs": "<?php $cat->javaScriptEscapedEcho(_("Your IdP could be one of:")) ?>",
  "missingEmail": "<?php $cat->javaScriptEscapedEcho(_("Missing email address")) ?>",
  "entryUpdate": "<?php $cat->javaScriptEscapedEcho(_("This entry was last updated at:")) ?>",
  "openRoamingTouWarning": "<?php $cat->javaScriptEscapedEcho(_("If you intend to download an installer which also enables OpenRoaming then you must accept OpenRoaming Terms and Conditions.")) ?>",
  "openRoamingText1": "<?php $cat->javaScriptEscapedEcho(_("If you select installers with OpenRoaming support, remember to indicate your consent.")) ?>",
  "openRoamingText2": "<?php $cat->javaScriptEscapedEcho(_("The installer has built-in OpenRoaming support.")) ?>",
  "openRoamingText3": "<?php $cat->javaScriptEscapedEcho(sprintf(_("I want to use OpenRoaming and have read and accept <a href='%s' target='_blank'>%s</a>"), $cat->textTemplates->templates[user\NETWORK_TERMS_AND_PRIV]["OpenRoaming"]["TOU_LINK"], $cat->textTemplates->templates[user\NETWORK_TERMS_AND_PRIV]["OpenRoaming"]["TOU_TEXT"])) ?>",
  "openRoamingText4": "<?php $cat->javaScriptEscapedEcho(sprintf(_("I have read and accept <a href='%s' target='_blank'>%s</a>"), $cat->textTemplates->templates[user\NETWORK_TERMS_AND_PRIV]["OpenRoaming"]["TOU_LINK"], $cat->textTemplates->templates[user\NETWORK_TERMS_AND_PRIV]["OpenRoaming"]["TOU_TEXT"])) ?>",
  "openRoamingDisabled": "<?php $cat->javaScriptEscapedEcho(_("OpenRoaming is not supported on this device")) ?>",
  "downloadAnother": "<?php $cat->javaScriptEscapedEcho($cat->textTemplates->templates[user\DOWNLOAD_CHOOSE_ANOTHER]) ?>",
};

var discoTextStrings = {
  "title":"<?php $cat->javaScriptEscapedEcho(_("Organisation")) ?>",
  "subtitle":"<?php $cat->javaScriptEscapedEcho(_("Select your organisation")) ?>",
  "textHelp": "<?php $cat->javaScriptEscapedEcho(_("Help, my organisation is not on the list")) ?>",
  "textHelpMore": "<?php $cat->javaScriptEscapedEcho(sprintf(_("This system relies on information supplied by local %s administrators. If your organisation is not on the list, then nag them to add information to the %s database."), \config\ConfAssistant::CONSORTIUM['display_name'], \config\Master::APPEARANCE['productname'])) ?>",
  "textLocateMe": "<?php $cat->javaScriptEscapedEcho(_("Locate me more accurately using HTML5 Geo-Location")) ?>",
  "textShowProviders": "<?php $cat->javaScriptEscapedEcho(_("Show organisations in")) ?>",
  "textAllCountries": "<?php $cat->javaScriptEscapedEcho(_("all countries")) ?>",
  "textSearch" : "<?php $cat->javaScriptEscapedEcho(_("or search for an organisation, for example University of Oslo")) ?>",
  "textShowAllCountries": "<?php $cat->javaScriptEscapedEcho(_("show all countries")) ?>",
  "textLimited1" : "<?php $cat->javaScriptEscapedEcho(_("Results limited to")) ?>",
  "textLimited2" : "<?php $cat->javaScriptEscapedEcho(_("entries - show more")) ?>",
  "textNearby" : "<?php $cat->javaScriptEscapedEcho(_("Nearby")) ?>",
  "geoLoc_timeout" : "<?php $cat->javaScriptEscapedEcho(_("Location timeout")) ?>",
  "geoLoc_posUnavailable" : "<?php $cat->javaScriptEscapedEcho(_("Could not get your position"))?>",
  "geoLoc_permDenied" : "<?php $cat->javaScriptEscapedEcho(_("Your browser has denied access to your location")) ?>",
  "geoLoc_unknownError" : "<?php $cat->javaScriptEscapedEcho(_("Unknown location error")) ?>",
  "geoLoc_here" : "<?php $cat->javaScriptEscapedEcho(_("You are here:")) ?>",
  "geoLoc_getting" : "<?php $cat->javaScriptEscapedEcho(_("Getting your location...")) ?>",
  "geoLoc_nearby" : "<?php $cat->javaScriptEscapedEcho(_("Nearby providers shown on top.")) ?>",
};
var roller; // controlls if the system sliedes apper on the page
if (roller === undefined)
    roller = 0;
var noDisco;
if (noDisco === undefined)
    noDisco = 0;
var sbPage;
if (sbPage === undefined)
    sbPage = 0;
    
// used to keep the footer at the bottom whle the window is resized
$.fn.redraw = function() {
  $(this).each(function() {
    var redraw = this.offsetHeight;
  });
};

function otherInstallers() {
  clearUIsettings();
  $(".guess_os").hide();
  $("#other_installers").show();
  $("#devices").redraw();
  reset_footer();
}
// Print the list of profiles for the given IdP (identified as inst_id)

function listProfiles(inst_id,selected_profile) {
  var j;
  var otherdata;
  $('#welcome').hide();
  $("#silverbullet").hide();
  $('#user_welcome').hide();
  $("#idp_logo").hide();
  $("#fed_logo").hide();
  $("#inst_id").val(inst_id);
  $("#profile_id").val('');
  $(".signin_large").hide();
  if (roller)
    Program.stop_program = 1;
  $("#profiles").hide();
  $("#user_info").hide();
  $("#devices").hide();
  $("#profile_redirect").hide();
  $.post(apiURL, {action: 'listProfiles', api_version: 2, lang: lang, idp: inst_id}, function(data) {
    j = JSON.parse(data);
    result = j.status;
    if (j.otherdata !== undefined)
        otherdata = j.otherdata;
    if (! result) {
        alert(guiTexts.noMatchingData);
        document.location.href='<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/' ?>';
    }
    j = j.data;
    n = j.length;
    $("#profile_list").html('');
    inst_name = j[0].idp_name;
    logo = j[0].logo;
    // the #inst_name is the hiddien form field used to carry the info
    $("#inst_name").val(inst_name);
    $("#inst_name_span").html(inst_name);
    $(".inst_name").text(inst_name);
    $("#user_page").show();
    $("#institution_name").show();
    if (n > profile_list_size)
      $("#profile_list").append('<option value="0" selected style="color:red"> --' + guiTexts.select + ' --</option>');
    $.each(j,printP);
    if (n <= profile_list_size)
      $("#profile_list").append('<option value="0" selected style="display:none"> </option>');
    if (logo == 1) {
      $("#idp_logo").attr("src",apiURL + "?action=sendLogo&api_version=2&idp="+inst_id);
      $("#idp_logo").show();
    }
    $("#fed_logo").attr("src",apiURL +"?action=sendFedLogo&api_version=2&idp="+inst_id);
    if (otherdata !== undefined && otherdata['fedname'] !== undefined) {
      $("#fed_logo").attr("title",otherdata['fedname']);
      $("#fed_logo").attr("alt",otherdata['fedname']);
    }
    if (otherdata !== undefined && otherdata['fedurl'] !== undefined) {
      $("#fed_logo").css('cursor','pointer');
      $("#fed_logo").click(function(event) {
        window.open(otherdata['fedurl'], '_blank');
      });
    }
    $("#fed_logo").show();
    if (n > 1) {
      if (n <= profile_list_size) {
        $("#profile_list").attr('size',n+1);
      } else {
       $("#profile_list").attr('size',1);
      }
      $("#profiles").show();
    }
    
    // depending on the case if we have one or multiple profiles we either
    // show the select or skip the selection and show the profile
    if (n > 1 && selected_profile) {
      var theProfile = $('#profile_list option[value='+selected_profile+']');
      if ( theProfile.length == 1) { 
        theProfile.attr("selected",true);
        showProfile(selected_profile);
      } 
    }
    reset_footer();
  });
}

// the printP function prints an individual profile entry in the select
function printP(i,v) {
  if (n == 1 ) {
    $("#profiles").hide();
    $("#profile_list").append('<option value="'+v.profile+'" selected>'+v.display+'</option>');
    showProfile(v.profile);
  } else {
    $("#profile_list").append('<option value="'+v.profile+'">'+v.display+'</option>');
  }
}

// showProfile displays a single profile and the corresponding download buttons
// the argument is the numeric profile identifier
// Other than hiding unncecessay elements the function is essentialy an AJAX
// handler for profileAttributes call
function showProfile(prof) {
  $("#profile_redirect").hide();
  $("#silverbullet").hide();
  $("#other_installers").hide();
  $("#devices").hide();
  // no matching profile hide stuff and return
  if (prof == 0) {
    $("#user_info").hide();
//    $("#devices").hide();
    return;
  }
  var j, txt ;
  // set the global profile variable
  profile = prof;
  $("#profile_id").val(profile);
  txt = '';
  $.post(apiURL, {action: 'profileAttributes', api_version: 2, lang: lang, profile: profile}, function(data) {
    j1 = JSON.parse(data);
    result = j1.status;
    if (! result) {
      alert(guiTexts.noMatchingData);
      document.location.href='<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/' ?>';
    }
    j = j1.data;
            console.log(j.devices);

    if (j.description !== undefined && j.description) {
      $("#profile_desc").text(j.description);
      $("#profile_desc").css("display","inline-block");
       //  $("#profile_desc").show();
    } else {
      $("#profile_desc").hide();
      $("#profile_desc").text('');
    }
    updateTxt = guiTexts.entryUpdate+' '+j.last_changed+'</span><br/>';
    openroamming = 'none';
    preagreed = false;
    if (j.openroaming !== undefined) {
      var p = j.openroaming.match(/.*(?=-preagreed)/);
      if (p != null) {
        preagreed = true;
        openroaming = p[0];
      } else {
        openroaming = j.openroaming;
      }
    }
    if (j.local_url !== undefined && j.local_url) 
      txt = txt+'<span class="user_info">' + guiTexts.www + ' <a href="'+j.local_url+'" target="_blank">'+j.local_url+'</a></span><br/>';
    if (j.local_email !== undefined && j.local_email) 
      txt = txt+'<span class="user_info">' + guiTexts.email + ' <a href="mailto:'+j.local_email+'">'+j.local_email+'</a></span><br/>';
    if (j.local_phone !== undefined && j.local_phone) 
      txt = txt+'<span class="user_info">' + guiTexts.tel + ' ' +j.local_phone+'</span><br/>';
    if (txt) 
      txt = "<span class='user_info_header'>" + guiTexts.problems + "</span><br/>"+txt;
    else
      txt = "<span class='user_info_header'>" + guiTexts.problemsGeneric + '</span><br/>';
    $("#user_info").html(txt);
    $("#user_info").show();
    if (j.silverbullet) {
      $("#devices").hide();
      $("#silverbullet").show();
      return;
    }
    profileDevices = j.devices;
    mydev=findDevice(recognisedOS);
    console.log("recognisedOS:"+mydev);
    // create the main download page section

    
      // test if we have a global profile redirect in this case the array
      // will only have a single element and it will have id 0
      // such device id may not appear in any other situation    
    if (profileDevices.length == 1 && profileDevices[0].id == '0') {
      $("#devices").hide();
      $("#user_info").hide();
      $("#profile_redirect_bt").attr('href',profileDevices[0].redirect);
      $("#profile_redirect").show();
      reset_footer();
      return;
    }
    updateGuessOsDiv(mydev);
    resetDevices(false);
    // first handle the guess_os part
    if (!handleGuessOs(mydev))
      return;
     
    // now the full devices list
    $.each(j.devices,function(i,v) {
      // Now consider devices that cannot be configured due to unsupported
      // EAP methods. This can be recognised by the device status set to 1
      // however we also need to make sure that the redirect has not been
      // set for this device. 
        if (v.status > 0) {
          if( v.redirect == '0') {
            $("#"+v.id).addClass('disabledDevice');
            $("#info_b_"+v.id).addClass('disabledDevice');
          }
        }
    });
    $("#devices").show();
    reset_footer();
})
}


function resetDevices(initial) {    
  if (recognisedOS !== '' ) {
    mainOS(initial);
  } else {
    otherInstallers();
  }
  $("#devices button").removeClass('alertButton');
  $("#devices button").removeClass('disabledDevice');
  $("#devices button").removeClass('hasAdditonalInfo');
  $('#devices button').unbind('click');
  $('#devices tr').show();
  $('.device_info').html('');
  $('.device_info').hide();
  $("#user_welcome").hide();
  $("#devices").unbind();
  $("#devices").on('click', 'button', function(event) {
    event.preventDefault();
    deviceButtonClick($(this));
  });
} 

function clearUIsettings() {
    $("#other_installers").hide(); // 
    $("#openroaming_check").prop("checked", false);
    $("#g_or_"+recognisedOS).css("background-color", "#bbb");
    $("#g_or_"+recognisedOS).removeClass('enabled');
    $("#openroaming_tou").hide();
    $("#g_or_"+recognisedOS).hide();
    $("button.dev_or").removeClass('enabled');
    $("button.dev_or").css("background-color", "#bbb");
}

function handlePreagreed() {
  if (!preagreed) {
    if (openroaming == 'ask') {
      $("#or_text_1").show();
    }
    $("#openroaming_tou").show();
  } else {
    $("#openroaming_check").prop("checked", true);
    $("#openroaming_check").trigger("change");
  }
}

function resetOpenRoaming(mainOs, hs20) {
  if (mainOs === '' ) {
    return;
  }
  $("#g_or_"+mainOs).css("background-color", "#bbb");
  $("#g_or_"+mainOs).removeClass('enabled');
  $("#g_or_"+mainOs).hide();
  switch (openroaming) {
    case 'none':
      $("#download_button_header_"+mainOs).html("eduroam");
      $("#g_"+mainOs).show();
      $("#g_or_"+mainOs).hide();
      break;
    case 'ask':
      if (hs20 == "1") {
        $("#or_text_1").html(guiTexts.openRoamingText1);
        $("#or_text_2").html(guiTexts.openRoamingText3);
        $("#g_"+mainOs).show();
        handlePreagreed();
        $("#download_button_header_"+mainOs).html("eduroam only");
        $("#g_"+mainOs).show();
        $("#g_or_"+mainOs).show();
      } else {
          $("#download_button_header_"+mainOs).html("eduroam");        
      }
      $("#g_"+mainOs).show();
      break;
    case 'always':
      if (hs20 == "1") {
        $("#download_button_header_"+mainOs).html("eduroam and OpenRoaming");
        $("#or_text_1").html(guiTexts.openRoamingText2);
        $("#or_text_2").html(guiTexts.openRoamingText4);
        handlePreagreed();
        $("#g_"+mainOs).hide();
        $("#download_button_header_"+mainOs).html("eduroam and OpenRoaming");
        $("#g_or_"+mainOs).show();
      } else {
        $("#openroaming_tou").hide();
        $("#download_button_header_"+mainOs).html("eduroam");
        $("#g_"+mainOs).show();
      }
      break; 
  }
}

function mainOS(initial) {
  clearUIsettings();
  resetOpenRoaming(recognisedOS, recognisedOShs20);
  if (!initial) {
    $("div.guess_os").show();
  }
}


/*
now comes the definition of button click
there are several types of button possible
the main download button (.guess_os) my either generate immediate download action
or can open up the extra info window;
the device listing buttons (.other_os) just cause the device selection
and call the main download screen;
the info button (.more_info_b) just pop up the device info window
*/

function deviceButtonClick(button) {
  var device_id = button.attr("name");
  var info_id = 'info_'+device_id;
  if (button.hasClass("guess_os")) { // main download buttons first
    info_id = 'info_g_'+device_id;
    $('.device_info').hide();
    if (button.hasClass("dev_or") && $("#openroaming_check").prop("checked") == false) {
      alert(guiTexts.openRoamingTouWarning);
      return;
    }
    pressedButton = button;
    if (button.hasClass('hasAdditonalInfo')) {
      $('#'+info_id).show(100);
    } else {
      doDownload(device_id,0);
    }
  } else if (button.hasClass("other_os")) { // now the full list download buttons
      changeDevice(device_id);  
  } else if (button.hasClass("more_info_b")) {
    if (button.hasClass("disabledDevice")) {
      alert(guiTexts.unconfigurable)
    } else {
      showMoreDeviceInfo(device_id);
    }
  }
}

function showMoreDeviceInfo(devId) {
  $("#info_window").html("<h2>"+$('#'+devId).text()+"</h2>");
  $.post(apiURL, {action: 'deviceInfo', api_version: 2, lang: lang, device: devId, profile: profile, openroaming: openroaming}, deviceInfo);
}

function doDownload(devId, setOpenRoaming) {
    $('#download_info').hide();
    generateTimer = $.now();
    $("#devices").hide();
    $("#user_welcome").show();
    $.post(apiURL, {action: 'generateInstaller', api_version: 2, lang: lang, device: devId, profile: profile, openroaming: setOpenRoaming}, processDownload);
}

function findDevice(devId) {
  for (var i = 0; i < profileDevices.length; i++) {
    v = profileDevices[i];
      if (v.id == devId) {
        return(v);
      }
    }
    return(null);
}

function deviceInfo(data) {
  var h = $("#info_window").html();
  $("#info_window").html(h+data);
  $("#main_body").fadeTo("fast", 0.2,function() {
    var x = getWindowHCenter() - 350;
    var top = $("#main_body").get(0).getBoundingClientRect().top;
    if (top < -150) {
      $("#info_overlay").css("top", -top + 50);
    }
    $("#info_overlay").show();
  });
}

function handleGuessOs(recognisedDevice) {
    if (recognisedDevice == null)
        return true;
    if(recognisedDevice.redirect != '0') {
         alert(recognisedDevice.redirect);
    } 
  // handle devices that canot be configured due to lack of support
  // for required EAP methods
  /*
  if (recognisedDevice.status > 0 && recognisedDevice.redirect == '0') {
      // this requires a proper handler
      alert(guiTexts.unconfigurable);
    $("#devices").hide();
    $("#user_info").hide();
    $("#profile_redirtect_text").html(guiTexts.unconfigurable);
    $("span.redirect_link").hide();
    $("#profile_redirect").show();
    reset_footer();
    return false;
  }
      */
      // handle devices which require displaying extra information when
      // the dowmload button is pressed
  $('.device_info').html('');
  
  if (recognisedDevice.device_customtext != '0' || 
      recognisedDevice.eap_customtext != '0' || 
      recognisedDevice.message != '0') {
    $("#g_"+recognisedOS+",#g_or_"+recognisedOS).addClass('hasAdditonalInfo');
      i_div = $("#info_g_"+recognisedOS);
      /*
      if ($(this).hasClass("dev_or") && $("#openroaming_check").prop("checked") == false) {
        i_div.hide();
        alert(guiTexts.openRoamingTouWarning);
        return;
      }
    */

//        t = i_div.html();
    t = '';
    if (recognisedDevice.message != '0') {
      if (t != '')
        t += '<br>';
      t +=  recognisedDevice.message;
    }
    if (recognisedDevice.device_customtext != '0') {
      if (t != '')
        t += '<br>';
      t +=  recognisedDevice.device_customtext;
    }
    if (recognisedDevice.eap_customtext != '0') {
      if (t != '')
        t += '<br/>&nbsp;<br/>';
      t +=  recognisedDevice.eap_customtext;
    }
    t += "<br><span class='redirect_link'>"+guiTexts.continue+"</span>";
    i_div.html(t);
    $(".redirect_link").click(function(event) {
      i_div.hide('fast');
      var dev_id = pressedButton.attr('name');
      var setOpenRoaming = 0;
      if (pressedButton.hasClass("dev_or") && $("#openroaming_check").prop("checked") == true) {
        setOpenRoaming = 1;
      } 
      if (recognisedDevice.status == 0) {
        doDownload(dev_id, setOpenRoaming);
      }
    });           
  }
  return true;
}


function changeDevice(devId) {
  device = findDevice(devId);
  console.log(device);
  if (device.options.hs20 === undefined) {
      recognisedOShs20 = 0;
  } else {
      recognisedOShs20 = device.options.hs20;
  }
  recognisedOS = device.id;
  $("#device").val(device.id);
  updateGuessOsDiv(device);
  resetOpenRoaming(recognisedOS, recognisedOShs20);
  if (!handleGuessOs(device))
    return;
  $("#devices").show();
  $("div.guess_os").show();
  $("#other_installers").hide();
  reset_footer();
}

function changeLang(l) {
  $("#lang").val(l);
  document.cat_form.submit();
}

  
function updateGuessOsDiv(device) {
  $("#device_message").hide();
  $("#guess_os").empty();
  $("#download_another").remove();

  $("#download_text_1").empty();
  if (device != null) {
      $("#download_text_1").append("<div>Download installer for "+device.display+"</div>")
      $("#download_text_1").css('background-image', 'url("'+vendorlogo+device.group+'.png")');
      if (device.status > 0) {
        $("#device_message").html(guiTexts.unconfigurable);
        $("#device_message").show();
      } else {

      div1 = "<div>\
          <div class='button_wrapper'>\
            <button name='"+device.id+"' class='guess_os' id='g_"+device.id+"'>\
              <div class='download_button_text_1' id='download_button_header_"+device.id+"'>eduroam only\
              /div>\
            </button>\
          </div>\
          <div class='button_wrapper'>\
            <button name='"+device.id+"' class='guess_os dev_or' id='g_or_"+device.id+"'>\
              <div name='"+device.id+"' class='download_button_text_1' id='download_button_or_header_"+device.id+"'>eduroam and OpenRoaming\
              </div>\
            </button>\
          </div>\
          <div class='button_wrapper'>\
            <button name='"+device.id+"' class='more_info_b' id='g_info_b_"+device.id+"'>i</button>\
          </div>\
        </div>\
        <div name='"+device.id+"' class='device_info' id='info_g_"+device.id+"'>XXXXX</div>\
        <div id='more_i'><a href='javascript:showMoreDeviceInfo(\""+device.id+"\")'>See more installer information</a></div>";   
        $("#guess_os").prepend(div1);
      }
  }
    div2 ="<div id='download_another' class='sub_h guess_os'>\
    <a href='javascript:otherInstallers()'>"+guiTexts.downloadAnother+"</a>\
  </div>";
  $("#guess_os_wrapper").append(div2);
}

function showInfo(data, title) {
  if (data.substring(0,8) == 'no_title') {
    data = data.substring(8,data.length);
  } else {
    data = "<h1>"+title+"</h1>"+data;
  }
  if (roller)
    Program.stop_program = 1;
  $("#main_body").fadeTo("fast", 0.1);
  $("#main_menu_content").html(data);
  $("#main_menu_info").show('fast');
}
  
function infoCAT(k,subK,title) {
  $.post(catInfo, {page: k, subpage: subK, lang: lang}, function(data) {
    showInfo(data, title)
  });
}

function waiting(action) {
  if (action == 'start') {
    var x = getWindowHCenter() - 16;
    $("#loading_ico").css('left',x+'px');
    $("#loading_ico").attr('src','resources/images/icons/loading9.gif');
    $("#loading_ico").show();
    return;
  }
  if (action == 'stop') {
    $("#loading_ico").hide();
    return;
  }
}
  
function goAdmin() {
  waiting('start');
  window.location.replace(overviewUser+"?lang="+lang);
}

function remindIdPF() {
  mail = $("#remindIdP").val();
  key = $("#remindIdPs").val();
  if (mail == "") {
    alert(guiTexts.missingEmail);
    return;
  }
  waiting('start');
  $.get(remindIdP, {key: key, mail: mail}, function(data) {
    $("#remindIdPl").html("");
    try {
      j = JSON.parse(data);
    }
    catch(err) {
      alert(generation_error);
      return(false);
    }
    if (j.status == 0) {
      $("#remindIdPh").html(guiTexts.noProviders);
      waiting('stop');
      return;
    }
    if (j.data.length == 1) {
      $("#remindIdPh").html(guiTexts.yourIdP);
    } else {
      $("#remindIdPh").html(guiTexts.yourIdPs);
    }
    $.each(j.data, function(i, v) {
      $("#remindIdPl").append('<li>' + v + '</li>');
    });
    waiting('stop');
  });
}


/* Get horizontal center of the Browser Window */

function getWindowHCenter() {
  var windowWidth = 0;
  if ( typeof( window.innerWidth ) == 'number' ) {
    windowWidth = window.innerWidth;
  } else {
    if ( document.documentElement && document.documentElement.clientWidth ) {
      windowWidth = document.documentElement.clientWidth;
    } else {
      if ( document.body && document.body.clientWidth ) {
        windowWidth = document.body.clientWidth;
      }
    }
  }
  return(Math.round(windowWidth/2));
}

<?php if ($idpId) { 
        print "front_page = 0;\n";
} ?>

function showTOU() {
  $("#all_tou_link").hide();
  $("#tou_2").show();  
}

function back_to_downloads() {
  $("#devices").show();
  $("#user_welcome").hide();
}

function reset_footer() {
  var wh = parseInt($(window ).height());
  var mph = parseInt($("#main_page").height()) + parseInt($("#footer").css("height"));
  if (wh > mph) {
    $("#wrap").css("min-height","100%");
    $("#vertical_fill").css("height",wh - mph - 16);
    $("#vertical_fill").show();    
  } else {
    $("#wrap").css("min-height","auto");
    $("#vertical_fill").hide();
  }
  $("#institution_name").css("min-height", $("#inst_extra_text").height());
}

function processDownload(data) {
  generateTimer = $.now() - generateTimer;
  if (generateTimer < 3000)
    generateTimer = 3000 - generateTimer;
  else
    generateTimer = 0;
     
  var j;
  try {
    j = JSON.parse(data).data;
  }
  catch(err) {
    alert(generation_error);
    return(false);
  }
  if ( j.link == 0 )
    alert(generation_error);
  else {
    download_link = j.link;
    $("#download_info a").attr('href',download_link);
    $('#download_info').show();
    if ( generateTimer > 0 ) {
        setTimeout("document.location.href=download_link",generateTimer);
    }
    else {
       document.location.href=download_link;
    }
  }
}

function discoJuiceCallback(e) {
  $("#profile_desc").hide();
  $("#profile_desc").text('');
  $("#welcome_top1").hide();
  $("#top_invite").hide();
  $("#institution_name").hide();
  $("#front_page").hide();
  if (roller)
    Program.stop_program = 1;
  $(this).addClass('pressed');
  $('#welcome').hide();
  $("#inst_name_span").html("");
  $("#user_info").hide();
  $("#devices").hide();
  $("#profile_redirect").hide();
  $("#profiles").hide();
  $("#institutions").hide();
  listProfiles(e.idp,0);
}

function loadDiscoJuice() {
  var metadata;
  if (preloadIdPs)
    metadata = allIdPs;
  else
    metadata = apiURL+"?action=listAllIdentityProviders&api_version=2&lang="+lang;

  discoTextStrings.discoPath = "external/discojuice/";
  discoTextStrings.iconPath = apiURL + "?action=sendLogo&api_version=2&disco=1&lang=" + lang + "&idp=";
  discoTextStrings.overlay = true;
  discoTextStrings.cookie = true;
  discoTextStrings.type = false;
  discoTextStrings.country = true;
  discoTextStrings.location = true;
  discoTextStrings.countryAPI = apiURL+"?action=locateUser&api_version=2";
  discoTextStrings.metadata = metadata;
  discoTextStrings.metadataPreloaded = preloadIdPs;
  discoTextStrings.callback = discoJuiceCallback;
  $(".signin").DiscoJuice(discoTextStrings);
  DiscoJuice.Constants.Countries = discoCountries;
  idpsLoaded = true;
}

$(document).ready(function() {
  var j ;

  if (ie_version == 0 )
    $('body').addClass("use_borders");
  else {
  if (ie_version ==  8)
    $('body').addClass("old_ie");
  if (ie_version < 8)
    $('body').addClass("no_borders");
  if (ie_version > 9)
    $('body').addClass("no_borders");
  }
  $("#devices").hide();
  if (sbPage == 0) {
    $("#user_page").hide();
    $("#institution_name").hide();
    $("#user_info").hide();
  } else {
    $("#user_page").show();
    $("#institution_name").show();
    $("#user_info").show();
  }   
  $("#profiles").hide();
  $("#devices").hide();
  $("#download_info a").css('font-weight','bold');

  $("#profile_list").change(function(event) {
    showProfile($(this).val());
  });

  resetDevices(true);
 <?php 
    if ($idpId) {
    print "listProfiles($idpId, $profileId);";
    }
    ?>

  $(".signin").click(function(event) {
    event.preventDefault();
    if (idpsLoaded)
      return;
    waiting('start');
    const tmInt = setInterval(function() {
      if (!idpsLoaded)
        return;
      clearInterval(tmInt);
      waiting('stop');
      DiscoJuice.UI.show();
    }, 100);
  });
  $("#main_menu_close").click(function(event) {
    $("#main_menu_info").hide('fast');
    $("#main_body").fadeTo("fast", 1.0);
    if (roller) {
      Program.stop_program = 0;
      Program.nextStep();
    }
    return(false);
  });
  $("#info_menu_close").click(function(event) {
    $("#info_overlay").hide('fast');
    $("#main_body").fadeTo("fast", 1.0);
  });
  $("#hamburger").click(function(event) {
    $("#menu_top > ul").toggle();
  });
  $("#menu_top > ul >li").click(function(event) {
    if ($( window ).width() < 750 ) {
      $("#menu_top > ul").hide();
    }
  });
  catWelcome = $("#main_menu_content").html();
  if (noDisco === 0) {
    if (preloadIdPs) {
      $.get(apiURL+"?action=listAllIdentityProviders&api_version=2&lang="+lang,function(data) {
        allIdPs = data;
        loadDiscoJuice();
      }, "json");
    } else {
        loadDiscoJuice();
    }
  }
// device_button_bg = $("button:first").css('background');
  device_button_fg = $("button:first").css('color');
  if (front_page)
    if (roller)
      $("#img_roll_1").fadeOut(0);
    $("#cursor").fadeOut(0);
  if (front_page) {
    $("#front_page").show();
    if (roller)
      prepareAnimation();
  }
  $("#openroaming_check").change(function(event) {
    if ($("#openroaming_check").prop("checked") == true) {
        $("[id^='g_or_']").css("background-color", "#1d4a74");
        $("[id^='g_or_']").addClass('enabled');
        $("#device_list button.dev_or.hs20").css("background-color", "#1d4a74");
        $("#device_list button.dev_or.hs20").addClass('enabled');
    } else {
      $("[id^='g_or_']").css("background-color", "#bbb");
      $("[id^='g_or_']").removeClass('enabled');
      $("#device_list button.dev_or").css("background-color", "#bbb");
      $("#device_list button.dev_or").removeClass('enabled');
    }
  });

  reset_footer();
  $( window ).resize(function(event) {
    if ($( window ).width() > 750) {
      $("#menu_top > ul").show();
    }
    reset_footer();
  });
});
