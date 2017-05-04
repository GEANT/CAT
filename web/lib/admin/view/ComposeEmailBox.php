<?php
namespace web\lib\admin\view;

use web\lib\admin\http\SendTokenByEmail;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class ComposeEmailBox extends AbstractTextDialogBox {
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\AbstractDialogBox::renderControls()
     */
    protected function renderControls(){
        ?>
        <div style="position: relative; padding-bottom: 5px;">
             <label>Send email using client aplication:</label>
             <button type="button" id="sb-compose-email-client">Send mail with client</button>
             <p>OR</p>
             <label>Enter user email: </label>
             <input type="text" id="sb-compose-email-email" name="email">
             <button type="submit" id="sb-compose-email-cat" name="command" value="<?php echo SendTokenByEmail::COMMAND; ?>" disabled="disabled">Send mail with CAT</button>
        </div>
        <?php
    }
}
