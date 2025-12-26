<?php

namespace AwardWallet\Engine\panera\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public $mailFiles = ["panera/scanner/Credentials1.eml", "panera/scanner/Credentials2.eml", "panera/scanner/Credentials3.eml"];

    public function GetRemindLoginFields()
    {
        return [
            'Email',
            'FirstName',
            'Login',
        ];
    }

    public function RemindLogin($data)
    {
        $this->http->GetURL('https://www.panerabread.com/en-us/company/mypanera-rewards.html');

        $this->http->FormURL = 'https://www.panerabread.com/pbdyn/reset/forgotPassword';

        foreach (array_keys($this->http->Form) as $key) {
            unset($this->http->Form[$key]);
        }

        $this->http->SetInputValue('forgot_password_email', $data['Email']);

        $this->http->PostForm();

        if ($this->http->Response['body'] == 'true') {
            return true;
        } else {
            return false;
        }
    }

    public function GetRemindLoginCriteria()
    {
        return [
            'SUBJECT "Need help logging in?" FROM "donotreply@panerabread.com"',
        ];
    }

    public function ParseRemindLoginEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);

        if ($login = re('#Your\s+username\s+is\s*:\s+(\S+)#i', $text)) {
            $result['Login'] = $login;
        }

        if ($firstName = re('#Hello\s+(.+?)\s*,#i', $text)) {
            $result['FirstName'] = $firstName;
        }

        return $result;
    }

    public function getCredentialsImapFrom()
    {
        return [
            'guest@mypaneramail.com',
            'rewards@mypanera.com',
            'panera@panerabreadnews.com',
            'Panera@panera.fbmta.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to MyPanera',
            'Celebrate the Season with Us...',
            'You Can Make a Child\'s Holiday Better...',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Email',
            'FirstName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if ($firstName = re('#Hello\s+(.+?)\s*,#i', $text)) {
            $result['FirstName'] = $firstName;
        }

        return $result;
    }
}
