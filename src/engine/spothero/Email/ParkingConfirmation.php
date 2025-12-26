<?php

namespace AwardWallet\Engine\spothero\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ParkingConfirmation extends \TAccountChecker
{
    public $mailFiles = "spothero/it-695996775.eml, spothero/it-696110023.eml";
    public $subjects = [
        'SpotHero Parking Confirmation - Check Your Parking Pass',
    ];

    public $lang = 'en';

    public $date;

    public $fee = ['Service Fee', 'Facility Fee'];

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@spothero.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'SpotHero')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('View Parking Pass'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Get Direction'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Happy Parking!'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Payment'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]spothero\.com/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        $this->ParseParking($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseParking(Email $email)
    {
        $p = $email->add()->parking();

        $p->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Rental ID:')]", null, true, "/{$this->opt($this->t('Rental ID:'))}\s*(\d{5,})$/"))
            ->status($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reservation')]", null, true, "/{$this->opt($this->t('Reservation'))}\s*(\w+)/"));

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total']/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,\']+)$/", $price, $m)) {
            $p->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Subtotal']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D{1,3}([\d\.\,\']+)$/");

            if (!empty($cost)) {
                $p->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            foreach ($this->fee as $fee) {
                $feeSum = $this->http->FindSingleNode("//text()[{$this->eq($fee)}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D{1,3}([\d\.\,\']+)$/");

                if (!empty($feeSum)) {
                    $p->price()
                        ->fee($fee, PriceHelper::parse($feeSum, $m['currency']));
                }
            }
        }

        $location = $this->http->FindSingleNode("//img[contains(@src, 'time')]/preceding::text()[normalize-space()][1]/ancestor::p[1]");

        $address = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Enter at:')]", null, true, "/{$this->opt($this->t('Enter at:'))}\s*(.+)/");

        if (empty($address) && stripos($location, '-') !== false) {
            $address = $this->re("/^(.+)\s\-/", $location);
        }

        $p->place()
            ->location($location)
            ->address($address);

        $phone = $this->http->FindSingleNode("//img[contains(@src, 'phone')]/following::text()[normalize-space()][1]", null, true, "/^([\s\d\(\)\-]+)$/");

        if (!empty($phone)) {
            $p->place()
                ->phone($phone);
        }

        $dateText = $this->http->FindSingleNode("//img[contains(@src, 'time')]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<depDate>\w+\s*\w+\s*\d+\,\s*[\d\:]+\s*A?P?M)\s+\-\s+(?<arrDate>\w+\s*\w+\s*\d+\,\s*[\d\:]+\s*A?P?M)/", $dateText, $m)) {
            $p->booked()
                ->start($this->normalizeDate($m['depDate']))
                ->end($this->normalizeDate($m['arrDate']));
        } elseif (preg_match("/^(?<date>\w+\s*\w+\s*\d+)\,\s*(?<depTime>[\d\:]+\s*A?P?M)\s*\-\s*(?<arrTime>[\d\:]+\s*A?P?M)$/", $dateText, $m)) {
            $p->booked()
                ->start($this->normalizeDate($m['date'] . ', ' . $m['depTime']))
                ->end($this->normalizeDate($m['date'] . ', ' . $m['arrTime']));
        }

        $plate = $this->http->FindSingleNode("//img[contains(@src, 'car')]/following::text()[normalize-space()][1]/ancestor::div[1]", null, true, "/\|?\s*([A-Z\d]{5,})\s+/");
        $p->booked()
            ->plate($plate, true, true);

        $car = $this->http->FindSingleNode("//img[contains(@src, 'car')]/following::text()[normalize-space()][1]");

        if (stripos($car, 'Missing Vehicle') !== false) {
            $car = '';
        }

        if (!empty($car)) {
            $p->booked()
                ->car($car);
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            '/^(\w+\s*\w+\s*\d+)\,\s*([\d\:]+\s*A?P?M)$/u', // Thu Jun 27, 9:30 AM
        ];
        $out = [
            '$1 ' . $year . ', $2',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }
}
