<?php

namespace AwardWallet\Engine\thaiair\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "ropsvc@thaiairways.com"',
            'FROM "enews@royal-orchid-plus.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        if (stripos($subject, 'Royal Orchid Plus Enrolment Confirmation') !== false) {
            // credentials-1.eml
            $result['Name'] = nice(re('#Dear\s+M[rs]+\.\s+(.*)\s+Thank\s+you#i', $text));
            $result['Login'] = re('#Your\s+membership\s+number\s+is\s+(\S+)#i', $text);
            $result['Password'] = re('#Your\s+PIN\s+is\s+(\S+)#i', $text);
        } elseif (preg_match('#Dear\s+M[rs]+\.\s+(.*)\s+\s+Membership\s+No.\s*(\S+)#i', $text, $m)) {
            // credentials-2.eml
            $result['Name'] = nice($m[1]);
            $result['Login'] = $m[2];
        } elseif (preg_match('#Dear\s+M[rs]+\.\s+(.*),\s+Your\s+PIN\s+code\s+is\s*:\s+(\S+)#i', $text, $m)) {
            // credentials-3.eml
            $result['Name'] = nice($m[1]);
            $result['Password'] = $m[2];
        }

        return $result;
    }
}
