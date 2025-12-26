<?php

namespace AwardWallet\Engine\mabuhay\Credentials;

class Credentials2 extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'mabuhaymiles_email@mabuhaymiles.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Mabuhay Miles Account Registration Confirmation",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Email',
            'Name',
            'Login',
            'Number',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Email'] = $parser->getCleanTo();
        $result['Name'] = re('#Dear M[rsi]+ (.*?),#i', $text);
        $result['Login'] = $result['Number'] = re('#Mabuhay Miles Member ID\s*:\s*00(\d+)#i', $text);

        return $result;
    }
}
