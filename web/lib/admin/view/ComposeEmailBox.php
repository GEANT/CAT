<?php
namespace web\lib\admin\view;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class ComposeEmailBox extends AbstractTextDialogBox {
    
    protected function renderControls(){
        ?>
        <div style="position: relative; padding-bottom: 5px;">
             <label>Send email using client aplication:</label>
             <button type="button" id="sb-compose-email-client">Send mail with client</button>
             <p>OR</p>
             <label>Enter user email: </label>
             <input type="text" name="email">
             <button type="submit" name="command" value="<?php //echo $this->command; ?>">Send mail with CAT</button>
        </div>
        <?php
    }
}
