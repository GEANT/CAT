<?php

/* 
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");
?>

<!-- JQuery --> 
<script type="text/javascript" src="<?php echo \core\CAT::getRootUrlPath() ?>/external/jquery/jquery.js"></script> 
<!-- JQuery --> 
<script>
    function click_button() {
        $(this).fadeOut(150).fadeIn(150);
    }
    $(document).on("click", "button", click_button);
</script>