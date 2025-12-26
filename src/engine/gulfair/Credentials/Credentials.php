<?php

namespace AwardWallet\Engine\gulfair\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'ffp@gulfair.com',
            'ffp@news.gulfair.com',
            'ffp@newsletter.gulfair.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Congratulations, you have been enrolled in Gulf Air FFP",
            "Did you Vote yet?",
            "Double Your Miles? Read Falconflyer Zone June 2014 for details",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        $result['Email'] = $parser->getCleanTo();

        if (re("#Your membership no[\s.:]+([A-Z\d-]+)#ix", $text)) { // Credentials-1.eml
            $result['Login'] = re(1);
        } elseif (re("#(?:^|\n)\s*(\d+)\s*\n\s*Dear\s+#i", $text)) { // Credentials-2.eml
            $result['Login'] = re(1);
        } elseif (re("#\n\s*Membership no[\s.:]+([A-Z\d-]+)#ix", $text)) { // Credentials-3.eml
            $result['Login'] = re(1);
        }

        if (re("#(?:^|\n)\s*Dear ([^\n,]+)#ix", $text)) {
            $result['Name'] = re(1);
        }

        // clear Mr/Mrs
        if (isset($result['Name'])) {
            $result['Name'] = clear("#^Mrs?\.?\s+#i", $result['Name']);
        }

        return $result;
    }
}
