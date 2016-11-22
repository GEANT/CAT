/**
 * Edit Silverbullet page script file (requires silverbullet.js to be included)
 * @author Zilvinas Vaira
 */

(function(){
    /** global: silverbullet */
    var app = new silverbullet.SilverbulletApplication();
    window.onload = function() {
        app.start();
    };
})();
