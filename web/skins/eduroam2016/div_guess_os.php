<div class="sub_h" id="guess_os">
    <!-- table browser -->
    <table id='browser'>
        <tr>
            <td>
                <button class='large_button guess_os' style='background-image:url("<?php echo $Gui->skinObject->findResourceUrl("IMAGES","vendorlogo/".$operatingSystem['group'].".png")?>'
                                                    id='g_<?php echo $operatingSystem['device'] ?>'>
                    <img id='cross_icon_<?php echo $operatingSystem['device'] ?>' src='<?php echo $Gui->skinObject->findResourceUrl("IMAGES","icons/delete_32.png")?>' >
                    <div class='download_button_text_1' id='download_button_header_<?php echo $operatingSystem['device'] ?>'> <?php print $downloadMessage ?>
                    </div>
                    <div class='download_button_text'>
                        <?php echo $operatingSystem['display'] ?>
                    </div>
                </button>
                <div class='device_info' id='info_g_<?php echo $operatingSystem['device'] ?>'></div>
          </td>
          <td style='vertical-align:top'>
               <button class='more_info_b large_button' id='g_info_b_<?php echo $operatingSystem['device'] ?>'>i</button>
          </td>
      </tr>
    </table> <!-- id='browser' -->
    <div class="sub_h">
       <a href="javascript:other_installers()"><?php echo _("All platforms"); ?></a>
    </div>
</div> <!-- id="guess_os" -->
