<?php

namespace AwardWallet\Engine\rewardsnet\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public $mailFiles = [];

    public function getCredentialsImapFrom()
    {
        return [
            "midwest@rewardsnetwork.com",
            "mpdining@rewardsnetwork.com",
            "skymiles@rewardsnetwork.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Rewards Network#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Email',
            'Name',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if ($login = $this->http->FindPreg("#\s+Login\s+ID(?:<[^>]+>|\s)*?:(?:<[^>]+>|\s)*?([a-z_.A-Z\d\-]+)#")) {
            $result['Login'] = $login;
        }

        if ($s = re('#Dear\s+(.*?)\s*,#i', $text)) {
            $result['Name'] = $s;
        }

        if ($s = re('#Full\s+Name\s*:\s+(.*)#i', $text)) {
            $result['Name'] = $s;
        }

        return $result;
    }
}
