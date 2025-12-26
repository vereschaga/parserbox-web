<?php

include_once __DIR__ . '/../testprovider/functions.php';

class TAccountCheckerTestprovidergroup extends TAccountCheckerTestprovider
{
    public function Login()
    {
        switch ($this->AccountFields['Login']) {
            case 'testprovidergroup':
                switch ($this->AccountFields['Login2']) {
                    case ACCOUNT_INVALID_PASSWORD:
                        throw new CheckException("Invalid logon from group", ACCOUNT_INVALID_PASSWORD);

                    case 'RuntimeException':
                        throw new \RuntimeException('Provider failed');

                    case 'logout':
                        return false;

                    case 'throwToParent':
                        throw new \TestFailException('Throw to parent', null, null, true);

                    default:
                        return true;
                }
        }

        return false;
    }

    public function Parse()
    {
        switch ($this->AccountFields['Login']) {
            case "testprovidergroup":
                $this->SetBalance(1000);

                break;
        }
    }

    public static function GetAccountChecker($accountInfo)
    {
        return new self();
    }
}

class TestFailException extends \Exception implements CheckAccountExceptionInterface
{
    /**
     * @var boll
     */
    private $throwToParent;

    /**
     * @param string $message
     * @param int $code
     * @param Exception $previous
     * @param bool $throwToParent
     */
    public function __construct($message, $code, $previous, $throwToParent)
    {
        parent::__construct($message, $code, $previous);

        $this->throwToParent = $throwToParent;
    }

    /**
     * @return bool
     */
    public function throwToParent()
    {
        return true;
    }
}
