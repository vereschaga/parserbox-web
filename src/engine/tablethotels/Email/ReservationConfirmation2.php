<?php

namespace AwardWallet\Engine\tablethotels\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation2 extends \TAccountChecker
{
    public $mailFiles = "tablethotels/it-799245887.eml";
    public $subjects = [
        'Reservation Confirmation for The',
    ];

    public $lang = 'en';

    public $hotelName;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@tablethotels.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Tablet Hotels')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Confirmation number:'))}]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Adults') and contains(normalize-space(), 'Children')]")->length > 0
            && $this->http->XPath->query("//text()[normalize-space()='Hotel Info']")->length > 0
            && $this->http->XPath->query("//text()[normalize-space()='Rooms']")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tablethotels\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (preg_match("/Reservation Confirmation for\s+(.+)\s*\:/", $parser->getSubject(), $m)) {
            $this->hotelName = $m[1];
        }

        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Confirmation number:']/following::text()[normalize-space()][1]"))
            ->travellers($this->http->FindNodes("//text()[contains(normalize-space(), 'Adults') and contains(normalize-space(), 'Children')]/preceding::text()[normalize-space()][1]", null, "/([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])/"))
            ->cancellation($this->http->FindSingleNode("//text()[normalize-space()='Cancellation']/following::text()[normalize-space()][1]"));

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total']/ancestor::tr[1]/descendant::td[normalize-space()][last()]");

        if (preg_match("/^(?<currency>\D{1,3})(?<total>[\d\.\,\']+)/", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $h->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='Taxes & Fees']/ancestor::tr[1]/descendant::td[normalize-space()][last()]", null, true, "/^\D{1,3}\s*([\d\.\,]+)$/");

            if ($tax !== null) {
                $h->price()
                    ->tax(PriceHelper::parse($tax, $currency));
            }

            $fee = $this->http->FindSingleNode("//text()[normalize-space()='Resort Fees']/ancestor::tr[1]/descendant::td[normalize-space()][last()]", null, true, "/^\D{1,3}\s*([\d\.\,]+)$/");

            if ($fee !== null) {
                $h->price()
                    ->fee('Resort Fees', PriceHelper::parse($fee, $currency));
            }
        }

        $roomType = $this->http->FindSingleNode("//text()[normalize-space()='Rooms']/following::text()[normalize-space()][1]");

        if (!empty($roomType)) {
            $room = $h->addRoom();
            $room->setType($roomType);

            $rates = $this->http->FindNodes("//text()[normalize-space()='Taxes & Fees']/ancestor::tr[1]/preceding-sibling::tr");

            if ($rates > 0) {
                $room->setRates($rates);
            }
        }

        $guestText = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Adults') and contains(normalize-space(), 'Children')]");

        if (preg_match("/^(?<adults>\d+)\s+Adults?\,\s*(?<kids>\d+)\s*Children$/", $guestText, $m)) {
            $h->booked()
                ->guests($m['adults'])
                ->kids($m['kids']);
        }

        $dateText = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Adults') and contains(normalize-space(), 'Children')]/preceding::text()[normalize-space()][2]");

        if (preg_match("/^(?<inDate>.+\d{4})\s+\-\s+(?<outDate>.+\d{4})$/", $dateText, $m)) {
            $h->booked()
                ->checkIn(strtotime($m['inDate']))
                ->checkOut(strtotime($m['outDate']));
        }

        $hotelInfo = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Hotel Info']/ancestor::td[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/Hotel Info\n(?<hotelName>.+)\n(?<address>(?:.+\n){1,})(?<phone>[+][\d\s\(\)]+)/", $hotelInfo, $m)) {
            $h->hotel()
                ->name($m['hotelName'])
                ->address(str_replace("\n", " ", $m['address']))
                ->phone($m['phone']);
        }

        $this->detectDeadLine($h);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function detectDeadLine(Hotel $h)
    {
        if (empty($h->getCancellation())) {
            return;
        }

        if (preg_match('/Non-Refundable/', $h->getCancellation(), $m)) {
            $h->booked()->nonRefundable();
        }

        if (preg_match('/Free Cancellation by\s*(\w+\s*\d+\,\s*\d{4})/', $h->getCancellation(), $m)) {
            $h->booked()->deadline(strtotime($m[1]));
        }

        if (preg_match('/You may cancel free of charge until (\d+ days?) before arrival/', $h->getCancellation(), $m)) {
            $h->booked()->deadlineRelative($m[1]);
        }
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'SGD' => ['SG$'],
            'ZAR' => ['R'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
