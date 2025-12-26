<?php

namespace AwardWallet\Engine\norwegiancruise\Credentials;

class Login extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return ["accountservices@ncl.com"];
    }

    public function getCredentialsSubject()
    {
        return ["NCL.COM Forgot Password"];
    }

    public function getParsedFields()
    {
        return ["Login"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = re('#Username\s*:\s+(\S+)#i', $this->text());

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
        $this->http->GetURL('https://www.ncl.com/cas/login');

        if (!$this->http->ParseForm('resetForm')) {
            return false;
        }

        $this->http->SetInputValue('email', $data['Email']);
        $this->http->SetInputValue('firstname', $data['FirstName']);
        $this->http->SetInputValue('lastname', $data['LastName']);
        $this->http->PostForm();

        if ($this->http->FindPreg('#TRUE#i')) {
            return true;
        } else {
            return false;
        }
    }
}
