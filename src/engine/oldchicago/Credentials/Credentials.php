<?php

namespace AwardWallet\Engine\oldchicago\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'OCRewards@oldchicago.fbmta.com',
            'ocrewards@oldchicago.fbmta.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome To OC Rewards",
            "Winter Mini Tour  - Dock Labor Dispute Impact",
            "The Winter Mini Tour Is About To Kick Off",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Email',
            'FirstName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        if ($name = re("#(\w+)\s*,\s+you're\s+good\s+to\s+go#", $this->text())) {
            $result['FirstName'] = $name;
        }

        if ($name = re("#Hello\s+(\w+)#", $this->text())) {
            $result['FirstName'] = $name;
        }

        if ($name = re("#(\w+)\s*,\s+Join\s+your\s+Conway\s+Old\s+Chicago#msi", $this->text())) {
            $result['FirstName'] = $name;
        }

        return $result;
    }
}
