/*
  This file defines elements of a simple image slideshow 
*/

var img_vis = 0;
var that;

/*
  ProgramFlow object defines structures required to put asynchroneous elements
  into a synchroneus program flow.

  Add method adds an object to the flow. Each object added to the flow must define
    an Execute method, and that mathod must call the ProgramFlow nextStep method 
    to activate the following step of the program.
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

/*
   The OS object holds operating system descriptions
*/

function OS(id, name, subtitle, path, signed) {
  this.id = id;
  this.name = name;
  this.subtitle = subtitle;
  this.signed = signed;
  this.image_path = path;
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
      $("#img_roll_"+img_vis).fadeOut(this.fadeTime);
      $("#line2").text(this.os.name);
      $("#line3").text(this.os.subtitle);
      if (this.os.signed)
         $("#line5").show();
      else
         $("#line5").hide();
      $("#img_roll_"+old_vis).fadeIn(this.fadeTime,Program.nextStep);
   }
}


var fTM = 1500;

var win8 = new OS('win8','<?php echo _("MS Windows")?>', '<?php echo _("8, 7, Vista, XP") ?>',"resources/images/screenshots/sampleinstaller-win8-english-h234.png",1);
var mac = new OS('mac','<?php echo _("Apple OS X")?>','<?php echo _("Mountain Lion, Lion") ?>',"resources/images/screenshots/sampleinstaller-mac-english-h234.png",1);
var iphone = new OS('iphone','<?php echo _("Apple iOS devices")?>','<?php echo _("iPhone, iPad, iPod touch") ?>',"resources/images/screenshots/sampleinstaller-iphone-english-h234.png",1);
var linux = new OS('linux','<?php echo _("Linux")?>','<?php echo _("all major distributions") ?>',"resources/images/screenshots/sampleinstaller-linux-english-h234.png",0);



var Program = new ProgramFlow();
function prepareAnimation() {
   Program.Add(new Picture(mac,fTM),0);
   Program.Add(new Picture(win8,fTM),2000);
   Program.Add(new Picture(iphone,fTM),2000);
   Program.Add(new Picture(linux,fTM),2000);
   Program.nextStep();
   Program.Sleep(2000);
}

