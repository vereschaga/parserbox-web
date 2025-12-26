<?php

namespace AwardWallet\Engine\rydges\Credentials;

class ThankYouForJoiningRydgesPriorityGUEST extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'priorityguest@rydges.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Thank you for joining Rydges PriorityGUEST',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = re('#Membership\s+Number\s+-\s+(\w+)#i', $this->text());
        $result['FirstName'] = re('#\s+Dear\s+(.*?)\s+Thank\s+you\s+for\s+joining#i', $this->text());

        return $result;
    }
}
