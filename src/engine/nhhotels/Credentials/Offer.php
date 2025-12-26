<?php

namespace AwardWallet\Engine\nhhotels\Credentials;

class Offer extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-3.eml',
        'credentials-4.eml',
        'credentials-5.eml',
        'credentials-6.eml',
        'credentials-7.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'info@news.nh-hotels.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'With your NH Rewards exclusive rate you always pay less',
            ', book in advance and save!',
            ', remember to book in advance and save!',
            ', come back to NH and we\'ll give you 20 euros',
            ', from the bottom of our hearts, Merry Christmas!',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            // 'FirstName',
            'Name',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        // credentials-{4-7}.eml
        // $result['FirstName'] = re('#^([A-Z]+),\s+#i', $parser->getSubject());
        // credentials-{3,6,7}.eml
        $result['Name'] = re('#\s*(.*)\s+Member\s+No\.#i', $text);

        return $result;
    }
}
