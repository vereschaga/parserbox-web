<?php

namespace AwardWallet\Engine\goldpassport\Email;

use AwardWallet\Schema\Parser\Email\Email;

class JunkThankYou extends \TAccountChecker
{
    public $mailFiles = "goldpassport/it-32868551.eml, goldpassport/it-32936869.eml";
    private $subjects = [
        'en' => ['Thank You,'],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@hyatt.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from'], $headers['subject']) && self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'],
                'Hyatt Reservation') === false
        ) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(.,"@hyatt.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,".hyatt.com/")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->http->XPath->query("//text()[contains(normalize-space(),'Thank you for choosing Hyatt')]")->length > 0
            && $this->http->XPath->query("//text()[normalize-space()='Thank You']");
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $email->setType('JunkThankYou');

        if (self::detectEmailByBody($parser) && $this->parseEmail($email)) {
            return $email;
        }

        return null;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function parseEmail(Email $email)
    {
        $conditions = [
            'Thank You',
            'Book Your Next Reservation',
            'Want the best rates available on Hyatt rooms? Book direct.',
        ];

        foreach ($conditions as $condition) {
            if ($this->http->XPath->query("//text()[normalize-space()='{$condition}']")->length === 0) {
                $this->logger->debug("[not found TEXT / should be]: " . $condition);

                return false;
            }
        }

        $conditionsNo = [
            'checkIn' => ['check-in', 'checkin', 'check in'],
        ];

        foreach ($conditionsNo as $conditionNo) {
            if ($this->http->XPath->query("//text()[{$this->eqi($conditionNo)}]")->length > 0) {
                $this->logger->debug("[found TEXT(s) / shouldn't be]: " . var_export($conditionNo, true));

                return false;
            }
        }

        $email->setIsJunk(true);

        return true;
    }

    private function eqi($field, $node = '.'): string
    {
        $field = (array) $field;
        $texts = $field;
        $field = [];

        foreach ($texts as $text) {
            $field[] = strtoupper($text);
            $field[] = strtolower($text);
            $field[] = ucwords($text);
            $field[] = ucfirst($text);
        }

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }
}
