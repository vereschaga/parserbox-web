<?php

namespace AwardWallet\Engine\marriottvacationclub\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationReceipt2 extends \TAccountChecker
{
    public $mailFiles = "marriottvacationclub/it-99459214.eml";
    public $subjects = [
        '/Marriott Vacation Club Destinations Confirmation Receipt[\s\-]+Reservation/s',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Marriott Bonvoy Number' => ['Marriott Bonvoy Number', 'Marriott Rewards Number'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@vacationclub.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Marriott Vacation Club')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Transaction Date:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Arrival Information'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]vacationclub\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation Number:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation Number:'))}\s*(\d+)/"))
            ->date(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Transaction Date:']/following::text()[normalize-space()][1]")));

        $travellers = $this->http->FindNodes("//text()[normalize-space()='Additional Guests']/ancestor::td[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Name') or contains(normalize-space(), 'Additional Guests') or contains(normalize-space(), 'No'))]");

        if (count($travellers) > 0) {
            $h->general()
                ->travellers($travellers, true);
        } else {
            $traveller = $this->http->FindSingleNode("//text()[normalize-space()='Primary Guest']/following::text()[normalize-space()='Name']/ancestor::td[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Name'))]");

            if (!empty($traveller)) {
                $h->general()
                    ->traveller($traveller, true);
            }
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[normalize-space()='Transaction Date:']/following::a[1]"))
            ->address($this->http->FindSingleNode("//text()[normalize-space()='Transaction Date:']/following::a[1]/following::text()[normalize-space()][1]"))
            ->phone($this->http->FindSingleNode("//text()[normalize-space()='Transaction Date:']/following::a[1]/following::text()[normalize-space()][2]", null, true, "/{$this->opt($this->t('Phone:'))}\s*([\d\s\-]+)/u"));

        $roomType = $this->http->FindSingleNode("//text()[normalize-space()='Room Type:']/following::text()[normalize-space()][1]");

        if (!empty($roomType)) {
            $room = $h->addRoom();
            $room->setType($roomType);
        }

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Check-In:']/following::text()[normalize-space()][1]")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Check-Out:']/following::text()[normalize-space()][1]")))
            ->guests($this->http->FindSingleNode("//text()[normalize-space()='# Of Guests:']/following::text()[normalize-space()][1]"));

        $accounts = $this->http->FindNodes("//text()[{$this->eq($this->t('Marriott Bonvoy Number'))}]/following::text()[normalize-space()][1]");
        $h->setAccountNumbers($accounts, false);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }
}
