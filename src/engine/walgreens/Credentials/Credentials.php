<?php

namespace AwardWallet\Engine\walgreens\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetRemindLoginFields()
    {
        return [
            'Email',
        ];
    }

    public function RemindLogin($data)
    {
        $this->http->GetURL('https://www.walgreens.com/password/username_reset.jsp');

        if (!$this->http->ParseForm('forgotForm1')) {
            return false;
        }

        $this->http->SetInputValue('emailAddress2', $data['Email']);
        $this->http->SetInputValue('submit1', 'Continue');

        $this->http->PostForm();

        if ($this->http->FindPreg('#We\s+have\s+sent\s+you\s+an\s+email\s+with\s+your\s+username#i')) {
            return true;
        } else {
            return false;
        }
    }

    public function GetRemindLoginCriteria()
    {
        return [
            'SUBJECT "Retrieve Username" FROM "donotreply@mail.walgreens.com"',
        ];
    }

    public function ParseRemindLoginEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if ($login = re('#Username\s*:\s+(\S+)#i', $text)) {
            $result['Login'] = $login;
        }

        return $result;
    }

    public function GetCredentialsCriteria()
    {
        return [
            'FROM "Walgreens@e.walgreens.com"',
            'FROM "donotreply@mail.walgreens.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        if (stripos($subject, 'Retrieve Username') !== false) {
            $result['FirstName'] = re('#Dear\s+(.*)\s+We have received#i', $text);
        }

        return $result;
    }
}
