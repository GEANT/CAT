<div class="sub_h">
    <div id="other_installers">
         <?php echo _("Choose an installer to download"); ?>
         <table id="device_list" style="padding:0px;">
             <?php
                 $langObject->setTextDomain("devices");
                 foreach ($Gui->listDevices(isset($_REQUEST['hidden']) ? $_REQUEST['hidden'] : 0) as $group => $deviceGroup) {
                      $groupIndex = count($deviceGroup);
                      $deviceIndex = 0;
                      print '<tbody><tr><td class="vendor" rowspan="' . $groupIndex . '"><img src="'. $Gui->skinObject->findResourceUrl("IMAGES","vendorlogo/".$group.".png").'" alt="' . $group . ' Device" title="' . $group . ' Device"></td>';
                      foreach ($deviceGroup as $d => $D) {
                          if ($deviceIndex) {
                              print '<tr>';
                          }
                          $j = ($deviceIndex + 1) * 20;
                          print "<td><button id='" . $d . "'>" . $D['display'] . "</button>";
                          print "<div class='device_info' id='info_" . $d . "'></div></td>";
                          print "<td><button class='more_info_b' id='info_b_" . $d . "'>i</button></td></tr>\n";
                          $deviceIndex++;
                      }
                      print "</tbody>";
                  }
                  $langObject->setTextDomain("web_user");
            ?>
        </table>
    </div>
</div>
