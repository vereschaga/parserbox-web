<?php

namespace AwardWallet\Engine\hawaiian\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "HawaiianAirlines@services.hawaiianairlines.com"',
            'FROM "HawaiianAirlines@em.hawaiianairlines.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        if (stripos($subject, 'Welcome to HawaiianMiles') !== false) {
            // credentials-1.eml
            $result['FirstName'] = re('#Welcome\s+(.*?)\s*,#i', $text);
            $result['Name'] = re('#\s*(.*)\s+Member\s+number#i', $text);
            $result['Login'] = re('#Member\s+number:\s+(\d[\d\s]+\d)#i', $text);
        } elseif ($s = $this->http->FindSingleNode('//text()[contains(., "Member Number:")]/ancestor::td[1]')) {
            // credentials-2.eml
            $result['FirstName'] = re('#Aloha\s+(.*?)\s*,#i', $text);
            $result['Name'] = re('#\s*(.*)\s+Member\s+number#i', $s);
            $result['Login'] = re('#Member\s+number:\s+(\d[\d\s]+\d)#i', $s);
        }

        if (isset($result['Login']) and $result['Login']) {
            $result['Login'] = preg_replace('#\s#i', '', $result['Login']);
        }

        return $result;
    }
}
