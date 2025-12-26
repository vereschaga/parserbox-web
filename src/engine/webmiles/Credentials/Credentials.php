<?php

namespace AwardWallet\Engine\webmiles\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'webmiles.info@webmiles.ch',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Ihr\s+aktueller\s+Kontostand#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            // 'Number',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['Login'] = $parser->getCleanTo();
        // $result['Number'] = re("#lautet:\s*([^\s]+)#i", $text);
        $result['Name'] = re("#\n\s*Lieber Herr ([^\n,]+)#i", $text);

        return $result;
    }
}
