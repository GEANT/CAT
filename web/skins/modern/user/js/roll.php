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

/**
 * This file defines elements of a simple image slideshow 
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @package UserGUI
 *
 * Some texts that you might want to customise are hardcoded 
 * in the calls creating the OS objects. See more comments at
 * the bottom of this file
 * 
 * This file should be loaded AFTER cat_js.php
*/
?>

var img_vis = 0;
var roller = 1;
var that;

/*
  ProgramFlow object defines structures required to put asynchroneous elements
  into a synchroneus program flow.

  Add method adds an object to the flow. Each object added to the flow must
  define an Execute method, and that mathod must call the ProgramFlow nextStep
  method to activate the following step of the program.
  nextStep executes the next step of the flow
  Sleep pauses the flow for a given number of miliseconds
*/
function ProgramFlow() {
   this.programArray = new Array();
   this.program_pointer = -1;
   this.progLen = 0;
   this.tm = 0;
   this.stop_program = 0;
   that = this;

   this.Add = function(object,init_delay) {
     o = new Object();
     o.object = object;
     o.delay = init_delay;
     this.programArray.push(o);
   }

   this.nextStep = function() {
      if (that.stop_program)
         return;
      that.program_pointer++;
      if(that.program_pointer ==  that.programArray.length)
      that.program_pointer = 0;
      NS = that.programArray[that.program_pointer];
      that.tm = window.setTimeout(function() {NS.object.Execute();},NS.delay);
   }

   this.Sleep = function(delay) {
      o = new Object();
      o1 = new Object();
      o1.Execute = this.nextStep;
      o.object = o1;
      o.delay = delay;
     this.programArray.push(o);
   }
}

<?php
/**
 * The OS object holds operating system descriptions
 * @param name is the main title
 * @param subtitle - the subtitle
 * @param path - the path to the scriin-dump image
 * @param signed - if true show information that the module is signed
*/
?>

function OS(name, subtitle, path, signed) {
  this.name = name;
  this.subtitle = subtitle;
  this.image_path = path;
  this.signed = signed;
}

/* 
   The Picture object prepares an image to be displayed
*/

function Picture(os,fadeTime) {
   this.os = os;
   this.fadeTime = fadeTime;
   this.image = new Image();
   this.image.src = os.image_path;

   this.Execute = function() {
      old_vis = img_vis;
      img_vis = img_vis == 0 ? 1 : 0;
      $("#img_roll_"+old_vis).attr("src",this.os.image_path); 
      $("#img_roll_"+img_vis).fadeOut(this.fadeTime).delay(this.fadeTime);
      $("#line2").text(this.os.name);
      $("#line3").text(this.os.subtitle);
      if (this.os.signed)
         $("#line5").show();
      else
         $("#line5").hide();
      $("#img_roll_"+old_vis).fadeIn(this.fadeTime,Program.nextStep);
//      $("#img_roll_"+old_vis).fadeIn(500,Program.nextStep);
   }
}


var fTM = 1500;

<?php
/**
  The OS objects define individual frames
  the last argument of the OS call specifies whether the module is
  signed.  Make sure that this is in sync with your settings
  in devices/devices.php
 */
?>

var win8 = new OS('<?php echo _("MS Windows")?>', '<?php echo _("10, 8, 7, Vista") ?>', "<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "screenshots/sampleinstaller-win8-english-h234.png")?>",true);
var mac = new OS('<?php echo _("Apple OS X")?>','10.7+', "<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "screenshots/sampleinstaller-mac-english-h234.png")?>",true);
var android = new OS('<?php echo _("Android")?>','<?php echo _("4.3+") ?>', "<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "screenshots/sampleinstaller-android-english-h234.png")?>",false);
var iphone = new OS('<?php echo _("Apple iOS devices")?>','<?php echo _("iPhone, iPad, iPod touch") ?>', "<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "screenshots/sampleinstaller-iphone-english-h234.png")?>",true);
var linux = new OS('<?php echo _("Linux")?>','<?php echo _("all major distributions") ?>', "<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "screenshots/sampleinstaller-linux-english-h234.png")?>",false);
var chromeos = new OS('<?php echo _("Chrome OS")?>','', "<?php echo $Gui->skinObject->findResourceUrl("IMAGES", "screenshots/sampleinstaller-chromeos-english-h234.png")?>",false);



var Program = new ProgramFlow();
function prepareAnimation() {
   Program.Add(new Picture(mac,fTM),0);
   Program.Add(new Picture(win8,fTM),2000);
   Program.Add(new Picture(iphone,fTM),2000);
   Program.Add(new Picture(linux,fTM),2000);
   Program.Add(new Picture(android,fTM),2000);
   Program.Add(new Picture(chromeos,fTM),2000);
   Program.nextStep();
   Program.Sleep(2000);
}

