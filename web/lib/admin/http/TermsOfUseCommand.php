<?php
namespace web\lib\admin\http;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class TermsOfUseCommand extends AbstractCommand{

    const COMMAND = 'termsofuse';
    
    const AGREEMENT = 'agreement';

    /**
     * 
     * {@inheritDoc}
     * @see \lib\http\AbstractCommand::execute()
     */
    public function execute(){
        if(isset($_POST[self::AGREEMENT])){
            $this->controller->signAgreement();
        }
        $this->controller->redirectAfterSubmit();
    }

}
