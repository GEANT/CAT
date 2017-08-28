<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */
?>
<?php 
function escaped_echo($s) {
   echo preg_replace('/"/','&quot;',$s);
}

$langObject = new \core\common\Language();
$langObject->setTextDomain('web_user');
$cat = new core\CAT();
$idpId = empty($_REQUEST['idp']) ? 0 : $_REQUEST['idp'];
if (! is_numeric($idpId)) {
    exit;
}
$profileId = empty($_REQUEST['profile']) ? 0 : $_REQUEST['profile'];
if (! is_numeric($profileId)) {
    exit;
}
 ?>
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
var profile_list_size = <?php echo $profile_list_size ?>;
var generation_error = "<?php escaped_echo(_("This is embarrassing. Generation of your installer failed. System admins have been notified. We will try to take care of the problem as soon as possible.")) ?>";

   $.fn.redraw = function(){
  $(this).each(function(){
    var redraw = this.offsetHeight;
  });
};

   function other_installers() {
     $("#guess_os").hide();
     $("#other_installers").show();
     $("#devices").redraw();
   }

   function listProfiles(inst_id,selected_profile){
    var j ;
    $('#welcome').hide();
    $('#user_welcome').hide();
    $("#idp_logo").hide();
    $("#inst_id").val(inst_id);
    $("#profile_id").val('');
    $(".signin_large").hide();
    Program.stop_program = 1;
    $("#profiles").hide();
    $("#user_info").hide();
    $("#devices").hide();
    $("#profile_redirect").hide();
    i_s = selected_profile;
      $.post('user/API.php', {action: 'listProfiles', api_version: 2, lang: lang, idp: inst_id}, function(data) {
//alert(data);
    j = $.parseJSON(data);
    result = j.status;
    if(! result) {
      alert("<?php escaped_echo(_("no matching data found"))?>");
      document.location.href='<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/'?>';
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
    if(n > profile_list_size)
    $("#profile_list").append('<option value="0" selected style="color:red"> --<?php escaped_echo(_("select"))?> --</option>');
    $.each(j,printP);
    if(n <= profile_list_size)
    $("#profile_list").append('<option value="0" selected style="display:none"> </option>');
    if(logo) {
    $("#idp_logo").attr("src","user/API.php?action=sendLogo&api_version=2&idp="+inst_id);
    $("#idp_logo").show();
    }
    if (n > 1) {
       if(n <= profile_list_size) {
       $("#profile_list").attr('size',n+1);
       } else {
       $("#profile_list").attr('size',1);
       }
      $("#profiles").show();
     }
     if(n > 1 && selected_profile) {
       $('#profile_list option[value='+selected_profile+']').attr("selected",true);
       showProfile(selected_profile);
       $("#devices").show();
     }
      });
   }
function printP(i,v) {
  if(n == 1 ) {
     $("#profiles").hide();
     $("#profile_list").append('<option value="'+v.profile+'" selected>'+v.display+'</option>');
     showProfile(v.profile);
//     $("#devices").show();
  } else {
     $("#profile_list").append('<option value="'+v.profile+'">'+v.display+'</option>');
  }
}
function resetDevices() {
 if(recognisedOS !== '' ) {
    $("#guess_os").show();
    $("#other_installers").hide();
    $("#download_button_header_"+recognisedOS).html(downloadMessage);
    $("#cross_icon_"+recognisedOS).hide();
 }
 $(".device_list button").removeClass('alertButton');
 $(".device_list button").removeClass('disabledDevice');
 $(".device_list button").removeClass('additionalInfo');
 $('.device_list button').unbind('click');
 $('.device_list tr').show();
 $('.device_info').html('');
 $('.device_info').hide();
 $("#user_welcome").hide();
  $(".device_list button").click(function(event){
  var j ;
  event.preventDefault();
  var button_id = $(this).attr('id');
  if(button_id.substr(0,2) == "g_")
    button_id = button_id.substr(2);
  if(button_id.substr(0,7) == "info_b_") {
    var device_id = button_id.substr(7);
    $("#info_window").html("<h2>"+$('#'+device_id).text()+"</h2>");
  $.post('user/API.php', {action: 'deviceInfo', api_version: 2, lang: lang, device: device_id, profile: profile}, function(data) {
    var h = $("#info_window").html();
    $("#info_window").html(h+data);
    $("#main_body").fadeTo("fast", 0.2,function() {
    var x = getWindowHCenter() - 350;
    $("#info_overlay").show();
}
);
});
  } else {
     $('.device_info').html('');
      $('.device_info').hide();
      if($(this).hasClass('disabledDevice')) 
        $(this).addClass('pressedDisabled');
      else 
        $(this).addClass('pressed');
      if($(this).hasClass('additionalInfo')) {
        $('#'+info_id).show(100);
      } else {
        $('#download_info').hide();
        generateTimer = $.now();
        $("#devices").hide();
        $("#user_welcome").show();
        $.post('user/API.php', {action: 'generateInstaller', api_version: 2, lang: lang, device: button_id, profile: profile}, processDownload);
     }
  }
}); 
   
} 

   function showProfile(prof){
     $("#profile_redirect").hide();
     if(prof == 0) {
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
     $.post('user/API.php', {action: 'profileAttributes', api_version: 2, lang: lang, profile: profile}, function(data) {
       j1 = $.parseJSON(data);
       result = j1.status;
       if(! result) {
            alert("<?php escaped_echo( _("no matching data found"))?>");
            document.location.href='<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/'?>';
       }
       j = j1.data;
       if(j.description !== undefined && j.description) {
         $("#profile_desc").text(j.description);
         $("#profile_desc").show();
       } else {
         $("#profile_desc").hide();
         $("#profile_desc").text('');
       }
       if(j.local_url !== undefined && j.local_url) 
         txt = txt+'<tr><td><?php escaped_echo(_("WWW:"));?></td><td><a href="'+j.local_url+'" target="_blank">'+j.local_url+'</a></td></tr>';
       if(j.local_email !== undefined && j.local_email) 
         txt = txt+'<tr><td><?php escaped_echo(_("email:"));?></td><td><a href=mailto:"'+j.local_email+'">'+j.local_email+'</a></td></tr>';
       if(j.local_phone !== undefined && j.local_phone) 
         txt = txt+'<tr><td><?php escaped_echo(_("tel:"));?></td><td>'+j.local_phone+'</td></tr>';
       if(txt) 
         txt = "<table><tr><th colspan='2'><?php escaped_echo(sprintf(_("If you encounter problems, then you can obtain direct assistance from your %s at:"),$cat->nomenclature_inst)); ?></th></tr>"+txt+'</table>';
        else 
         txt = "<table><tr><th colspan='2'><?php escaped_echo(sprintf(_("If you encounter problems you should ask for help at your %s"),$cat->nomenclature_inst)); ?>.</th></tr></table>";
      $("#user_info").html(txt);
      $("#user_info").show();
      resetDevices();
      $.each(j.devices,function(i,v) {
      // test if we have a global profile redirect
       if(v.id == 0) {
          redirect_profile = v.redirect;
       } else {
        if(v.status > 0 && v.redirect == '0') {
          $("#g_"+v.id).addClass('alertButton');
          $("#cross_icon_"+v.id).show();
          $("#"+v.id).addClass('disabledDevice');
          $("#download_button_header_"+v.id).html("<?php escaped_echo(sprintf(_("This device cannot be configured with settings provided by your %s"),$cat->nomenclature_inst))?>");
          $("#info_b_"+v.id+",#g_info_b_"+v.id).hide();
        } else  {
          if(v.status == -1)
            $("#"+v.id).parent().parent().hide();
          else
            $("#info_b_"+v.id+",#g_info_b_"+v.id).show();
        }
        if(v.redirect != '0') {
          $("#"+v.id+",#g_"+v.id).addClass('additionalInfo');
          $("#"+v.id+",#g_"+v.id).click(function(event){
            i_div = $("#info_"+$(this).attr('id'));
            t = "<?php escaped_echo(_("Your site administrator has specified that this device should be configured with resources located on a local page. When you click <b>Continue</b> this page will be opened in a new window/tab."))?>"+"<br><span class='redirect_link'><a href='"+v.redirect+"' target='_blank'><?php escaped_echo(_("Continue"));?></a></span>";
            i_div.html(t);
            $(".redirect_link").click(function(event) {
               i_div.hide();
            });
               
          });
        } else if(v.device_customtext != '0' || v.eap_customtext != '0' || v.message != '0' || v.status > 0) {
          var continue_text = "<?php escaped_echo(_("Continue"));?>";
          $("#"+v.id+",#g_"+v.id).addClass('additionalInfo');
          $("#"+v.id+",#g_"+v.id).click(function(event){
            i_div = $("#info_"+$(this).attr('id'));
            if(v.status > 0) {
              t = "<?php escaped_echo(sprintf(_("This device cannot be configured with settings provided by your %s"),$cat->nomenclature_inst))?>";
              continue_text = "<?php escaped_echo(_("Close"));?>";
            } else {
            t = i_div.html();
            if(v.message != '0') {
                if (t != '')
                  t += '<br>';
                t +=  v.message;
            }
            if(v.device_customtext != '0') {
                if (t != '')
                  t += '<br>';
                t +=  v.device_customtext;
            }
            if(v.eap_customtext != '0') {
                if (t != '')
                  t += '<br/>&nbsp;<br/>';
                t +=  v.eap_customtext;
            }
            }
               t += "<br><span class='redirect_link'>"+continue_text+"</span>";
            i_div.html(t);
            $(".redirect_link").click(function(event) {
               i_div.hide('fast');
               if(dev_id.substr(0,2) == "g_")
                  dev_id = dev_id.substr(2);
               if(v.status == 0) {
               $('#download_info').hide();
               $("#devices").hide();
               generateTimer = $.now();
               $("#user_welcome").show();
               $.post('user/API.php', {action: 'generateInstaller', api_version: 2, lang: lang, device: dev_id, profile: profile}, processDownload); 
               }
            });
               
          });
        }
      }
      });
   if(redirect_profile == 0) {
      $("#devices").show();
   } else {
      $("#devices").hide();
      $("#user_info").hide();
      $("#profile_redirect_bt").attr('href',redirect_profile);
      $("#profile_redirect").show();
   }
   })
  }

  function changeLang(l) {
    $("#lang").val(l);
    document.cat_form.submit();
  }

  function infoCAT(k,subK,title) {
      $.post('<?php echo $Gui->skinObject->findResourceUrl("BASE","user/cat_info.php")?>', {page: k, subpage: subK, lang: lang}, function(data) {
    if(data.substring(0,8) == 'no_title') {
       data = data.substring(8,data.length);
    } else {
       data = "<h1>"+title+"</h1>"+data;
    }
    Program.stop_program = 1;
//    $("#welcome_top1").css('visibility','hidden');
//    $("#top_invite").css('visibility','hidden');
//    $("#main_body").css('visibility','hidden');
    $("#main_body").fadeTo("fast", 0.1);
//    $("#signin").hide();
    $("#main_menu_content").html(data);
    $("#main_menu_info").show('fast');
   });
  }

  function goAdmin() {
   var x = getWindowHCenter() - 16;
   $("#loading_ico").css('left',x+'px');
   $("#loading_ico").attr('src','resources/images/icons/loading9.gif');
   $("#loading_ico").show();
   window.location.replace("<?php echo $Gui->skinObject->findResourceUrl("BASE","admin/overview_user.php")?>?lang="+lang);
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

<?php if($idpId) { 
      print "front_page = 0;\n";
} ?>

function showTOU(){
  $("#all_tou_link").hide();
  $("#tou_2").show();
   
}

function back_to_downloads() {
    $("#devices").show();
    $("#user_welcome").hide();
}


function processDownload(data) {
   generateTimer = $.now() - generateTimer;
   if(generateTimer < 3000)
     generateTimer = 3000 - generateTimer;
   else
     generateTimer = 0;
     
  var j;
//alert(data);
  try {
    j = $.parseJSON(data).data;
  }
  catch(err) {
    alert(generation_error);
    return(false);
  }
  if( j.link == 0 )
    alert(generation_error);
  else {
    download_link = 'user/API.php?action=downloadInstaller&api_version=2&lang='+lang+'&device='+j.device+'&profile='+j.profile;
    $("#download_info a").attr('href',download_link);
    $('#download_info').show();
    if( generateTimer > 0 ) {
       setTimeout("document.location.href='<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']),'/')?>'+'/'+download_link",generateTimer);
    }
    else {
       document.location.href='<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']),'/')?>'+'/'+download_link;
    }
  }
}

$(document).ready(function(){
   var j ;

   if(ie_version == 0 )
     $('body').addClass("use_borders");
   else {
   if(ie_version ==  8)
     $('body').addClass("old_ie");
   if(ie_version < 8)
     $('body').addClass("no_borders");
   if(ie_version > 9)
     $('body').addClass("no_borders");
   }

   $("#user_page").hide();
   $("#institution_name").hide();
   $("#profiles").hide();
   $("#user_info").hide();
   $("#devices").hide();
   $("#download_info a").css('font-weight','bold');

   $("#profile_list").change(function(event){
     showProfile($(this).val());
  });

  resetDevices();
 <?php 
if ($profileId) {
    print "listProfiles($idpId,$profileId);";
}
 ?>

$(".signin").click(function(event){
     event.preventDefault();
});

$("#main_menu_close").click(function(event){
    $("#main_menu_info").hide('fast');
    $("#main_body").fadeTo("fast", 1.0);
    Program.stop_program = 0;
    Program.nextStep();
  return(false);
});

$("#info_menu_close").click(function(event){
    $("#info_overlay").hide('fast');
    $("#main_body").fadeTo("fast", 1.0);
});

$("#hamburger").click(function(event){
	   $("#menu_top > ul").toggle();
});

$("#menu_top > ul >li").click(function(event){
           if ($( window ).width() < 750 ) {
               $("#menu_top > ul").hide();
           }
});


catWelcome = $("#main_menu_content").html();
  
$(".signin").DiscoJuice({
   "discoPath":"external/discojuice/",
   "iconPath":"user/API.php?action=sendLogo&api_version=2&disco=1&lang=en&idp=",
   "overlay":true,"cookie":true,"type":false,
   "country":true,"location":true,
   "title":"<?php escaped_echo($cat->nomenclature_inst) ?>",
   "subtitle":"<?php escaped_echo(sprintf(_("Select your <strong>%s<\/strong>"),$cat->nomenclature_inst)) ?>",
   "textHelp": "<?php escaped_echo(sprintf(_("Help, my %s is not on the list"),$cat->nomenclature_inst)) ?>",
   "textHelpMore": "<?php escaped_echo(sprintf(_("This system relies on information supplied by local %s administrators. If your %s is not on the list, then nag them to add information to the %s database."),CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'], $cat->nomenclature_inst, CONFIG['APPEARANCE']['productname'])); ?>",
   "textLocateMe": "<?php escaped_echo(_("Locate me more accurately using HTML5 Geo-Location")) ?>",
   "textShowProviders": "<?php escaped_echo(sprintf(_("Show %ss in"), $cat->nomenclature_inst)) ?>",
   "textAllCountries": "<?php escaped_echo(_("all countries")) ?>",
   "textSearch" : "<?php escaped_echo(sprintf(_("or search for an %s, in example Univerity of Oslo"),$cat->nomenclature_inst)) ?>",
   "textShowAllCountries": "<?php escaped_echo(_("show all countries")) ?>",
   "textLimited1" : "<?php escaped_echo(_("Results limited to"))?>",
   "textLimited2" : "<?php escaped_echo(_("entries - show more"))?>",
   "textNearby" : "<?php escaped_echo(_("Nearby"))?>",
   "geoLoc_timeout" : "<?php escaped_echo(_("Location timeout"))?>",
   "geoLoc_posUnavailable" : "<?php escaped_echo(_("Could not get your position"))?>",
   "geoLoc_permDenied" : "<?php escaped_echo(_("Your browser has denied access to your location"))?>",
   "geoLoc_unknownError" : "<?php escaped_echo(_("Unknown location error"))?>",
   "geoLoc_here" : "<?php escaped_echo(_("You are here:"))?>",
   "geoLoc_getting" : "<?php escaped_echo(_("Getting your location..."))?>",
   "geoLoc_nearby" : "<?php escaped_echo(_("Nearby providers shown on top."))?>",
   "countryAPI":"user/API.php?action=locateUser&api_version=2",
   "metadata":"user/API.php?action=listAllIdentityProviders&api_version=2&lang="+lang,
   "callback": function(e) {
     $("#profile_desc").hide();
     $("#profile_desc").text('');
     $("#welcome_top1").hide();
     $("#top_invite").hide();
     $("#institution_name").hide();
     $("#front_page").hide();
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
        });
DiscoJuice.Constants.Countries = {
<?php 
  $C = $Gui->printCountryList(1);
     $ret = '';
   foreach ($C as $key => $val) {
    $ret .= "'$key': \"$val\",";
   }
   echo substr($ret, 0, -1);
?>
        };


// device_button_bg = $("button:first").css('background');
 device_button_fg = $("button:first").css('color');

if(front_page)
   $("#img_roll_1").fadeOut(0);
   $("#cursor").fadeOut(0);
if(front_page) {
   $("#fron_page").show();
   prepareAnimation();
 }

//alert($("#welcome_top1").css('display'));

$( window ).resize(function(event) {
   if ($( window ).width() > 750) {
      $("#menu_top > ul").show();
   }
});

 });
