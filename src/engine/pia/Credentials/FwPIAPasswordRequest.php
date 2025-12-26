<?php

namespace AwardWallet\Engine\pia\Credentials;

class FwPIAPasswordRequest extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'Password.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'servicecenter@piac.aero',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'PIA - Password Request',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Email',
            'Login',
            'Password',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $subject = $parser->getSubject();
        $result['Name'] = reni('Dear (.+?) This is in response', $text);
        $result['Login'] = reni('Your Frequent Flyer Number is (\w+)', $text);
        $result['Password'] = trim(rew('Your PIN is (.+?) Thank you', $text));

        return $result;
    }
}
