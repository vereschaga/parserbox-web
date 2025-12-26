<?php

namespace AwardWallet\Engine\zoom\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'support@zoombucks.com',
            'support@surveyhelpcenter.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Confirm Your ZoomBucks Surveys Account",
            "Welcome to ZoomBucks.com! Free Promo Code Inside",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Email",
            "FirstName",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result['Email'] = $parser->getCleanTo();
        $result['FirstName'] = re("#Hello\s+([^,]+),#", $text);

        return $result;
    }
}
