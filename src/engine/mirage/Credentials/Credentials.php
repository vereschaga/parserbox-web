<?php

namespace AwardWallet\Engine\mirage\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'mlife@casino.mgmresorts.com',
            'welcome@mlife.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            ' ',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Email',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if ($name = re('#This\s+email\s+is\s+intended\s+for\s+((?s).*?)\s+M\s+life\s+Account#i', $text)) {
            // credentials-1.eml
            $result['Name'] = nice($name);
        } elseif ($name = re('#\s*(.*)\s+-\s+\d+\s+\|\s+My\s+Account#i', $text)) {
            // credentials-2.eml
            $result['Name'] = nice($name);
        }

        return $result;
    }
}
