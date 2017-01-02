<?php
namespace lib\http;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class TermsOfUseValidator extends AbstractCommandValidator{

    const COMMAND = 'termsofuse';
    
    const AGREEMENT = 'agreement';

    /**
     * 
     * {@inheritDoc}
     * @see \lib\http\AbstractCommand::execute()
     */
    public function execute(){
        if(isset($_POST[self::AGREEMENT])){
            $this->session->put(self::COMMAND, $_POST[self::AGREEMENT]);
        }
        $this->factory->redirectAfterSubmit();
    }

}
