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
            $profile = $this->factory->getProfile();
            $profile->addAttribute("hiddenprofile:tou_accepted",NULL,TRUE);
        }
        $this->factory->redirectAfterSubmit();
    }

}
