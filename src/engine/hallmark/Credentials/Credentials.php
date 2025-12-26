<?php

namespace AwardWallet\Engine\hallmark\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "hallmark@update.hallmark.com"',
            'FROM "accounts@hallmarkonline.com"',
            'FROM "mbrinfo@hallmarkonline.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);
        $result['Login'] = $parser->getCleanTo();

        if (preg_match('#(.*), welcome to Hallmark.com#', $subject, $m)) {
            // credentials-1.eml
            $result['FirstName'] = $m[1];
            $result['Name'] = re('#Hi\s+(.*?)\s*,#i', $text);
        }
        //		} elseif (stripos($subject, 'welcome to Crown Rewards') !== false) {
        //			// credentials-2.eml
        //			$result['FirstName'] = re('#(.*), thanks for signing up#i', $text);
        //		} elseif (stripos($subject, 'E-Statement') !== false) {
        //			// credentials-3.eml
        //			$result['Name'] = re('#\s*(.*)\s+MEMBER NUMBER#i', $text);
        //		}
        return $result;
    }
}
