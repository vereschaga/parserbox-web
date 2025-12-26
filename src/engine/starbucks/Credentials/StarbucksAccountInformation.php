<?php

namespace AwardWallet\Engine\starbucks\Credentials;

class StarbucksAccountInformation extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'login-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'orders@starbucks.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Starbucks Account Information',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login!',
            'Email',
        ];
    }

    public function getRetrieveFields()
    {
        return [
            'Email',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->GetURL('https://www.starbucks.com/account/forgot-username?AllowGuest=False');
        $res = $this->http->ParseForm('accountForm');

        if (!$res) {
            $this->http->Log('Failed to parse form', LOG_LEVEL_ERROR);

            return false;
        }

        $this->http->SetInputValue('Account.EmailAddress', $data['Email']);

        $res = $this->http->PostForm();

        if (!$res) {
            $this->http->Log('Failed to post form', LOG_LEVEL_ERROR);

            return false;
        }

        if ($this->http->FindPreg('#Please\s+check\s+your\s+email\s+to\s+retrieve\s+your\s+username\.#i')) {
            return true;
        } else {
            return false;
        }
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login!'] = re('#We\s+have\s+1\s+on\s+file\s*:\s+(\S+)\s+We\s+hope\s+you#i', $text);

        return $result;
    }
}
