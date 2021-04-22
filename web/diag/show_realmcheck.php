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
 * This page displays the dashboard overview of an entire IdP.
 * 
 * @author Maja Górecka-Wolniewicz <mgw@umk.pl>
 */
require_once dirname(dirname(__DIR__))."/config/_config.php";

$loggerInstance = new \core\common\Logging();

$deco = new \web\lib\admin\PageDecoration();

$gui = new \web\lib\user\Gui();

$gui->languageInstance->setTextDomain("diagnostics");

echo $deco->defaultPagePrelude(sprintf(_("Sanity check for dynamic discovery of realms"), \config\Master::APPEARANCE['productname']), false);

$ourlocale = $gui->languageInstance->getLang();

$error_message = '';
$user = NULL;
if (isset($_SESSION['user'])) {
    $user = $_SESSION['user'];
}

?>

<link rel="stylesheet" type="text/css" href="../external/jquery/jquery-ui.css" />
<?php 
$cssUrl = $gui->skinObject->findResourceUrl("CSS", "diag.css", "diag");
if ($cssUrl !== FALSE) {
    echo '<link rel="stylesheet" media="screen" type="text/css" href="'.$cssUrl.'" />';
} 
?>
<!-- JQuery -->
<script type="text/javascript" src="../external/jquery/jquery.js"></script>
<script type="text/javascript" src="../external/jquery/jquery-ui.js"></script>
<script type="text/javascript">
var morealltext = "<?php echo '<i>'._("Show detailed information for all tests").'&raquo;</i>' ?>";
var lessalltext = "<?php echo '<i>'._("Hide detailed information for all tests").'&raquo;</i>' ?>";
var moretext = "<?php echo _("more")."&raquo;" ?>";
var lesstext = "<?php echo "&laquo" ?>";
    $(document).ready(function () {
        $('.caresult, .eap_test_results, .udp_results').on('click', '.morelink', function () {
            if ($(this).hasClass('less')) {
                $(this).removeClass('less');
                $(this).html($(this).attr('moretext'));
                $('.moreall').removeClass('less');
                $('.moreall').html(morealltext);
            } else {
                $(this).attr('moretext', $(this).html());
                $(this).addClass('less');
                $(this).html(lesstext);
            }
            $(this).parent().prev().toggle();
            $(this).prev().toggle();
            return false;
        });
        $(".moreall").click(function () {
            if ($(this).hasClass("less")) {
                $(this).removeClass("less");
                $(this).html(morealltext);
                $('.morelink').removeClass("less");
                $('.morelink').html(moretext);
                $('.morelink:parent').prev().hide();
                $('.morelink').prev().hide();
            } else {
                $(this).addClass("less");
                $(this).html(lessalltext);
                $('.morelink').addClass("less");
                $('.morelink').html(lesstext);
                $('.morelink:parent').prev().show();
                $('.morelink').prev().show();
            }
            return false;
        });
        $(function () {
            $("#tabs").tabs();
        });
    });
</script>
</head>
<body>
<?php
require dirname(__DIR__).'/skins/modern/diag/js/diag_js.php';
echo $deco->productheader("ADMIN");
$norefresh = NULL;
$norefresh = filter_input(INPUT_GET, 'norefresh', FILTER_SANITIZE_STRING); 
if ($norefresh) {
    $norefresh = true;
}
$check_realm = false;
$token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);
if ($token) {
    $realmTests = new \core\diag\RADIUSTestsUI($token);
    if ($realmTests->realm) {
        $check_realm = $realmTests->realm;
        $outer_user = $realmTests->outerUser;
    }
}
if ($check_realm !== FALSE) {
    $realmTests->setGlobalStaticResult();
    $realmTests->setGlobalDynamicResult();
?>
    <h1> <?php printf(_("Realm testing for: %s"), $check_realm); ?>
    </h1>
    <div id="debug_out" style="display: none"></div>
    <div id="timestamp" style="min-width: 600px; max-width:800px" align="right">
        <?php echo _("Tests timestamp: ").$realmTests->getTimeStamp().' UTC'; ?>
    </div>
    <div id="tabs" style="min-width: 600px; max-width:800px">
        <ul>
            <li><a href="#tabs-1"><?php echo _("Overview") ?></a></li>
            <li><a href="#tabs-2"><?php echo _("Static connectivity tests") ?></a></li>
            <?php
                if ($realmTests->isDynamic()) {
            ?>    
            <li id="tabs-d-li"><a href="#tabs-3"><?php echo _("Dynamic connectivity tests") ?></a></li>
            <?php
            }
            ?>
        </ul>
            <div id="tabs-1">
                <button id="run_tests" onclick="runRealmCheck('<?php echo $check_realm; ?>','<?php echo $outer_user; ?>','<?php echo $ourlocale; ?>')"><?php echo _("Repeat connectivity tests") ?></button>
                <div id="test_area"></div>
                <?php print $realmTests->printOverview(); ?>

            </div>
            <div id="tabs-2">
                <!--<button id="run_s_tests" onclick="run_udp()"><?php echo _("Repeat static connectivity tests") ?></button>-->
                <p>
                <?php print $realmTests->printStatic(); ?>


            </div>

            <?php
            if ($realmTests->isDynamic()) {
                ?>
                <div id="tabs-3">
                    <!--<button id="run_d_tests" onclick="run_dynamic()"><?php echo _("Repeat dynamic connectivity tests") ?></button>-->
                    <?php print $realmTests->printDynamic(); ?>
                    
                </div>
            <?php
            }
} else {
        if (is_null($token)) {
            echo '<p><h1>'._("Token missing, no data can be presented").'</h1>';
        } else {
            echo '<p><h1>'._("The token given in the request does not exists, no data can be presented").'</h1>';
        }
}
echo $deco->footer();
if (!$norefresh) {
?>
<script type="text/javascript">
   $(function() {
    $('#run_tests').click();
   });
</script>
<?php
}

