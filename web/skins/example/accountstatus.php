<?php
/*
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Horizon 2020 
 * research and innovation programme under Grant Agreement No. 731122 (GN4-2).
 * 
 * On behalf of the GÉANT project, GEANT Association is the sole owner of the 
 * copyright in all material which was developed by a member of the GÉANT 
 * project. GÉANT Vereniging (Association) is registered with the Chamber of 
 * Commerce in Amsterdam with registration number 40535155 and operates in the
 * UK as a branch of GÉANT Vereniging. 
 * 
 * Registered office: Hoekenrode 3, 1102BR Amsterdam, The Netherlands. 
 * UK branch address: City House, 126-130 Hills Road, Cambridge CB2 1PQ, UK
 * 
 * License: see the web/copyright.inc.php file in the file structure or
 *          <base_url>/copyright.php after deploying the software
 */?>
<h1>Example Skin (Pick-Up and Status page for <?php echo \core\ProfileSilverbullet::PRODUCTNAME;?>)</h1>
<img alt='Consortium logo' src="<?php echo $Gui->skinObject->findresourceUrl("IMAGES","consortium_logo.png");?>"/>
<p>This skin is much more sober and less bloated than the default one. As it happens, it also doesn't do anything.</p>
<p>But at least it goes to show that it's possible to include custom images/CSS/external software using findResourceUrl(..., $filename):
<img alt='Custom image' src="<?php echo $Gui->skinObject->findresourceUrl("IMAGES","custom.png");?>"/>
<p>For <?php echo \core\ProfileSilverbullet::PRODUCTNAME;?>, this page can make use of the request status info we have collected prior to invocation of the skinned page:</p>
<pre>
    <?php print_r($statusInfo);?>
</pre>
