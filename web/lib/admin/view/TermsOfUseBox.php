<?php
namespace web\lib\admin\view;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class TermsOfUseBox extends AbstractDialogBox{
    
    private $command = '';
    private $parameter = '';
    
    /**
     * 
     * @param string $action
     * @param string $command
     * @param string $parameter
     */
    public function __construct($action, $command, $parameter) {
        parent::__construct($action);
        $this->command = $command;
        $this->parameter = $parameter;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\AbstractDialogBox::renderContent()
     */
    public function renderContent(){
        ?>
        <hr>
        <h2>Product Definition</h2>
        <p><?php echo \core\ProfileSilverbullet::PRODUCTNAME;?> outsources the technical setup of <?php echo CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'] ." ".CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_institution'];?> functions to the <?php echo CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'];?> Operations Team. The system includes</p>
            <ul>
                <li>a web-based user management interface where user accounts and access credentials can be created and revoked (there is a limit to the number of active users)</li>
                <li>a technical infrastructure ("CA") which issues and revokes credentials</li>
                <li>a technical infrastructure ("RADIUS") which verifies access credentials and subsequently grants access to <?php echo CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'];?></li>
                <li><span style='color: red;'>TBD: a lookup/notification system which informs you of network abuse complaints by <?php echo CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'];?> Service Providers that pertain to your users</span></li>
            </ul>
        <h2>User Account Liability</h2>
        <p>As an <?php echo CONFIG_CONFASSISTANT['CONSORTIUM']['display_name'] ." ".CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_institution'];?> administrator using this system, you are authorized to create user accounts according to your local <?php echo CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_institution'] ?> policy. You are fully responsible for the accounts you issue. In particular, you</p>
        <ul>
            <li>only issue accounts to members of your <?php echo CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_institution'];?>, as defined by your local policy.</li>
            <li>must make sure that all accounts that you issue can be linked by you to actual human end users</li>
            <li>have to immediately revoke accounts of users when they leave or otherwise stop being a member of your <?php echo CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_institution'];?></li>
            <li>will act upon notifications about possible network abuse by your users and will appropriately sanction them</li>
        </ul>
        <p>Failure to comply with these requirements may lead to the deletion of your <?php echo CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_institution'];?> (and all the users you create inside) in this system.</p>
        <h2>Privacy</h2>
        <p>With <?php echo \core\ProfileSilverbullet::PRODUCTNAME;?>, we are not interested in and strive not to collect any personally identifiable information about the end users you create. To that end,</p>
        <ul>
            <li>the usernames you create in the system are not expected to be human-readable identifiers of actual humans. We encourage you to create usernames like 'hr-user-12' rather than 'Jane Doe, Human Resources Department'. You are the only one who needs to be able to make a link to the human behind the identifiers you create.</li>
            <li>the identifiers in the credentials we create are not linked to the usernames you add to the system; they are pseudonyms.</li>
            <li>each access credential carries a different pseudonym, even if it pertains to the same username.</li>      
        </ul>
        <hr>
        <?php
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\view\AbstractDialogBox::renderControls()
     */
    public function renderControls(){
        ?>
        <div style="position: relative; padding-bottom: 5px;">
            <input type="checkbox" name="<?php echo $this->parameter; ?>" value="true"> <label>I have read and agree to the terms.</label>
        </div>
        <button type="submit" name="command" value="<?php echo $this->command; ?>">Continue</button>
        <?php
    }
}
