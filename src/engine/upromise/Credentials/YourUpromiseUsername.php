<?php

namespace AwardWallet\Engine\upromise\Credentials;

class YourUpromiseUsername extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'login-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'customercare@upromise.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Your Upromise Username',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Email',
        ];
    }

    public function GetRetrieveFields()
    {
        return [
            'Email',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->GetURL('https://lty.s.upromise.com/secure/8108.do?cx=l');

        if (!$this->http->ParseForm('forgotUserNameAndEmailForm')) {
            return false;
        }

        $this->http->SetInputValue('emailAddress', $data['Email']);
        $this->http->SetInputValue('emailAddressVerify', $data['Email']);

        $this->http->PostForm();

        if ($this->http->FindPreg('#Your\s+username\s+has(?:\s+already)?\s+been\s+sent\s+to\s+your\s+registered\s+email\s+address#i')) {
            return true;
        } else {
            return false;
        }
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = re('#Your\s+Upromise\s+username\s+is\s*:\s+(\S+)#i', $text);

        return $result;
    }
}
