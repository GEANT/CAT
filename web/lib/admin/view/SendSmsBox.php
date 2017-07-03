<?php
namespace web\lib\admin\view;

use web\lib\admin\http\SendTokenBySms;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SendSmsBox extends AbstractTextDialogBox {
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\AbstractDialogBox::renderControls()
     */
    protected function renderControls(){
        ?>
        <div style="position: relative; padding-bottom: 10px; width: 450px;">
             <label>Enter user phone: +</label>
             <input type="text" id="<?php echo PageElementInterface::SEND_SMS_CLASS; ?>-phone" name="<?php echo SendTokenBySms::PARAM_PHONE; ?>" maxlength="15">
             <button type="submit" id="<?php echo PageElementInterface::SEND_SMS_CLASS; ?>-send" name="command" value="<?php echo SendTokenBySms::COMMAND; ?>">Send SMS with CAT</button>
        </div>
        <?php
    }
}
