<?php
namespace web\lib\admin\http;

use web\lib\admin\utils\CSVParser;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class AddUsersCommand extends AbstractInvokerCommand{

    const COMMAND = 'newusers';

    /**
     *
     * @var SilverbulletContext
     */
    private $context;
    
    /**
     *
     * @param string $commandToken
     * @param SilverbulletContext $context
     */
    public function __construct($commandToken, $context){
        parent::__construct($commandToken, $context);
        $this->context = $context;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\http\AbstractCommand::execute()
     */
    public function execute(){
        $parser = new CSVParser($_FILES[self::COMMAND], "\n", ',');
        if(!$parser->isValid()){
            $this->storeErrorMessage(_('File either is empty or is not CSV file!'));
        }
        $userCount = 0;
        $invitationsCount = 0;
        while($parser->hasMoreRows()){
            $row = $parser->nextRow();
            if(isset($row[0]) && isset($row[1])){
                $user = $this->context->createUser($row[0], $row[1], $this);
                $max = empty($row[2]) ? 1 : intval($row[2]);
                if(!empty($user->getIdentifier())){
                    $this->context->createInvitation($user, $this, $max);
                    $userCount++;
                }
            }else{
                 $this->storeErrorMessage(sprintf(_('Username or expiry date missing for %s record!'), $userCount + 1));
            }
        }
        if($userCount>0){
            $this->storeInfoMessage(sprintf(_('%s total users were imported and %s invitations created!'), $userCount, $invitationsCount));
        }
    }

}
