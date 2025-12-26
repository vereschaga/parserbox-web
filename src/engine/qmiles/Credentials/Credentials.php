<?php

namespace AwardWallet\Engine\qmiles\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetRetrieveFields()
    {
        return ['Login', 'Email'];
    }

    public function GetCredentialsCriteria()
    {
        return [
            'FROM "membersvc@qmiles.com"',
            'FROM "news@qmiles.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $subject = $parser->getSubject();
        $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if (stripos($subject, 'Welcome to Qatar Airways Privilege Club') !== false) {
            // credentials-1.eml
            $result['Login'] = re('#Your\s+membership\s+number\s+is:\s+(\d+)\.#i', $text);
            $result['LastName'] = re('#Dear\s+M[rs]+\s+(.*?)\s*,#i', $text);
        } elseif (stripos($subject, 'Your e-newsletter for this month') !== false) {
            // credentials-2.eml
            $result['Login'] = re('#Membership\s+Number:\s+(\d+)#i', $text);
            $result['LastName'] = re('#Dear\s+M[rs]+\s+(.*?)\s*,#i', $text);
        }

        return $result;
    }

    public function RetrievePassword($data)
    {
        $this->http->GetURL('https://qmiles.qatarairways.com/ffponline/ffp-online/forgotpassword.jsf');

        if (!$this->http->ParseForm("forgetPassword")) {
            return false;
        }

        $this->http->SetInputValue('forgetPassword:name', $data['Login']);
        $this->http->SetInputValue('forgetPassword:password', $data['Email']);

        $this->http->PostForm();

        if ($this->http->FindPreg('#Request\s+is\s+successful\.\s+Your\s+password\s+will\s+be\s+emailed#i')) {
            return true;
        } else {
            return false;
        }
    }

    public function GetRetrievePasswordCriteria()
    {
        return ['SUBJECT "Forgot Password" FROM "Membersvc@qmiles.com"'];
    }

    public function ParseRetrievePasswordEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $regex = '#Your\s+password\s+for\s+accessing\s+your\s+Privilege\s+Club\s+account\s+is\s+(\S+)#i';
        $text = text($this->http->Response['body']);

        if ($password = re($regex, $text)) {
            $result['Password'] = $password;
        }

        return $result;
    }
}
