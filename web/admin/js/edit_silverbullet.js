/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */

/**
 * Edit Silverbullet page script file (requires silverbullet.js to be included)
 * @author Zilvinas Vaira
 */

(function(){
    /** global: silverbullet */
    var app = new silverbullet.SilverbulletApplication();
    window.onload = function() {
        app.start();
        
        //Check which tab should be active
        var active = $( "#tabs" ).attr("active");
        $( "#tabs" ).tabs({ active: active });
    };
})();
