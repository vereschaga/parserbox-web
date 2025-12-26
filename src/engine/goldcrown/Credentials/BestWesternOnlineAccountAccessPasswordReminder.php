<?php

namespace AwardWallet\Engine\goldcrown\Credentials;

class BestWesternOnlineAccountAccessPasswordReminder extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'password-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'BWR_WS@bestwestern.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Best Western Online Account Access - Password Reminder',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Password',
            'Email',
        ];
    }

    public function getRetrieveFields()
    {
        return [
            'Login',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $url = 'http://www.bestwestern.com/includes/proxy-ajax.asp';
        $url .= '?';
        $url .= 'page=sendPassword';
        $url .= '&';
        $url .= 'searchval=' . $data['Login'];
        $this->http->GetURL($url);

        if (re('#Thank\s+You!\s+Your\s+password\s+has\s+been\s+sent#i', text($this->http->Response['body']))) {
            return true;
        } else {
            return false;
        }
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $parser->getCleanTo();
        $result['Password'] = re('#Password:\s+(\w+)#i', $this->text());

        return $result;
    }
}
