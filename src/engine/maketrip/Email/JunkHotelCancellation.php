<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Schema\Parser\Email\Email;

class JunkHotelCancellation extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-49466119.eml, maketrip/it-49708913.eml";
    private $subjects = ['Cancellation of Hotel Booking ID', 'Refund for Cancelled Hotel Booking ID'];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@makemytrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'MakeMyTrip') !== false || stripos($headers['from'], 'makemytrip.com') !== false) {
            foreach ($this->subjects as $phrases) {
                if (stripos($headers['subject'], $phrases) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.makemytrip.com')] | //a[contains(@href,'.makemytrip.com')]")->length > 0) {
            if (($this->http->XPath->query('//text()[contains(., "Cancellation request for")]')->length > 0)
                && ($this->http->XPath->query('//text()[contains(., "Refund Calculation")]')->length > 0)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $email->setType('JunkHotelCancellation');

        if (self::detectEmailByBody($parser) && $this->parseEmail($email)) {
            return $email;
        }

        return null;
    }

    private function parseEmail(Email $email)
    {
        if (empty($this->http->FindSingleNode("//text()[normalize-space()='Rooms']
            /following::text()[normalize-space()][1][normalize-space()='Hotel Fare']
            /following::text()[normalize-space()][1][starts-with(normalize-space(),'Others')]
            /following::text()[normalize-space()][1][normalize-space()='Total Price']"))
        ) {
            return false;
        }

        $cancells = [
            'cancellation' => ['Cancellation Charges', 'Cancellation request for', 'Refund Calculation'],
        ];

        foreach ($cancells as $cancell) {
            if ($this->http->XPath->query("//text()[{$this->eqi($cancell)}]")->length === 0) {
                return false;
            }
        }

        $conditionsNo = [
            'checkOut' => ['check-out', 'checkout', 'check out',
                'check-in',  'checkin',  'check in', ],
        ];

        foreach ($conditionsNo as $conditionNo) {
            if ($this->http->XPath->query("//text()[{$this->eqi($conditionNo)}]")->length > 0) {
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
