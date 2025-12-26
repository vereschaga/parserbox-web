<?php

namespace AwardWallet\Engine\hotelclub\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "Customer_Service@hotelclub.com",
            "customer_service@hotelclub.com",
            "languages@hotelclub.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Запрос пароля",
            "Your registration confirmation",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Name",
            "Login",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        $result['Name'] = re("#(?:^|\n)\s*Dear\s+(.*?),#", $text);

        if (!$result['Name']) {
            $result['Name'] = trim(re("#(?:^|\n)\s*Уважаемый\(ая\)\s+(.*?),#", $text));
        }

        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
