<?php
/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */
?>
<?php
/**
 * Front-end for the user GUI
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @package UserGUI
 */
include(dirname(dirname(__FILE__)) . "/config/_config.php");
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
    <head lang="en"> 
        <link rel="stylesheet" media="screen" type="text/css" href="resources/css/cat.css.php" />
    </head>
    <body style='background: #fff url(resources/images/bg_grey_tile.png) repeat-x;'>
        <div id="heading">
            <?php
            print '<img src="resources/images/consortium_logo.png" alt="Consortium Logo" style="float:right; padding-right:20px; padding-top:20px"/>';
            print '<div id="motd">' . ( isset(CONFIG['APPEARANCE']['MOTD']) ? CONFIG['APPEARANCE']['MOTD'] : '&nbsp' ) . '</div>';
            print '<h1 style="padding-bottom:0px; height:1em;">' . sprintf(_("%s Copyright and Licensing"), CONFIG['APPEARANCE']['productname']) . '</h1>
<h2 style="padding-bottom:0px; height:0px; vertical-align:bottom;">' . CONFIG['APPEARANCE']['productname_long'] . '</h2>';
            echo '<table id="lang_select"><tr><td>';
            echo '</td><td style="text-align:right;padding-right:20px"><a href="' . dirname($_SERVER['SCRIPT_NAME']) . '">' . _("Start page") . '</a></td></tr></table>';
            ?>
        </div>
        <div id="main_body">
            CAT is distributed under the terms of the G&Eacute;ANT Standard Open Source Software Outward Licence:
            <ol>
                <li>Grant of Copyright Licence
                    <ol>
                        <li>
                            Licensor hereby grants You a world-wide, royalty-free, non-exclusive, perpetual, sub-licensable licence to:
                            <ol>
                                <li>Reproduce the Original Work in copies.</li>
                                <li>Prepare Derivative Works.</li>
                                <li>Distribute copies of the Original Work and Derivative Works to the public, with the proviso that copies of Original Work or Derivative Works that You distribute shall be Licensed under the terms of this Licence.</li>
                                <li>Display the Original Work publicly.</li>
                            </ol>
                        </li>
                    </ol>                    
                </li>
                <li>Grant of Patent Licence<br/>Licensor hereby grants You a world-wide, royalty-free, non-exclusive, perpetual, sub-licensable licence, under patent claims owned or controlled by the Licensor that are embodied in the Original Work as furnished by the Licensor to use and modify the Original Work and Derivative Works.
                </li>
                <li>Grant of Licence
                    <ol>
                        <li>Licensor hereby agrees to provide a machine-readable copy of the Source Code of the Original Work along with each copy of the Original Work that Licensor distributes.</li>
                        <li>Licensor reserves the right to satisfy this obligation by placing a machine-readable copy of the Source Code in an information repository reasonably calculated to permit inexpensive and convenient access by You for as long as Licensor continues to distribute the Original Work, and by publishing the address of that information repository in a notice immediately following the copyright notice that applies to the Original Work.</li>
                    </ol>
                </li>
                <li>
                    Exclusions from Licence Grant
                    <ol>
                        <li>Neither the names of Licensor, nor the names of any contributors to the Original Work, nor any of their trade marks or service marks, may be used to endorse or promote products derived from this Original Work without express prior written permission of the Licensor.</li>
                        <li>Nothing in this Licence shall be deemed to grant any rights to trade marks, copyrights, patents, trade secrets or any other intellectual property of Licensor except as expressly stated herein.</li>
                        <li>No patent licence is granted to make, use, sell or offer to sell embodiments of any patent claims other than the Licensed claims defined in clause 2.</li>
                        <li>Nothing in this Licence shall be interpreted to prohibit Licensor from licensing under different terms from this Licence any Original Work that Licensor otherwise would have a right to license.</li>
                    </ol>
                </li>
                <li>
                    Other Terms<br/>
                    To the extent that the Original Work contains any work which is subject to licence terms which conflict with these terms, the terms of the other licence shall take precedence over the terms of this Licence, to the extent required to give effect to them.
                </li>
                <li>
                    Third Party Provision<br/>
                    As an express condition for the grants of licence hereunder, You agree that any Third Party Provision by You of a Derivative Work shall be deemed a distribution and shall be Licensed to all under the terms of this Licence, as prescribed in clause 1.1.3 herein.
                </li>
                <li>
                    Attribution Rights
                    <ol>
                        <li>You must retain, in the Source Code of any Derivative Works that You create, all copyright, patent or trade mark notices from the Source Code of the Original Work, as well as any notices of licensing and any descriptive text identified therein as an “Attribution Notice”, including the following notice:<br/>
                            On behalf of the GÉANT project, GEANT Limited is the sole owner of the copyright in all material which was developed by a member of the GÉANT project. GEANT Limited is a not-for-profit limited liability company registered in England and Wales (company number 02806796) and with its registered company address at 126-130 Hills Road Cambridge CB2 1PQ. This work is part of a project that has received funding from the European Union’s Horizon 2020 research and innovation programme under Grant Agreement No. 691567 (GN4-1).
                        </li>
                        <li>You must cause the Source Code for any Derivative Works that You create to carry a prominent Attribution Notice reasonably calculated to inform recipients that You have modified the Original Work.</li>
                    </ol>
                </li>
                <li>Warranty of Provenance and Disclaimer of Warranty
                    <ol>
                        <li>The Licensor warrants that the copyright in and to the Original Work and the patent rights granted herein by Licensor are owned by the Licensor or are sublicensed to You under the terms of this Licence with the permission of the contributor(s) of those copyrights and patent rights.</li>
                        <li>Except as expressly stated in clause 8.1, the Original Work is provided under this Licence on an “as is” basis and this Licence expressly excludes all implied terms, conditions and warranties to the maximum limit permitted by the applicable law. The entire risk as to the quality of the Original Work is with you. This disclaimer of warranty constitutes an essential part of this Licence. No licence to Original Work is granted hereunder except under this disclaimer.</li>
                    </ol>
                </li>
                <li>Limitation of Liability
                    <ol>
                        <li>This limitation of liability shall not apply to liability for death or personal injury resulting from Licensor’s negligence to the extent applicable law prohibits such limitation.</li>
                        <li>Subject to clause 9.1 and any applicable law, under no circumstances and under no legal theory, whether in tort (including negligence), contract, or otherwise, shall the Licensor be liable to any person for any direct, indirect, special, incidental, or consequential damages of any character arising as a result of this Licence or the use of the Original Work including, without limitation, damages for loss of goodwill, work stoppage, computer failure or malfunction, or any and all other commercial damages or losses.</li>
                    </ol>                    
                </li>
                <li>
                    Acceptance and Termination
                    <ol>
                        <li>If You distribute copies of the Original Work or a Derivative Work, You must make a reasonable effort under the circumstances to obtain the express consent (which, for the avoidance of doubt, need not be in writing) of recipients to the terms of this Licence.</li>
                        <li>Nothing else but this Licence (or another written agreement between Licensor and You) grants You permission to create Derivative Works or to exercise any of the rights granted in clause 1, and any attempt to do so except under the terms of this Licence (or another written agreement between Licensor and You) is expressly prohibited by English copyright law, the equivalent laws of other countries, and by international treaty. Therefore, by exercising any of the rights granted to You in clause 1, You irrevocably indicate Your acceptance of this Licence and all of its terms and conditions.</li>
                        <li>Any failure by you to comply with your obligations under clause 1.1.3 shall automatically terminate this Licence as well as any rights granted to You under this Licence.</li>
                    </ol>
                </li>
                <li>Legal Fees
                    <ol>
                        <li>In any action to enforce the terms of this Licence or seeking damages relating thereto, the prevailing party shall be entitled to recover its costs and expenses, including, without limitation, reasonable attorneys’ fees and costs incurred in connection with such action, including any appeal of such action.</li>
                        <li>This clause shall survive the termination of this Licence.</li>
                    </ol>
                </li>
                <li>Termination for Patent Action
                    <ol>
                        <li>This Licence shall terminate automatically and You may no longer exercise any of the rights granted to You by this Licence as of the date You commence an action, including a cross-claim or counterclaim, against Licensor or any Licensee alleging that the Original Work infringes a patent.</li>
                        <li>This termination provision shall not apply for an action alleging patent infringement by combinations of the Original Work with other software or hardware.</li>
                    </ol>
                </li>
                <li>
                    Jurisdiction, Venue and Governing Law
                    <ol>
                        <li>Any action or suit relating to this Licence may be brought only in the courts of a jurisdiction wherein the Licensor resides or in which Licensor conducts its primary business, and under the laws of that jurisdiction excluding its conflict-of-law provisions.</li>
                        <li>Any use of the Original Work outside the scope of this Licence or after its termination shall be subject to the requirements and penalties of English copyright law, the equivalent laws of other countries and international treaty.</li>
                        <li>This clause shall survive the termination of this Licence.</li>
                    </ol>
                </li>
                <li>Miscellaneous
                    <ol>
                        <li>This Licence represents the entire agreement concerning the subject matter hereof and the parties have not relied on any representations not included in this Licence when entering into it.</li>
                        <li>If any provision of this Licence is held to be unenforceable, such provision shall be reformed only to the extent necessary to make it enforceable.</li>
                    </ol>
                </li>
                <li>
                    Right to Use<br/>
                    You may use the Original Work in all ways not otherwise restricted or conditioned by this Licence or by law, and Licensor promises not to interfere with or be responsible for such uses by You.
                </li>
                <li>Definitions
                    <ol>
                        <li>Derivative Works means any work, whether in Source Code or Object Code, that is based on (or derived from) the Original Work and for which the editorial revisions, annotations, elaborations, or other modifications represent, as a whole, an original work of authorship. For the purposes of this Licence, Derivative Works shall not include works that remain separable from, or merely link (or bind by name) to the interfaces of, the Work and Derivative Works thereof.</li>
                        <li>Licensor means the individual, individuals, entity or entities that offer(s) the Original Work under the terms of this Licence.</li>
                        <li>Object Code means the form of the Original Work resulting from mechanical transformation or translation of a Source form, including but not limited to compiled object code, generated documentation, and conversions to other media types.</li>
                        <li>Original Work means the work of authorship, whether in Source or Object form, made available under the Licence, as indicated by an Attribution Notice that is included in or attached to the work.</li>
                        <li>Source Code means the preferred form of the Original Work for making modifications to it and all available documentation describing how to modify the Original Work.</li>
                        <li>Third Party Provision means the use or distribution of the Original Work or Derivative Works in any way such that the Original Work or Derivative Works may be used by anyone other than You, whether the Original Work or Derivative Works are distributed to those persons or made available as an application intended for use over a computer network.</li>
                        <li>"You" means an individual or entity exercising rights under this Licence who has not previously violated the terms of this Licence with respect to the Work, or who has received express permission from the Licensor to exercise rights under this Licence despite a previous  violation. For legal entities, "You" includes any entity that controls, is controlled by, or is under common control with you. For purposes of this definition "control" means (i) the power, direct or indirect, to cause the direction or management of such entity, whether by contract or otherwise; or (ii) ownership of fifty percent (50%) or more of the outstanding shares; or (iii) beneficial ownership of such entity.</li>
                    </ol>
                </li>
            </ol>
            The software contains components from the following third parties:
            <ul>
                <li>
                    <b>setEAPCred.exe</b> - &copy; Gareth Ayres<br/>
                    Kindly donated by Gareth Ayres. Used and distributed under the license from https://github.com/GarethAyres/SU1X/tree/master/eduroamCAT
                </li>
                <li>
                    <b>base64.nsh</b> - &copy; 1999-2015 Contributors<br/>
                    base64.nsh include file has been created from http://nsis.sourceforge.net/Base64 and http://nsis.sourceforge.net/CharToASCII under the license: http://nsis.sourceforge.net/License
                </li>
                <li>
                    <b>jQuery</b> - &copy; jQuery Foundation</br>
                    The jQuery UI library is currently available for use in all personal or commercial projects under both MIT and GPL licenses.
                </li>
                <li>
                    <b>DiscoJuice</b> - &copy; UNINETT<br/>
                    Verbatim licensing email:<pre>
> Hi Andreas,
>
> as you may know, Tomasz is using a slightly tweaked version of
> DiscoJuice in eduroam CAT.
>
> In our tarball, we ship that derivative work of DiscoJuice. Is there any
> specific license coming with that software? Is it okay for us to create
> and ship that derivative work?

No problem at all. I whatever licence we put on it, it will be opensource and open for free use for commercial and non-commercial use, including derivate work                     

Andreas
                    </pre>
                </li>
                <li>
                    <b>Icons</b> - &copy; Supratim Nayak<br/>
                    License: "Free for non-commercial use, Commercial Usage not allowed"
                </li>
                <li>
                    <b>button_cancel.png</b> - &copy; RESTENA Foundation<br/>
                    RESTENA stock image, used with permission from author
                </li>
                <li>
                    <b>GeoLite and GeoLite2</b> - &copy; MaxMind, Inc.<br/>
                    This deployment of the CAT product may include GeoLite and/or GeoLite2 data created by MaxMind, available from <a href="http://www.maxmind.com">http://www.maxmind.com</a>
                </li>
            </ul>
        </div>
        <div class='footer'>
            <hr />
            <table style='width:100%'>
                <tr>
                    <td style="padding-left:20px; padding-right:20px; text-align:left; vertical-align:top;">
                        <?php
                        $cat = new \core\CAT();
                        echo $cat->CAT_COPYRIGHT;
                        ?>
                    </td>
                    <td style="padding-left:80px; padding-right:20px; text-align:right; vertical-align:top;">
                        <?php
                        $deco = new \web\lib\admin\PageDecoration();
                        echo $deco->attributionEurope();
                        ?>
                    </td>
                </tr>
            </table>
        </div>
    </body>
</html>
