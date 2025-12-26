<?php

namespace AwardWallet\Engine\rewardsnet\Credentials;

class IDine extends \TAccountCheckerExtended
{
    public $mailFiles = [];

    public function GetRemindLoginFields()
    {
        return [
            'Email',
        ];
    }

    public function RemindLogin($data)
    {
        $this->http->GetURL('http://www.idine.com/remindLoginId.htm');

        $this->http->FormURL = 'http://www.idine.com/dwr/call/plaincall/UserService.getLoginIdOfMember.dwr';

        foreach (array_keys($this->http->Form) as $key) {
            unset($this->http->Form[$key]);
        }

        $cookies = $this->http->Request->getResponseCookies();
        $httpSessionId = null;

        foreach ($cookies as $c) {
            if ($c['name'] == 'RNSESSIONID') {
                $httpSessionId = $c['value'];
            }
        }

        if (!$httpSessionId) {
            $this->http->Log('Couldn\'t get httpSessionId', LOG_LEVEL_ERROR);

            return false;
        }

        $requestParameters = [
            'callCount'       => '1',
            'page'            => '/remindLoginId.htm',
            'httpSessionId'   => $httpSessionId,
            'scriptSessionId' => 0, // maybe it's better to use some random number
            'c0-scriptName'   => 'UserService',
            'c0-methodName'   => 'getLoginIdOfMember',
            'c0-id'           => '0',
            'c0-param0'       => 'string:' . strtolower($data['Email']),
            'c0-param1'       => 'string:www.idine',
            'c0-param2'       => 'http://content.idine.com/z/id/d/i/',
            'c0-param3'       => 'boolean:false',
            'batchId'         => '1',
        ];

        foreach ($requestParameters as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }
        $this->http->PostForm();

        if ($this->http->FindPreg('#status\s*:\s*"SUCCESS\s*"#i')) {
            return true;
        } else {
            return false;
        }
    }

    public function GetRemindLoginCriteria()
    {
        return [
            'SUBJECT "Your iDine Login ID Request" FROM "info@idine.com"',
        ];
    }

    public function ParseRemindLoginEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();

        if ($login = re('#Your\s+Login\s+ID\s+is\s+(\S+)\.#i', text($this->http->Response['body']))) {
            $result['Login'] = $login;
        }

        if ($s = re('#Dear\s+(.*?)\s*,#i', text($this->http->Response['body']))) {
            $result['FirstName'] = $s;
        }

        return $result;
    }

    public function GetCredentialsCriteria()
    {
        return [
            'FROM "info@email.idine.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();

        if ($s = re('#Hi,\s+(.*)#i', text($this->http->Response['body']))) {
            $result['FirstName'] = $s;
        }

        return $result;
    }
}
