<?php

namespace AwardWallet\Engine\hiltongvc\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VacationConfirmed extends \TAccountChecker
{
    public $mailFiles = "hiltongvc/it-268501531.eml";
    public $subjects = [
        'Vacation Confirmed: Thank You For Booking Your Dates',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'HHonor Points' => ['HHonor Points', 'Hilton Honors Points', 'HHonors Points', 'Hon ors Points'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@transactions.hiltongrandvacations.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains('Hilton Grand Vacations Inc.')}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for booking your trip'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Package Details'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Tour Information'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]transactions.hiltongrandvacations.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotel($email);

        $this->ParseEvent($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation Number:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation Number:'))}\s*([\d\-]+)/"));

        $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thank you for booking your trip')]/preceding::text()[normalize-space()][1]", null, true, "/^([A-Z]+)\,/");

        if (!empty($traveller)) {
            $h->general()
                ->traveller($traveller);
        }

        $hotelName = $this->http->FindSingleNode("//text()[normalize-space()='Your Package Details']/following::text()[normalize-space()][1]/ancestor::*[1]");

        $hotelInfo = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Your Package Details']/following::text()[normalize-space()][1]/ancestor::p[1]/descendant::text()[normalize-space()]"));

        if (empty($hotelInfo)) {
            $hotelInfo = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Your Package Details']/following::text()[normalize-space()][1]/ancestor::table[1]/descendant::text()[normalize-space()]"));
        }

        if (preg_match("/^$hotelName\n\s*(?<address>.+)\n(?<phone>[\d\-\(\)\s]+)(?:\n|$)/msu", $hotelInfo, $m)
        || preg_match("/^$hotelName\s*(?<address>.+)$/su", $hotelInfo, $m)) {
            $h->hotel()
                ->name($hotelName)
                ->address(str_replace("\n", "", $m['address']));

            if (isset($m['phone'])) {
                $h->hotel()
                    ->phone($m['phone']);
            }
        }

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Arrival Date:')]/following::text()[normalize-space()][1]", null, true, "/^([\d\/]+)$/")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Departure Date:')]/following::text()[normalize-space()][1]", null, true, "/^([\d\/]+)$/")));

        $total = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Price:')]/following::text()[normalize-space()][1]", null, true, "/^([\d\.\,]+)$/");
        $currency = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Balance Due:')]/following::text()[normalize-space()][1]", null, true, "/^\s*(\D+)\d/");

        if (!empty($currency)) {
            $currency = $this->normalizeCurrency($currency);
        }

        if (!empty($total) && !empty($currency)) {
            $h->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);
        } elseif (!empty($total)) {
            $h->price()
                ->total($total);
        }

        $packageDescription = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Package Description')]/following::text()[normalize-space()][1]");

        if (preg_match("/\s([\d\.\,]+\s*{$this->opt($this->t('HHonor Points'))})/is", $packageDescription, $m)) {
            $h->setEarnedAwards($m[1]);
        }
    }

    public function ParseEvent(Email $email)
    {
        $eventInfo = $this->http->FindSingleNode("//text()[normalize-space()='Your Tour Information']/following::text()[normalize-space()][1]/ancestor::td[1]");
        $eventName = $this->http->FindSingleNode("//text()[normalize-space()='Your Tour Information']/following::text()[normalize-space()][1]");

        if (preg_match("/$eventName\s*(?<address>.+)\s*Tour Date\:\s*(?<date>[\d\/]+)\s*Tour Time:\s*(?<time>(?:[\d\:]+\s*A?P?M?|TBD))$/", $eventInfo, $m)) {
            $e = $email->add()->event();

            $e->general()
                ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation Number:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation Number:'))}\s*([\d\-]+)/"));

            $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thank you for booking your trip')]/preceding::text()[normalize-space()][1]", null, true, "/^([A-Z]+)\,/");

            if (!empty($traveller)) {
                $e->general()
                    ->traveller($traveller);
            }

            $e->setEventType(4);

            $e->setName($eventName);

            $e->setAddress($m['address']);

            if (stripos($m['time'], ':') !== false) {
                $e->setStartDate(strtotime($m['date'] . ', ' . $m['time']));
            } else {
                $e->setStartDate(strtotime($m['date']));
            }

            $e->booked()
                ->noEnd();
        }
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

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            '$'   => ['$'],
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
