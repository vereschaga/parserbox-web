<?php

namespace AwardWallet\Engine\sendearnings\Credentials;

class ClaimYourSweepstakesEntriesForReturningToSendEarnings extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'Credentials2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'support@sendearnings.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Claim your Sweepstakes entries for returning to SendEarnings!',
        ];
    }

    public function getParsedFields()
    {
        return [
            'FirstName',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $subject = $parser->getSubject();
        $result['FirstName'] = re('#(?:Hi|Hello),?\s+(\w+)#i', $text);
        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
