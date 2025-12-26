<?php

namespace AwardWallet\Engine\maximiles\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'specialoffers@maximiles.co.uk',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to Maximiles",
            "Christmas Rewards Delivery Cut Off",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Email',
            'FirstName',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        if ($name = re("#Dear\s+(\w+)#", $this->text())) {
            $result['FirstName'] = $name;
        }

        if ($name = re("#(\w+),\s+Your\s+balance\s+is#msi", $this->text())) {
            $result['FirstName'] = $name;
        }

        if ($name = re("#This\s+email\s+was\s+intended\s+for\s+(\w+\s+\w+)#", $this->text())) {
            $result['Name'] = $name;
        }

        return $result;
    }
}
