<?php

namespace AwardWallet\Engine\priceline\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'deals@emails.priceline.com',
            'registration@service.priceline.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#.#",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Email',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);
        $regexs = [
            '#\s*(.*)\'s\s+(?:Express\s+)?Hotel\s+Deals#i',
            '#\s*(.*)\'s\s+Car\s+Deals#i',
            '#\s*(.*)\'s\s+Round-trip\s+Flight\s+Deals#i',
            '#Top\s+Deals\s+for\s+(.*)#i',
        ];

        foreach ($regexs as $r) {
            if ($firstName = re($r, $text)) {
                $result['FirstName'] = $firstName;
            }
        }

        return $result;
    }
}
