<?php
namespace web\lib\admin\http;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class TermsOfUseCommand extends AbstractInvokerCommand{

    const COMMAND = 'termsofuse';
    
    const AGREEMENT = 'agreement';

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
        if(isset($_POST[self::AGREEMENT])){
            $this->context->signAgreement();
        }
        $this->context->redirectAfterSubmit();
    }

}
