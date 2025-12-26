<?php

namespace AwardWallet\Engine\voila\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetRetrieveFields()
    {
        return ['Login'];
    }

    public function GetCredentialsCriteria()
    {
        return [
            'FROM "support@vhr.com"',
            'FROM "web.help@vhr.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if ($s = re('#Dear\s+(.*)\s*,#i', $text)) {
            // credentials-1.eml
            $result['FirstName'] = $s;
        }

        if ($s = re('#Member\s+Name\s*:?\s+(.*)#i', $text)) {
            // credentials-{1,2,3,4}.eml
            $result['Name'] = $s;
        }

        return $result;
    }

    public function RetrievePassword($data)
    {
        $this->http->GetURL('http://www.vhr.com/forgetpassword.aspx');

        if (!$this->http->ParseForm('aspnetForm')) {
            return false;
        }

        $this->http->SetInputValue('ctl00$MainContent$txtEmail', $data['Login']);
        $this->http->SetInputValue('ctl00$MainContent$btnEnterEmail', 'Reset password');

        $this->http->PostForm();

        if (re('#Click\s+the\s+reset\s+link\s+we\s+sent\s+to\s+\S+\s+to\s+change\s+your\s+password#i', text($this->http->Response['body']))) {
            return true;
        } else {
            return false;
        }
    }

    public function GetRetrievePasswordCriteria()
    {
        return [
            'SUBJECT "Reset your password" FROM "web.help@vhr.com"',
        ];
    }

    public function ParseRetrievePasswordEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);

        if ($password = re('#Below\s+is\s+your\s+temporary\s+password\s*:\s+(\S+)#i', $text)) {
            $result['Password'] = $password;
        }

        return $result;
    }
}
