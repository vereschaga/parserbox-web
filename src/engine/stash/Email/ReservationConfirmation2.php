<?php

namespace AwardWallet\Engine\stash\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation2 extends \TAccountChecker
{
    public $mailFiles = "stash/it-434838677.eml";
    public $subjects = [
        'Reservation Confirmation:',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mail.stashrewards.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Stash Hotel Rewards')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('YOUR STAY DETAILS:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('VIEW YOUR ITINERARY'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mail\.stashrewards\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[normalize-space()='Guest Name:']/following::text()[normalize-space()][1]"))
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Hotel Confirmation #*:']/following::text()[normalize-space()][1]", null, true, "/^\s*([\d\-A-Z]+)$/"))
            ->cancellation($this->http->FindSingleNode("//text()[normalize-space()='Hotel Cancellation Policy:']/following::text()[normalize-space()][1]"));

        $hotelInfo = implode("\n", $this->http->FindNodes("//text()[normalize-space()='YOUR STAY DETAILS:']/following::text()[normalize-space()][1]/ancestor::span[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^(?<hotelName>.+)\n(?<address>(?:.+\n){1,})(?<phone>\d+[\d\.]+)/", $hotelInfo, $m)) {
            $h->hotel()
                ->name($m['hotelName'])
                ->address(str_replace("\n", " ", $m['address']))
                ->phone($m['phone']);
        }

        $checkIn = $this->http->FindSingleNode("//text()[normalize-space()='Check-in:']/following::text()[normalize-space()][1]");
        $inTime = $this->http->FindSingleNode("//text()[normalize-space()='Check-in time:']/following::text()[normalize-space()][1]");

        $checkOut = $this->http->FindSingleNode("//text()[normalize-space()='Check-out:']/following::text()[normalize-space()][1]");
        $ouTime = $this->http->FindSingleNode("//text()[normalize-space()='Check-out time:']/following::text()[normalize-space()][1]");
        $ouTime = str_replace(["Noon"], ["12:00"], $ouTime);

        $h->booked()
            ->checkIn(strtotime($checkIn . ', ' . $inTime))
            ->checkOut(strtotime($checkOut . ', ' . $ouTime))
            ->rooms($this->http->FindSingleNode("//text()[normalize-space()='Number of rooms:']/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/"))
            ->guests($this->http->FindSingleNode("//text()[normalize-space()='Number of adults:']/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/"))
            ->kids($this->http->FindSingleNode("//text()[normalize-space()='Number of children:']/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/"));

        $h->addRoom()->setType($this->http->FindSingleNode("//text()[normalize-space()='Room type:']/following::text()[normalize-space()][1]"));

        $this->detectDeadLine($h);

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total:']/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>\D)\s*(?<total>[\d\.\,]+)/", $price, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $h->price()
                ->tax($this->http->FindSingleNode("//text()[normalize-space()='Taxes & Fees:']/following::text()[normalize-space()][1]", null, true, "/^\D*([\d\.\,]+)$/"))
                ->cost($this->http->FindSingleNode("//text()[normalize-space()='Room Price:']/following::text()[normalize-space()][1]", null, true, "/^\D*([\d\.\,]+)$/"));
        }
    }

    public function ParseStatement(Email $email)
    {
        $st = $email->add()->statement();

        $info = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Hi') and contains(normalize-space(), 'Points')]");

        if (preg_match("/^Hi\s*(\D+)\s*\|\s*([\d\,\,]+)\s*Points$/", $info, $m)) {
            $st->addProperty('Name', $m[1]);
            $st->setBalance(str_replace(',', '', $m[2]));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotel($email);

        if ($this->http->XPath->query("//text()[normalize-space()='MY ACCOUNT']")->length > 0) {
            $this->ParseStatement($email);
        }

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

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("#Cancel\s*(?<priorHours>\d+)\s*hours\s*prior\s*to arrival by (?<time>\d+a?p?m) local time to avoid#i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['priorHours'] . ' hours', $m['time']);
        }
    }
}
