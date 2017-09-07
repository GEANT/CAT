<?php

namespace web\lib\admin\view;

use web\lib\admin\domain\SilverbulletCertificate;
use web\lib\admin\domain\SilverbulletUser;
use web\lib\admin\http\AddInvitationCommand;
use web\lib\admin\http\DeleteUserCommand;
use web\lib\admin\http\MessageDistributor;
use web\lib\admin\http\RevokeCertificateCommand;
use web\lib\admin\http\SaveUsersCommand;
use web\lib\admin\http\UpdateUserCommand;
use web\lib\admin\view\html\Button;
use web\lib\admin\view\html\CompositeTag;
use web\lib\admin\view\html\Row;
use web\lib\admin\view\html\Table;
use web\lib\admin\view\html\Tag;
use web\lib\admin\view\html\UnaryTag;
use web\lib\admin\http\SendTokenByEmail;
use web\lib\admin\http\SendTokenBySms;
use web\lib\admin\domain\SilverbulletInvitation;
use web\lib\admin\http\RevokeInvitationCommand;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class UserCredentialsForm implements PageElementInterface{
    
    const EDITABLEBLOCK_CLASS = 'sb-editable-block';
    const TITLEROW_CLASS = 'sb-title-row';
    const USERROW_CLASS = 'sb-user-row';
    const CERTIFICATEROW_CLASS = 'sb-certificate-row';
    const INVITATION_TOKEN_CLASS = 'sb-invitation-token';
    const RESET_BUTTON_ID = 'sb-reset-dates';
    const USER_COLUMN = 'user';
    const TOKEN_COLUMN = 'token';
    const EXPIRY_COLUMN = 'expiry';
    const ACTION_COLUMN = 'action';
    
    /**
     *
     * @var Table
     */
    private $table;

    /**
     *
     * @var number
     */
    private $userIndex = 0;
    
    /**
     * 
     * @var number
     */
    private $userRowIndex = 0;

    /**
     *
     * @var TitledFormDecorator
     */
    private $decorator;
    
    /**
     * @var string
     */
    private $action;

    /**
     * 
     * @var CompositeTag
     */
    private $acknowledgeNotice;

    /**
     * Provides smallest period of days left until one or more users need to be acknowledged.
     * 
     * @var number Should be a number from 0 to maximum alloved period (e.g. 365 days).
     */
    private $acknowledgeDays;
    
    /**
     * 
     * @var string
     */
    private $acknowledgeText;
    
    /**
     * 
     * @param MessageDistributor $distributor
     * @param string $action
     * @param string $title
     * @param string $acknowledgeText
     * @param boolean $isAcknowledgeEnabled
     */
    public function __construct($distributor, $action, $title, $acknowledgeText, $isAcknowledgeEnabled = false) {
        $this->action = $action;
        $this->table = new Table();
        $this->table->addAttribute("cellpadding", 5);
        $this->table->addAttribute("style", "max-width:1920px;");
        $this->decorator = new TitledFormDecorator($this->table, $title, $this->action);

        $hiddenCommand = new UnaryTag('input');
        $hiddenCommand->addAttribute('type', 'hidden');
        $hiddenCommand->addAttribute('name', 'command');
        $hiddenCommand->addAttribute('value', SaveUsersCommand::COMMAND);
        $this->decorator->addHtmlElement($hiddenCommand, TitledFormDecorator::BEFORE);
        
        $saveMessageBox = new MessageBox(PageElementInterface::MESSAGEBOX_CLASS);
        $distributor->distributeMessages(SaveUsersCommand::COMMAND, $saveMessageBox);
        $distributor->distributeMessages(AddInvitationCommand::COMMAND, $saveMessageBox);
        $distributor->distributeMessages(DeleteUserCommand::COMMAND, $saveMessageBox);
        $distributor->distributeMessages(SendTokenByEmail::COMMAND, $saveMessageBox);
        $distributor->distributeMessages(SendTokenBySms::COMMAND, $saveMessageBox);
        $this->decorator->addHtmlElement($saveMessageBox, TitledFormDecorator::BEFORE);
        
        $this->acknowledgeDays = CONFIG_CONFASSISTANT['SILVERBULLET']['gracetime'] ?? SilverbulletUser::MAX_ACKNOWLEDGE;
        $this->acknowledgeText = $acknowledgeText;
        if($isAcknowledgeEnabled){
            $div = new CompositeTag('div');
            $div->addAttribute('style', 'padding-bottom: 20px;');
            $this->acknowledgeNotice = new Tag ('p');
            $div->addTag($this->acknowledgeNotice);
            $checkbox = new UnaryTag('input');
            $checkbox->addAttribute('type', 'checkbox');
            $checkbox->addAttribute('name', SaveUsersCommand::PARAM_ACKNOWLEDGE);
            $checkbox->addAttribute('value', 'true');
            $div->addTag($checkbox);
            $label = new Tag('label');
            $label->addText('I have verified that all configured users are still eligible for eduroam');
            $div->addTag($label);
            $this->decorator->addHtmlElement($div);
            $this->decorator->addHtmlElement(new Button(_('Save'),'submit', SaveUsersCommand::COMMAND, SaveUsersCommand::COMMAND));
        }
        
        $this->addTitleRow();
    }
    
    /**
     * 
     */
    private function addTitleRow(){
        $row = new Row(array(self::USER_COLUMN => _('User'), self::TOKEN_COLUMN => _('Token/Certificate details'), self::EXPIRY_COLUMN => _('User/Token Expiry'), self::ACTION_COLUMN => _('Actions')));
        $row->addAttribute('class', self::TITLEROW_CLASS);
        $this->table->addRow($row);
    }
    
    /**
     * 
     * @param SilverbulletUser $user
     */
    public function addUserRow($user){
        $row = new Row(array(self::USER_COLUMN => $user->getUsername(), self::EXPIRY_COLUMN => new DatePicker(SaveUsersCommand::PARAM_EXPIRY_MULTIPLE, $user->getExpiry())));
        $row->addAttribute('class', self::USERROW_CLASS);
        
        $acknowledgeLevel = $user->getAcknowledgeLevel();
        if($acknowledgeLevel == SilverbulletUser::LEVEL_YELLOW){
            $row->addAttribute('style', 'background-color:#F0EAC0;');
        }elseif ($acknowledgeLevel == SilverbulletUser::LEVEL_RED){
            $row->addAttribute('style', 'background-color:#F0C0C0;');
        }
        
        $acknowledgeDays = $user->getAcknowledgeDays();
        if($acknowledgeDays < $this->acknowledgeDays){
            $this->acknowledgeDays = $acknowledgeDays;
        }
        
        $this->userRowIndex = $this->table->size();
        $this->table->addRow($row);
        
        $hiddenUserId = new Tag('input');
        $hiddenUserId->addAttribute('type', 'hidden');
        $hiddenUserId->addAttribute('name', SaveUsersCommand::PARAM_ID_MULTIPLE);
        $hiddenUserId->addAttribute('value', $user->getIdentifier());
        $this->table->addToCell($this->userRowIndex, self::USER_COLUMN, $hiddenUserId);

        $updateButton = new Button(_('Update'), 'submit', UpdateUserCommand::COMMAND, $this->userIndex);
        //$resetButton = new Button ( _ ( 'Reset' ), 'reset', '', '', 'delete', self::RESET_BUTTON_ID );
        $this->table->addToCell($this->userRowIndex, self::EXPIRY_COLUMN, $updateButton);
        
        $action = new CompositeTag('div');
        $action->addAttribute('class', 'sb-user-buttons');
            $deactivationButton = new Button(_('Deactivate User'), 'submit', DeleteUserCommand::COMMAND, $user->getIdentifier(), 'delete');
            if($user->isDeactivated()){
                $deactivationButton->addAttribute('disabled', 'disabled'); 
            }
        $action->addTag($deactivationButton);
        $action->addTag(new Button(_('New Credential'), 'submit', AddInvitationCommand::COMMAND, $this->userIndex));
        $invitationsLabel = new CompositeTag("label");
        $invitationsLabel->addText(_('Activations: '));
        $invitationsQuantity = new Tag('input');
        $invitationsQuantity->addAttribute('type', 'text');
        $invitationsQuantity->addAttribute('name', SaveUsersCommand::PARAM_QUANTITY_MULTIPLE);
        $invitationsQuantity->addAttribute('value', '1');
        $invitationsQuantity->addAttribute('maxlength', '3');
        $invitationsQuantity->addAttribute('style', 'width: 30px;');
        $invitationsLabel->addTag($invitationsQuantity);
        $action->addTag($invitationsLabel);
        $this->table->addToCell($this->userRowIndex, self::ACTION_COLUMN, $action);
        $this->userIndex++;
    }
    
    /**
     * 
     * @param SilverbulletCertificate $certificate
     */
    public function addCertificateRow($certificate){
        if($certificate->isGenerated()){

            //Create certificate box
            $certificateBox = new CompositeTag('div');
            $certificateBox->addAttribute('class', 'sb-certificate-summary ca-summary');
                
            //Create certificate details div
            $certificateDetails = new Tag('div');
            $certificateDetails->addAttribute('class', 'sb-certificate-details');
            $certificateDetails->addText($certificate->getCertificateDetails());
            $certificateBox->addTag($certificateDetails);

            //Create button container div
            $buttonContainer = new Tag('div');
            if($certificate->isRevoked()){
                $certificateBox->addAttribute('style', 'background-color:#F0C0C0;');
                $buttonContainer->addAttribute('style', 'height:22px; margin-top:7px; text-align:center;');
                $buttonContainer->addText(_("REVOKED"));
            }elseif ($certificate->isExpired()){
                $certificateBox->addAttribute('style', 'background-color:lightgrey;');
                $buttonContainer->addAttribute('style', 'height:22px; margin-top:7px; text-align:center;');
                $buttonContainer->addText(_("EXPIRED"));
            }else{
                $buttonContainer->addAttribute('style', 'text-align:right;padding-top: 5px;');
                $buttonContainer->addText(new Button(_('Revoke'), 'submit', RevokeCertificateCommand::COMMAND, $certificate->getIdentifier(), 'delete'));
            }
            $certificateBox->addTag($buttonContainer);
            $this->table->addToCell($this->userRowIndex, self::TOKEN_COLUMN, $certificateBox);
            
        }
    }
    
    /**
     *
     * @param SilverbulletInvitation $invitation
     */
    public function addInvitationRow($invitation){
        $expiryColumn = _("Expiry Date: ") . $invitation->getExpiry();
        $expiryColumn .= "<br>";
        $expiryColumn .= _("Activations: ") . $invitation->getRemainingActivations();
        $row = new Row(array(self::TOKEN_COLUMN => $invitation->getTokenLink(), self::EXPIRY_COLUMN => $expiryColumn));
        $row->addAttribute('class', self::CERTIFICATEROW_CLASS);
        $index = $this->table->size();
        $this->table->addRow($row);
        if(!$invitation->isExpired() && !$invitation->isRevoked() && !$invitation->isAbsent()){
            $this->table->addToCell($index, self::TOKEN_COLUMN, new Button(_('Copy to Clipboard'), 'button', '', '', self::INVITATION_TOKEN_CLASS . '-copy'));
            $this->table->addToCell($index, self::TOKEN_COLUMN, new Button(_('Compose mail...'), 'button', '', '', self::INVITATION_TOKEN_CLASS. '-compose'));
            $this->table->addToCell($index, self::TOKEN_COLUMN, new Button(_('Send in SMS...'), 'button', '', '', self::INVITATION_TOKEN_CLASS. '-sms'));
            $this->table->addToCell($index, self::TOKEN_COLUMN, new Button(_('Generate QR code...'), 'button', '', '', self::INVITATION_TOKEN_CLASS. '-qrcode'));
        }
        if(!$invitation->isRevoked()){
            $this->table->addToCell($index, self::ACTION_COLUMN, new Button(_('Revoke'), 'submit', RevokeInvitationCommand::COMMAND, $invitation->getIdentifier(), 'delete'));
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\PageElementInterface::render()
     */
    public function render() {
        if($this->acknowledgeNotice!=null){
            $this->acknowledgeNotice->addText(sprintf($this->acknowledgeText, $this->acknowledgeDays));
        }
        ?>
        <div class="<?php echo self::EDITABLEBLOCK_CLASS;?>">
            <?php $this->decorator->render(); ?>
        </div>
        <?php
    }
}
