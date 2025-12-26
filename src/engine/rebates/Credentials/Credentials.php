<?php

namespace AwardWallet\Engine\rebates\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetRetrieveFields()
    {
        return ['Login'];
    }

    public function GetCredentialsCriteria()
    {
        return [
            'FROM "notifications@mrrebates-mailings.com"',
            'FROM "mail@mrrebates-newsletter.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        return $result;
    }

    public function RetrievePassword($data)
    {
        $this->http->GetURL('https://www.mrrebates.com/forgot_password.asp');

        if (!$this->http->ParseForm('theForm')) {
            return false;
        }

        $this->http->SetInputValue('t_email_address', $data['Login']);

        $this->http->PostForm();

        if ($this->http->FindPreg('#Password\s+Emailed#i')) {
            return true;
        } else {
            return false;
        }
    }

    public function GetRetrievePasswordCriteria()
    {
        return ['SUBJECT "Password Request - Mr. Rebates" FROM "notifications@mrrebates-mailings.com"'];
    }

    public function ParseRetrievePasswordEmail(\PlancakeEmailParser $parser)
    {
        $result = [];

        if ($password = re('#Password\s*:\s+(\S+)#i', text($this->http->Response['body']))) {
            $result['Password'] = $password;
        }

        return $result;
    }
}
