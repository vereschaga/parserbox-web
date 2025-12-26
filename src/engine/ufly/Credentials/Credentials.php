<?php

namespace AwardWallet\Engine\ufly\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'support@ufly.suncountry.com',
            'do-not-reply@suncountry.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Ufly Rewards Enrollment Confirmation',
            'Ufly Rewards Password Reset',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Email',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);
        $result['Name'] = nice(re('#Dear\s+(.*?)\s*,#i', $text));
        $result['Login'] = re('#rewards\s+Number\s*:\s+(\d+)#i', $text);

        return $result;
    }
}
