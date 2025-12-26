<?php

namespace AwardWallet\Engine\norwegiancruise\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return ["norwegiancruiseline@email.ncl.com", "accountservices@ncl.com"];
    }

    public function getCredentialsSubject()
    {
        return ["NCL.COM"];
    }

    public function getParsedFields()
    {
        return ["Email"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        // For debugging only
        // $result = [
        // 'FirstName' => 'Alexi',
        // 'LastName' => 'Vereschaga'
        // ];
        if (stripos($parser->getSubject(), 'NCL.COM Forgot Password') !== false) {
            $result['FirstName'] = re('#Dear\s+(.*?),#i', $this->text());
        }
        $result['Email'] = $parser->getCleanTo();

        return $result;
    }
}
