<?php

namespace AwardWallet\Engine\taj\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'Innercircle@tajhotels.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Points Update",
            "points update",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Name',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['Email'] = $parser->getCleanTo();
        $result['Name'] = re('#\n\s*(.*?)\n\s*Membership\s*:#i', $text);
        $result['Login'] = re('#Membership:\s*([\w\-]+)#', $text);

        // clear Mr/Mrs
        $result['Name'] = clear("#^Mrs?\.?\s+#i", $result['Name']);

        return $result;
    }
}
