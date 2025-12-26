<?php

namespace AwardWallet\Engine\westjet\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "welcome@mywestjet.com"',
            'FROM "info@mywestjet.com"',
            'FROM "noreply@share.westjet.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $subject = $parser->getSubject();
        $text = str_replace('=', '', text($this->http->Response['body']));

        if (stripos($subject, 'Welcome to the WestJet Frequent Guest Program') !== false) {
            // credentials-1.eml
            $result['Name'] = re('#Guest\s+Name\s+(.*)#i', $text);
        } elseif (stripos($subject, 'WestJet Rewards update') !== false) {
            // credentials-2.eml
            $result['Name'] = $this->http->FindSingleNode('//tr[contains(., "Member name:") and contains(., "WestJet ID:") and not(.//tr)]/following-sibling::tr[1]/td[1]');

            if ($result['Name']) {
                $result['Name'] = nice(str_replace('=', '', $result['Name']));
            }
        }
        //		if ($login = re('#WestJet\s+ID\s+(\d+)#i', $text))
        //			$result['Login'] = $login;
        //		elseif ($login = re('#(\d+)#i', $this->http->FindSingleNode('//tr[contains(., "Member name:") and contains(., "WestJet ID:") and not(.//tr)]/following-sibling::tr[1]/td[last()]')))
        //			$result['Login'] = $login;
        return $result;
    }
}
