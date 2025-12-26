<?php

namespace AwardWallet\Engine\jdpower\Credentials;

class Credentials extends \TAccountChecker
{
    public function getCredentialsImapFrom()
    {
        return [
            "jdpowerpanel@surveyhelpcenter.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Confirm your JDPowerPanel Account",
            "#New \S[\d\.]+ Survey Now Available#",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Email",
            "Login",
            "FirstName",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result = [];
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        if ($name = re("#Hello,?\s+(\w+)#", $text)) {
            $result['FirstName'] = beautifulName($name);
        } elseif ($name = re("#^(\w+)\s+-\s+#", $parser->getHeader('subject'))) {
            $result['FirstName'] = beautifulName($name);
        }

        return $result;
    }
}
