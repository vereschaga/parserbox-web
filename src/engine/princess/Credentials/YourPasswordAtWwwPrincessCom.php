<?php

namespace AwardWallet\Engine\princess\Credentials;

class YourPasswordAtWwwPrincessCom extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'password-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'noreply@princesscruises.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Your password at www.princess.com',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Password',
            'Name',
            'Email',
            'FirstName',
            'LastName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($parser->getPlainBody());
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['Name'] = re('#Here\s+is\s+the\s+password\s+for\s+(.*?):#', $text);

        if (preg_match('#^(\w+)\s+(\w+)$#i', $result['Name'], $m)) {
            $result['FirstName'] = $m[1];
            $result['LastName'] = $m[2];
        }
        $result['Password'] = re('#Password\s*:\s+(\S+)#i', $text);

        return $result;
    }

    public function GetRetrieveFields()
    {
        return [
            'FirstName',
            'LastName',
            'Email',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->GetURL('https://book.princess.com/captaincircle/jsp/passwordReminder.jsp');

        if (!$this->http->ParseForm('PASSWORD_REMINDER_FORM')) {
            return false;
        }

        $this->http->SetInputValue('firstName', $data['FirstName']);
        $this->http->SetInputValue('lastName', $data['LastName']);
        $this->http->SetInputValue('email', $data['Email']);

        $this->http->PostForm();

        if ($this->http->FindPreg('#Your\s+password\s+has\s+been\s+sent\s+to\s+the\s+email\s+address\s+you\s+provided#i')) {
            return true;
        } else {
            return false;
        }
    }
}
