<?php

namespace AwardWallet\Engine\officemax\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "officemax@mailbox.officemax.com"',
            'FROM "online@officemax.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $parser->getCleanTo();
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        if (stripos($subject, 'Welcome to MaxPerks') !== false) {
            $result['Name'] = re('#Name\s*:\s+(.*)#i', $text);
        } elseif (stripos($subject, 'Welcome to OfficeMax') !== false) {
            $result['Name'] = re('#Hello\s+(.*?)\s*,#i', $text);

            if ($result['Name']) {
                $result['Name'] = str_replace('-->', '', $result['Name']);
            }
        }

        return $result;
    }
}
