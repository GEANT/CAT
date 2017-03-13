<?php
namespace lib\http;

use lib\utils\CSVParser;

class AddUsersCommand extends AbstractCommand{

    const COMMAND = 'newusers';

    /**
     *
     * {@inheritDoc}
     * @see \lib\http\AbstractCommand::execute()
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
                $user = $this->controller->createUser($row[0], $row[1]);
                $max = empty($row[2]) ? 1 : $row[2];
                if(!empty($user->getIdentifier())){
                    for($i=0; $i<$max; $i++){
                        $this->controller->createCertificate($user);
                        $invitationsCount++;
                    }
                    $userCount++;
                }
            }else{
                 $this->storeErrorMessage(_('Username or expiry date missing for') . ' ' . ($userCount + 1) . ' ' . _(' record!'));
            }
        }
        if($userCount>0){
            $this->storeInfoMessage($userCount .' '. _('total users were imported and') .' '. $invitationsCount .' '. _('invitations created!'));
        }
    }

}
