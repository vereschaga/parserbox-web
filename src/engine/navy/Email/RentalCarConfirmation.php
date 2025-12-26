<?php

namespace AwardWallet\Engine\navy\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RentalCarConfirmation extends \TAccountChecker
{
    public $mailFiles = "navy/it-706729904.eml, navy/it-706852796.eml";
    public $subjects = [
        'Navy Federal Rewards Car Reservation Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@rewards.navyfederal.org') !== false) {
            foreach ($this->subjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]navyfederal\.org$/', $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Navy Federal Rewards'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Car Pickup'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Total Charges'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Daily rate'))}]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function parseRental(Email $email)
    {
        $r = $email->add()->rental();

        // collect reservation confirmation
        $confirmationText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your reservation number is'))}]/ancestor::td[normalize-space()][1]");

        if (preg_match("/^\s*.+?(?<status>{$this->opt($this->t('Booked'))})\s*\!\s*(?<desc>{$this->opt($this->t('Your reservation number is'))})\:\s*(?<number>\w+)\s*$/m", $confirmationText, $m)) {
            $r->general()
                ->confirmation($m['number'], $m['desc'])
                ->status($m['status']);
        }

        // collect traveller
        $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Thank you for using'))}]/ancestor::span[1]/preceding-sibling::span[normalize-space()][1]", null, true, "/^\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\,\s*$/m");

        if (!empty($traveller)) {
            $r->addTraveller($traveller, true);
        }

        // collect company name
        $company = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Thank you for using'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]/descendant::td[normalize-space()][last() - 1]");

        if (!empty($company)) {
            $r->setCompany($company);
        }

        // collect pickUp/dropOff datetime
        $datesText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Thank you for using'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]/descendant::td[normalize-space()][last()]");

        if (preg_match("/^\s*(?<pickUpDate>.+?)\s+{$this->opt($this->t('To'))}\s+(?<dropOffDate>.+?)\s*$/mi", $datesText, $m)) {
            $r->setPickUpDateTime($this->normalizeDate($m['pickUpDate']));
            $r->setDropOffDateTime($this->normalizeDate($m['dropOffDate']));
        }

        // collect pickup and return location
        $locationsText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Thank you for using'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][2]/descendant::td[not(table)][3]");

        if (preg_match("/^\s*{$this->opt($this->t('Car Pickup'))}\s+(?<pickupLoc>.+?)\s+{$this->opt($this->t('Car Return'))}\s+(?<returnLoc>.+?)\s*$/m", $locationsText, $m)) {
            $r->setPickUpLocation($m['pickupLoc']);
            $r->setDropOffLocation($m['returnLoc']);
        }

        // collect car model
        $carModel = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Thank you for using'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][2]/descendant::td[not(table)][2]", null, true, "/^\s*(.+?\s+{$this->opt($this->t('or similar'))}).+?$/m");

        if (!empty($carModel)) {
            $r->car()
                ->model($carModel);
        }

        // collect car url
        $carUrl = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Thank you for using'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][2]/descendant::td[not(table)][1]/img/@src");

        if (!empty($carUrl)) {
            $r->car()
                ->image($carUrl);
        }

        // collect cost
        $costText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Daily rate'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        if (preg_match("/^\s*(?<currency>\D)\s*(?<cost>[\d\.\,\']+)\s*$/m", $costText, $m)) {
            $r->price()
                ->cost(PriceHelper::parse($m['cost'], $this->normalizeCurrency($m['currency'])))
                ->currency($this->normalizeCurrency($m['currency']));
            $currency = $this->normalizeCurrency($m['currency']);
        }

        // collect tax
        $tax = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Taxes and fees'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^\s*\D\s*([\d\.\,\']+)\s*$/m");

        if (!empty($currency) && $tax !== null) {
            $r->price()
                ->tax(PriceHelper::parse($tax, $currency));
        }

        // collect spent awards (points)
        $spentAwards = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Points applied to your car rental'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^\s*([\d\.\,\']+\s+{$this->opt($this->t('Points'))})\s*$/mi");

        if (!empty($spentAwards)) {
            $r->price()
                ->spentAwards($spentAwards);
        }

        // collect total
        $total = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total amount charged to your card'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^\s*\D\s*([\d\.\,\']+)\s*$/m")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total amount for your car reservation'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^\s*\D\s*([\d\.\,\']+)\s*$/m");

        if (!empty($currency) && $total !== null) {
            $r->price()
                ->total(PriceHelper::parse($total, $currency));
        }

        // collect provider phone
        $phoneInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('If you have questions or need assistance with planning your trip'))}]/ancestor::td[1]");

        if (preg_match("/^.+?(?<desc>{$this->opt($this->t('If you have questions or need assistance with planning your trip'))}).+?(?<phone>\d[\d\-]+\d)\s*\.\s*$/m", $phoneInfo, $m)) {
            $r->addProviderPhone($m['phone'], $m['desc']);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseRental($email);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\w+)\s+(\d+)\,\s+(\d{4})\s+(\d+(?:\:\d+)?\s*\w{2})$#u", // Aug 02, 2024 06:00 PM
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+(\D+)\s+\d{4}\,\s+\d+(?:\:\d+)?\s*\w{2}$#u", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'         => 'EUR',
            'US dollars'=> 'USD',
            '£'         => 'GBP',
            '₹'         => 'INR',
            '$'         => '$',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return $s;
    }
}
