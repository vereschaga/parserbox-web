<?php

namespace AwardWallet\Engine\groupon\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "notify@r.groupon.com"',
            'FROM "noreply@r.groupon.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = $parser->getCleanTo();

        if (stripos($parser->getHeader('from'), 'Groupon Goods') !== false) {
            // credentials-1.eml
            $result['FirstName'] = re('#For\s+(.*?)\s+\|#i', $text);
        } elseif (preg_match('#^Groupon\s+<#i', $parser->getHeader('from'))) {
            // credentials-2.eml
            $result['FirstName'] = re('#Hey\s+(.*?),#i', $text);
        }

        return $result;
    }
}
