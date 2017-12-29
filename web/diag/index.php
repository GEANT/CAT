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
<html>
    <head>
        <title>Diagnostics Startpage</title>
    </head>
    <body>
        <h1>Diagnostics Startpage</h1>
        <h2>Tools for End Users and IdP/SP admins</h2>
        <h3>Real-Time Diagnostics for a given realm</h3>
        <p>Actually, we should ask if the user knows his realm at all. Alternatively, a list of countries and subsequently a list of institutions in that country, and derive the realm from that.</p>
        <form action='testTelepath.php' method='GET'>
            <span>What realm do you have problems with?<input type='text' name='realm'><button type='submit' name='go'>Go!</button></span>
        </form>
        <h2>Tools for authenticated admins only</h2>
        <h3>SP contacting IdP due to technical problems or abuse</h3>
        <form action='contact-idp.php' method='POST'>
            <select name="Type of Problem">
                <option value='technical'>I suspect a Technical Problem with the IdP</option>
                <option value='abuse-copyright'>A user from this IdP has allegedly infringed copyrights</option>
                <option value='abuse-network'>A user from this IdP has conducted malicious network operations (spam, DDoS, ...)</option>
            </select><br/>
            <table>
                <tr>
                    <td>What is the realm of the IdP in question?</td>
                    <td><input type='text' name='realm'></td>
                </tr>
                <tr>
                    <td>What is the authentication timestamp of the user session in question?</td>
                    <td><input type='text' name='timestamp'></td>
                </tr>
                <tr>
                    <td>What is the MAC address of the user session in question?</td>
                    <td><input type='text' name='mac'></td>
                </tr>
                <tr>
                    <td>Additional comments</td>
                    <td><textarea name='freetext' cols='80' rows='5'></textarea></td>
                </tr>
                <tr>
                    <td>Please specify an email address on which the IdP can contact you</td>
                    <td><input type='text' name='email'></td>
                </tr>
                <tr>
                    <td><button type='submit' name='go'>Go!</button></td>
                    <td></td>
                </tr>
            </table>
        </form>
        <h3>IdP contacting SP due to technical problems</h3>
        <form action='contact-sp.php' method='POST'>
            <select name="Type of Problem">
                <option value='technical'>User claims connectivity problems but has been authenticated successfully</option>
                <option value='abuse-copyright'>User claims that mandatory open port is not open</option>
            </select><br/>
            <table>
                <tr>
                    <td>Can you identify the SP by means of its Operator-Name attribute?</td>
                    <td><input type='text' name='operator-name'></td>
                </tr>
                <tr>
                    <td>Alternatively, we need a country + subsequent list of SP dropdown box to select manually.</td>
                    <td>TBD</td>
                </tr>
                <tr>
                    <td>Alternatively, we need a country + freetext field describing the SP location to route this at least to the NRO</td>
                    <td>TBD</td>
                </tr>
                <tr>
                    <td>What is the outer ID of the user session in question?</td>
                    <td><input type='text' name='timestamp'></td>
                </tr>
                <tr>
                    <td>What is the authentication timestamp of the user session in question?</td>
                    <td><input type='text' name='timestamp'></td>
                </tr>
                <tr>
                    <td>What is the MAC address of the user session in question?</td>
                    <td><input type='text' name='mac'></td>
                </tr>
                <tr>
                    <td>Additional comments about the problem:</td>
                    <td><textarea name='freetext' cols='80' rows='5'></textarea></td>
                </tr>
                <tr>
                    <td>Do you have any contact details by which the user wishes to be contacted by the SP?</td>
                    <td><textarea name='freetext' cols='80' rows='5'></textarea></td>
                </tr>
                <tr>
                    <td>Please specify an email address on which the SP can contact you</td>
                    <td><input type='text' name='email'></td>
                </tr>
                <tr>
                    <td><button type='submit' name='go'>Go!</button></td>
                    <td></td>
                </tr>
            </table>
        </form>
    </body>
</html>
