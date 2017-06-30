<?php
namespace web\lib\admin\view;

use web\lib\admin\http\SendTokenByEmail;
use web\lib\admin\http\ValidateEmailAddress;

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
        <div style="position: relative; padding-bottom: 10px; width: 450px;">
             <label>Send email using client aplication:</label>
             <button type="button" id="sb-compose-email-client">Send mail with client</button>
             <p>OR</p>
             <label>Enter user email: </label>
             <input type="text" id="<?php echo PageElementInterface::COMPOSE_EMAIL_CLASS; ?>-email" name="<?php echo ValidateEmailAddress::PARAM_ADDRESS; ?>">
             <button type="submit" id="<?php echo PageElementInterface::COMPOSE_EMAIL_CLASS; ?>-cat" name="command" value="<?php echo SendTokenByEmail::COMMAND; ?>" disabled="disabled">Send mail with CAT</button>
        </div>
        <?php
    }
}
