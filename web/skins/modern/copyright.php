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
include_once('Divs.php');
$divs = new Divs($Gui);
?>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
<link rel="stylesheet" media="screen" type="text/css" href="<?php echo $Gui->skinObject->findResourceUrl("CSS", "cat-user.css"); ?>" />
</head>
<body>
<div id="wrap">
        <?php echo $divs->div_heading('start'); ?>

    <div id="main_page">
        <div id="main_body">
            <div id="user_page" style="display:block">
                <?php echo $divs->div_pagetitle("eduroam CAT Copyright and Licensing", ""); ?>
                <div style="padding:20px">
            <?php include dirname(dirname(__DIR__)) . "/copyright.inc.php"; ?>
                </div>
            </div>
        </div>
    </div>
        <?php echo $divs->div_footer(); ?>
</div>
</body>