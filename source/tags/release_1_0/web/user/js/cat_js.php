<?php
/***********************************************************************************
 * (c) 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * License: see the LICENSE file in the root directory
 ***********************************************************************************/
?>
<?php $Gui->set_locale('web_user'); ?>
var n;
var profile;
var device_button_bg ="#0a698e";
var device_button_fg;
var pressedButton;
var catWelcome;
var front_page = 1;
var profile_list_size = <?php echo $profile_list_size ?>;
var generation_error = '<?php echo _("This is embarrassing. Generation of your installer failed. System admins have been notified. We will try to take care of the problem as soon as possible.") ?>';

   function listProfiles(inst_id,inst_name,selected_profile){
    var j ;
    $('#welcome').hide();
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
      $.post('user/cat_back.php', {action: 'listProfiles', lang: lang, id: inst_id}, function(data) {
    j = $.parseJSON(data);
    result = j.shift();
    if(! result) {
      alert("<?php echo _("no maching data found")?>");
      document.location.href='<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/'?>';
    }
    n = j.length;
    $("#profile_list").html('');
    inst_name = j[0].inst_name;
    logo = j[0].logo;
    $("#inst_name").val(inst_name);
    $("#inst_name_span").html("<?php echo _("Selected institution:")?> <strong>"+inst_name+"</strong>");
    $("#institution_name").show();
    if(n > profile_list_size)
    $("#profile_list").append('<option value="0" selected style="color:red"> --<?php echo _("select")?> --</option>');
    $.each(j,printP);
    if(n <= profile_list_size)
    $("#profile_list").append('<option value="0" selected style="display:none"> </option>');
    if(logo) {
    $("#idp_logo").attr("src","user/cat_back.php?action=sendLogo&id="+inst_id);
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
     $("#profile_list").append('<option value="'+v.identifier+'" selected>'+v.name+'</option>');
     showProfile(v.identifier);
     $("#devices").show();
  } else {
     $("#profile_list").append('<option value="'+v.identifier+'">'+v.name+'</option>');
  }
}
function resetDevices() {
 $("#device_list button").removeClass('disabledDevice');
 $("#device_list button").removeClass('additionalInfo');
 $('#device_list button').unbind('click');
 $('.device_info').html('');
 $('.device_info').hide();
  $("#device_list button").click(function(event){
  var j ;
  event.preventDefault();
  if($(this).attr('id').substr(0,7) == "info_b_") {
    var device_id = $(this).attr('id').substr(7);
    $("#info_window").html("<h2>"+$('#'+device_id).text()+"</h2>");
  $.post('user/cat_back.php', {action: 'deviceInfo', lang: lang, id: device_id, profile: profile}, function(data) {
    var h = $("#info_window").html();
    $("#info_window").html(h+data);
    $("#main_body").fadeTo("fast", 0.2,function() {
    var x = getWindowHCenter() - 350;
   $("#info_overlay").css('left',x+'px');
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
  pressedButton = $(this);
  setTimeout("pressedButton.removeClass('pressed pressedDisabled')", 1000);
  if($(this).hasClass('additionalInfo')) {
    info_id = 'info_'+pressedButton.attr('id');
    $('#'+info_id).show(100);
  } else {
    $.post('user/cat_back.php', {action: 'generateInstaller', lang: lang, id: $(this).attr('id'), profile: profile}, function(data) {
  setTimeout("pressedButton.removeClass('pressed pressedDisabled')", 1000);
  try {
    j = $.parseJSON(data);
  }
  catch(err) {
    alert(generation_error);
    return(false);
  }
  if( j.link == 0 )
    alert(generation_error);
  else
    document.location.href='<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']),'/')?>'+'/'+j.link;
 });
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
     $.post('user/cat_back.php', {action: 'profileAttributes', lang: lang, id: profile}, function(data) {
       j = $.parseJSON(data);
       if(j.description !== undefined && j.description) {
         $("#profile_desc").text(j.description);
         $("#profile_desc").show();
       } else {
         $("#profile_desc").hide();
         $("#profile_desc").text('');
       }
       if(j.local_url !== undefined && j.local_url) 
         txt = txt+'<tr><td><?php echo _("WWW:");?></td><td><a href="'+j.local_url+'" target="_blank">'+j.local_url+'</a></td></tr>';
       if(j.local_email !== undefined && j.local_email) 
         txt = txt+'<tr><td><?php echo _("email:");?></td><td><a href=mailto:"'+j.local_email+'">'+j.local_email+'</a></td></tr>';
       if(j.local_phone !== undefined && j.local_phone) 
         txt = txt+'<tr><td><?php echo _("tel:");?></td><td>'+j.local_phone+'</td></tr>';
       if(txt) 
         txt = "<table><tr><th colspan='2'><?php echo _("If you encounter problems, then you can obtain direct assistance from you home organisation at:"); ?></th></tr>"+txt+'</table>';
        else 
         txt = "<table><tr><th colspan='2'><?php echo _("If you encounter problems you should ask for help at your home institution"); ?>.</th></tr></table>";
      $("#user_info").html(txt);
      $("#user_info").show();
      resetDevices();
      $.each(j.devices,function(i,v) {
      // test if we have a global profile redirect
       if(v.id == 0) {
          redirect_profile = v.redirect;
       } else {
        if(v.status > 0 && v.redirect == '0') {
          $("#"+v.id).addClass('disabledDevice');
          $("#info_b_"+v.id).hide();
        } else 
          $("#info_b_"+v.id).show();
        if(v.redirect != '0') {
          $("#"+v.id).addClass('additionalInfo');
          $("#"+v.id).click(function(event){
            i_div = $("#info_"+$(this).attr('id'));
            t = "<?php echo _("Your site administrator has specified that this device should be configured with resources located on a local page. When you click <b>Continue</b> this page will be opened in a new window/tab.")?>"+"<br><span class='redirect_link'><a href='"+v.redirect+"' target='_blank'><?php echo _("Continue");?></a></span>";
            i_div.html(t);
            $(".redirect_link").click(function(event) {
               i_div.hide();
            });
               
          });
        } else if(v.device_customtext != '0' || v.eap_customtext != '0' || v.status > 0) {
          $("#"+v.id).addClass('additionalInfo');
          $("#"+v.id).click(function(event){
            i_div = $("#info_"+$(this).attr('id'));
            if(v.status > 0) {
              t = "<?php echo _("This device cannot be configured with settings provided by your institution")?>";
            } else {
            t = i_div.html();
            if(v.device_customtext != '0') {
                if (t != '')
                  t += '<br>';
                t +=  v.device_customtext;
            }
            if(v.eap_customtext != '0') {
                if (t != '')
                  t += '<p>';
                t +=  v.eap_customtext;
            }
            }
               t += "<br><span class='redirect_link'><?php echo _("Continue");?></span>";
            i_div.html(t);
            $(".redirect_link").click(function(event) {
               i_div.hide('fast');
               if(v.status == 0) 
               $.post('user/cat_back.php', {action: 'generateInstaller', lang: lang, id: pressedButton.attr('id'), profile: profile}, function(data) {
               j = $.parseJSON(data);
               document.location.href=j.link;
              });
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

  function infoCAT(k,title) {
      $.post('user/cat_info.php', {page: k, title:title, lang: lang}, function(data) {
    Program.stop_program = 1;
    $("#slides").css('visibility','hidden');
    $("#signin").hide();
    $("#main_menu_content").html(data);
    $("#main_menu_info").show('fast');
   });
  }

  function goAdmin() {
   var x = getWindowHCenter() - 16;
   $("#loading_ico").css('left',x+'px');
   $("#loading_ico").attr('src','resources/images/icons/loading9.gif');
   $("#loading_ico").show();
   window.location.replace("admin/overview_user.php?lang="+lang);
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

<?php if(isset($_REQUEST['idp']) && $_REQUEST['idp']) { 
      print "front_page = 0;\n";
} ?>

function showTOU(){
  $("#all_tou_link").hide();
  $("#tou_2").show();
   
}

$(document).ready(function(){
   var j ;
   var h1, h2;
   var w1;
   var w2;
   var w3;
   $(".signin_large").height($("#user_button").height() + 25);
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

   $("#institution_name").hide();
   $("#profiles").hide();
   $("#user_info").hide();
   $("#devices").hide();

   $("#profile_list").change(function(event){
     showProfile($(this).val());
  });
  if(front_page) {
  $(window).resize(function() {
   var w1, w2, w3;
   $("#front_page").height($(window).height() - h2);
   w1 = Math.round($(document).width()/2);
   $("#user_button1").width(w1);
   w2 = w1 - $("#eu_text").width();
   w3 = w1 + w2 - 140;
   $("#logos").css('left',w3+'px');
   $(".signin_large").height($("#user_button").height() + 25);
   $("#user_button_td").css('padding-top',Math.min(Math.max($(window).height() - 550,30),150));
  });
  }

  resetDevices();
 <?php 
if(isset($_REQUEST['idp']) && $_REQUEST['idp']) { 
    $p_id = (isset($_REQUEST['profile']) && $_REQUEST['profile']) ? $_REQUEST['profile'] : 0; 
   print 'listProfiles('.$_REQUEST['idp'].',"'.$_REQUEST['inst_name'].'",'.$p_id.');';
}
 ?>

$(".signin").click(function(event){
     pressedButton = $(this);
     setTimeout("pressedButton.removeClass('pressed')", 1000);
});

$("#main_menu_close").click(function(event){
   $("#main_menu_info").hide('fast');
    $("#slides").css('visibility','visible');
    $("#signin").show();
    Program.stop_program = 0;
    Program.nextStep();
  return(false);
});

catWelcome = $("#main_menu_content").html();
  
$(".signin").DiscoJuice({
   "discoPath":"external/discojuice/",
   "iconPath":"user/cat_back.php?action=sendLogo&disco=1&lang=en&id=",
   "overlay":true,"cookie":true,"type":false,
   "country":true,"location":true,
   "title":"<?php echo _("Home institution") ?>",
   "subtitle":"<?php echo _("Select your <strong>institution<\/strong>") ?>",
   "textHelp": "<?php echo _("Help, my institution is not on the list") ?>",
   "textHelpMore": "<?php echo sprintf(_("This system relies on information supplied by local %s administrators. If your institution is not on the list, then nag them to add information to the %s database."),Config::$CONSORTIUM['name'],Config::$APPEARANCE['productname']); ?>",
   "textLocateMe": "<?php echo _("Locate me more accurately using HTML5 Geo-Location") ?>",
   "textShowProviders": "<?php echo _("Show institutions in") ?>",
   "textAllCountries": "<?php echo _("all countries") ?>",
   "textSearch" : "<?php echo _("or search for an institution, in example Univerity of Oslo") ?>",
   "textShowAllCountries": "<?php echo _("show all countries") ?>",
   "textLimited1" : "<?php echo _("Results limited to")?>",
   "textLimited2" : "<?php echo _("entries - show more")?>",
   "textNearby" : "<?php echo _("Nearby")?>",
   "geoLoc_timeout" : "<?php echo _("Location timeout")?>",
   "geoLoc_posUnavailable" : "<?php echo _("Could not get your position")?>",
   "geoLoc_permDenied" : "<?php echo _("Your browser has denied access to your location")?>",
   "geoLoc_unknownError" : "<?php echo _("Unknown location error")?>",
   "geoLoc_here" : "<?php echo _("You are here:")?>",
   "geoLoc_getting" : "<?php echo _("Getting your location...")?>",
   "geoLoc_nearby" : "<?php echo _("Nearby providers shown on top.")?>",
   "countryAPI":"user/cat_back.php?action=locateUser",
   "metadata":"user/cat_back.php?action=listAllIdentityProviders&lang="+lang,
   "callback": function(e) {
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
     listProfiles(e.entityID,e.title,0);
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


$("#close_button").click(function(event) {
    $("#info_overlay").hide();
    $("#main_body").fadeTo("fast", 1.0);
});
if(front_page)
   $("#img_roll_1").fadeOut(0);
   $("#cursor").fadeOut(0);
if(front_page) {
   prepareAnimation();
   h2 = $("#heading").height() + 40;
   $("#front_page").height($(window).height() - h2);
   if($(window).height() < 700 ) 
      $("#user_button_td").css('padding-top',Math.max($(window).height() - 550,30));
   }
   w1 = Math.round($(document).width()/2);
   $("#user_button1").width(w1);
   w2 = w1 - $("#eu_text").width();
   w3 = w1 + w2 - 140;
   $("#logos").css('left',w3+'px');
 });
