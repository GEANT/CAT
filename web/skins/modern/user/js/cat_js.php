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


$cat = new \web\lib\user\Gui();
$idpId = filter_input(INPUT_GET, 'idp', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'idp', FILTER_VALIDATE_INT) ?? 0;
$profileId = filter_input(INPUT_GET, 'profile', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'profile', FILTER_VALIDATE_INT) ?? 0;
$skinObject = $Gui->skinObject;
if (\config\ConfAssistant::PRELOAD_IDPS) {
    print "const preloadIdPs = true;\n";
} else {
    print "const preloadIdPs = false;\n";
}
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
var openroaming = '';
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
    "continue": "<?php $cat->javaScriptEscapedEcho(_("Continue")) ?>",
    "close": "<?php $cat->javaScriptEscapedEcho(_("Close")) ?>",
    "noProviders": "<?php $cat->javaScriptEscapedEcho(_("No providers found for this email")) ?>",
    "yourIdP": "<?php $cat->javaScriptEscapedEcho(_("Your IdP is:")) ?>",
    "yourIdPs": "<?php $cat->javaScriptEscapedEcho(_("Your IdP could be one of:")) ?>",
    "missingEmail": "<?php $cat->javaScriptEscapedEcho(_("Missing email address")) ?>",
    "entryUpdate": "<?php $cat->javaScriptEscapedEcho(_("This entry was last updated at:")) ?>",
    "openRoamingTouWarning": "<?php $cat->javaScriptEscapedEcho(_("If you intend to download an installer which also enables OpenRoaming then you must accept OpenRoaming Terms and Conditions.")) ?>",
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

function other_installers() {
  $("#guess_os").hide();
  $("#other_installers").show();
  $("#devices").redraw();
  reset_footer();
}

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
    j = $.parseJSON(data);
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
    if (n > 1 && selected_profile) {
      var theProfile = $('#profile_list option[value='+selected_profile+']');
      if ( theProfile.length == 1) { 
        theProfile.attr("selected",true);
        showProfile(selected_profile);
        $("#devices").show();
      } 
    }
    reset_footer();
  });
}

function printP(i,v) {
  if (n == 1 ) {
    $("#profiles").hide();
    $("#profile_list").append('<option value="'+v.profile+'" selected>'+v.display+'</option>');
    showProfile(v.profile);
//     $("#devices").show();
  } else {
    $("#profile_list").append('<option value="'+v.profile+'">'+v.display+'</option>');
  }
}

function resetDevices(initial) {
  if (recognisedOS !== '' ) {
    $("#other_installers").hide();
    $("#openroaming_check").prop("checked", false);
    switch (openroaming) {
      case 'none':
        $("#download_button_header_"+recognisedOS).html("eduroam");
        $("#g_"+recognisedOS).show();
        $("#g_or_"+recognisedOS).hide();
        $("#openroaming_tou").hide();
        break;
      case 'ask':
        $("#download_button_header_"+recognisedOS).html("eduroam only");
        $("#g_"+recognisedOS).show();
        $("#g_or_"+recognisedOS).css("background-color", "#aaa");
        $("#g_or_"+recognisedOS).show();
        $("#openroaming_tou").show();
        break;
      case 'always':
        $("#download_button_header_"+recognisedOS).html("eduroam only");
        $("#g_"+recognisedOS).hide();
        $("#g_or_"+recognisedOS).css("background-color", "#aaa");
        $("#g_or_"+recognisedOS).show();
        $("#openroaming_tou").show();
        break;
    }
    if (!initial)
        $("#guess_os").show();
//    $("#download_button_header_"+recognisedOS).html(downloadMessage);
    $("#cross_icon_"+recognisedOS).hide();
  }
  $(".device_list button").removeClass('alertButton');
  $(".device_list button").removeClass('disabledDevice');
  $(".device_list button").removeClass('additionalInfo');
  $('.device_list button').unbind('click');
  $('.device_list tr').show();
  $('.device_info').html('');
  $('.device_info').hide();
  $('.openroaming_selection').hide();
  $("#user_welcome").hide();
  $(".device_list button").click(function(event) {
    var j;
    var or = false;
    event.preventDefault();
    var button_id = $(this).attr('id');
    var info_id = 'info_'+button_id;
    if (button_id.substr(0,5) == "g_or_") {
      or = true;
      button_id = button_id.substr(5);
      info_id = 'info_g_'+button_id;
    } else if (button_id.substr(0,2) == "g_") {
      button_id = button_id.substr(2);
      info_id = 'info_g_'+button_id;
    }
    if (button_id.substr(0,7) == "info_b_") { // the info button
      var device_id = button_id.substr(7);
      $("#info_window").html("<h2>"+$('#'+device_id).text()+"</h2>");
      $.post(apiURL, {action: 'deviceInfo', api_version: 2, lang: lang, device: device_id, profile: profile, openroaming: openroaming}, function(data) {
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
      });
    } else { // the download button
      $('.device_info').html('');
      $('.device_info').hide();
      $('.openroaming_selection').hide();
      pressedButton = $(this);
      if ($(this).hasClass('additionalInfo')) {
        openroaming_id = 'openroaming_'+pressedButton.attr('id');
        $('#'+openroaming_id).show();  // TMW - temporary hack
        $('#'+info_id).show(100);
      } else {
        $('#download_info').hide();
        generateTimer = $.now();
        $("#devices").hide();
        $("#user_welcome").show();
        $.post(apiURL, {action: 'generateInstaller', api_version: 2, lang: lang, device: button_id, profile: profile, openroaming: openroaming}, processDownload);
      }
    }
  });   
} 

function showProfile(prof) {
  $("#profile_redirect").hide();
  $("#silverbullet").hide();
  if (prof == 0) {
    $("#user_info").hide();
    $("#devices").hide();
    return;
  }
  var j, txt ;
  var redirect_profile;
  redirect_profile = '0';
  profile = prof;
  $("#profile_id").val(prof);
  txt = '';
  $.post(apiURL, {action: 'profileAttributes', api_version: 2, lang: lang, profile: profile}, function(data) {
    j1 = $.parseJSON(data);
    result = j1.status;
    if (! result) {
      alert(guiTexts.noMatchingData);
      document.location.href='<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/' ?>';
    }
    j = j1.data;
    if (j.description !== undefined && j.description) {
      $("#profile_desc").text(j.description);
      $("#profile_desc").css("display","inline-block");
       //  $("#profile_desc").show();
    } else {
      $("#profile_desc").hide();
      $("#profile_desc").text('');
    }
    updateTxt = guiTexts.entryUpdate+' '+j.last_changed+'</span><br/>';
    if (j.openroaming !== undefined) {
      openroaming = j.openroaming;
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
    resetDevices(false);
    $.each(j.devices,function(i,v) {
      // test if we have a global profile redirect
      if (v.id == 0) {
        redirect_profile = v.redirect;
      } else {
        if (v.status > 0 && v.redirect == '0') {
          $("#g_"+v.id).addClass('alertButton');
          $("#cross_icon_"+v.id).show();
          $("#"+v.id).addClass('disabledDevice');
          $("#download_button_header_"+v.id).html(guiTexts.unconfigurable);
          $("#info_b_"+v.id+",#g_info_b_"+v.id).hide();
        } else  {
          if (v.status == -1)
            $("#"+v.id).parent().parent().hide();
          else
            if ($( window ).width() > 389 )
               $("#info_b_"+v.id+",#g_info_b_"+v.id).show();
        }
        if (v.redirect != '0') {
          $("#"+v.id+",#g_"+v.id).addClass('additionalInfo');
          $("#"+v.id+",#g_or_"+v.id).addClass('additionalInfo');
          $("#"+v.id+",#g_"+v.id).click(function(event) {
            i_div = $("#info_"+$(this).attr('id'));
            t = guiTexts.redirect+"<br><span class='redirect_link'><a href='"+v.redirect+"' target='_blank'>" + guiTexts.continue + "</a></span>";
            i_div.html(t);
            $(".redirect_link").click(function(event) {
              i_div.hide();
            });           
          });
        } else if (v.device_customtext != '0' || v.eap_customtext != '0' || v.message != '0' || v.status > 0) {
          var continue_text = guiTexts.continue;
          $("#"+v.id+",#g_"+v.id).addClass('additionalInfo');
          $("#"+v.id+",#g_or_"+v.id).addClass('additionalInfo');
          $("#"+v.id+",#g_"+v.id+",#g_or_"+v.id).click(function(event) {
            if ($(this).attr('id').substr(0,2) == "g_")
              i_div = $("#info_g_"+v.id);
            else
              i_div = $("#info_"+$(this).attr('id'));
            if ($(this).attr('id').substr(0,5) == "g_or_") {
              if ($("#openroaming_check").prop("checked") == false) {
                i_div.hide();  
                alert(guiTexts.openRoamingTouWarning);
                return;
              }
            }

            if (v.status > 0) {
              t = guiTexts.unconfigurable;
              continue_text = guiTexts.close;
            } else {
              t = i_div.html();
              if (v.message != '0') {
                if (t != '')
                  t += '<br>';
                t +=  v.message;
              }
              if (v.device_customtext != '0') {
                if (t != '')
                  t += '<br>';
                t +=  v.device_customtext;
              }
              if (v.eap_customtext != '0') {
                if (t != '')
                  t += '<br/>&nbsp;<br/>';
                t +=  v.eap_customtext;
              }
            }
            t += "<br><span class='redirect_link'>"+continue_text+"</span>";
            i_div.html(t);
            $(".redirect_link").click(function(event) {
              i_div.hide('fast');
              var dev_id = pressedButton.attr('id');
              var setOpenRoaming = 0;
              
              if (dev_id.substr(0,5) == "g_or_" && $("#openroaming_check").prop("checked") == true) {
                setOpenRoaming = 1;
                dev_id = dev_id.substr(5);
              } else if (dev_id.substr(0,2) == "g_") {
                dev_id = dev_id.substr(2);
              }
              if (v.status == 0) {
                $('#download_info').hide();
                $("#devices").hide();
                generateTimer = $.now();
                $("#user_welcome").show();
                $.post(apiURL, {action: 'generateInstaller', api_version: 2, lang: lang, device: dev_id, openroaming: setOpenRoaming, profile: profile}, processDownload); 
              }
            });           
          });
        }
      }
    });
    if (redirect_profile == 0) {
      $("#devices").show();
    } else {
      $("#devices").hide();
      $("#user_info").hide();
      $("#profile_redirect_bt").attr('href',redirect_profile);
      $("#profile_redirect").show();
    }
    reset_footer();
 })
 }

function changeLang(l) {
  $("#lang").val(l);
  document.cat_form.submit();
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
      j = $.parseJSON(data);
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
    j = $.parseJSON(data).data;
  }
  catch(err) {
    alert(generation_error);
    return(false);
  }
  if ( j.link == 0 )
    alert(generation_error);
  else {
    download_link = apiURL+'?action=downloadInstaller&api_version=2&lang='+lang+'&device='+j.device+'&profile='+j.profile;
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
    if ($("#openroaming_check").prop("checked") == true) 
      $(this).parent().prev().css("background-color", "#1d4a74");
    else
      $(this).parent().prev().css("background-color", "#aaa");
  });
  reset_footer();
  $( window ).resize(function(event) {
    if ($( window ).width() > 750) {
      $("#menu_top > ul").show();
    }
    reset_footer();
  });
});
