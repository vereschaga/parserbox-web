<?php

namespace AwardWallet\Engine\parknfly\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "info@parknfly.messages3.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            " ",
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
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);
        $result['FirstName'] = orval(
            re('#\s+Dear\s+(.*?)\s*,\s+#', $text),
            re('#Frequent\s+Parker\s+Newsletter\s+Hi\s+(.*?),#', $text)
        );

        return $result;
    }
}
