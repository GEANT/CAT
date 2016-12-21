<?php
/* * *********************************************************************************
 * (c) 2011-16 GÉANT on behalf of the GN3, GN3plus, GN4-1 and GN4-2 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
require_once("Skinjob.php");
?>
<h1>Example Skin (main user frontpage)</h1>
<img src="<?php echo $skinObject->findresourceUrl("IMAGES");?>consortium_logo.png"/>
<p>This skin is much more sober and less bloated than the default one. As it happens, it also doesn't do anything.</p>
<p>But at least it goes to show that it's possible to include custom images/CSS/external software using findResourceUrl(..., true):
<img src="<?php echo $skinObject->findresourceUrl("IMAGES",true);?>custom.png"/>
