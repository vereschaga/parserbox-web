<?php

namespace AwardWallet\Engine\stash\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "stash@mail.stashrewards.com"',
            'FROM "noreply@email.stashrewards.com"',
            'FROM "stashrewards@email.stashrewards.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        if (stripos($subject, 'Stash Points balance') !== false) {
            // credentials-{1,2,4}.eml
            $result['Name'] = $this->http->FindSingleNode('//tr[contains(., "Member:") and not(.//tr)]/following-sibling::tr[1]/td[1]');
        } elseif ($s = re('#Hi\s+(.*?)\s*,#i', $text)) {
            // credentials-3.eml
            $result['FirstName'] = $s;
        }

        return $result;
    }
}
