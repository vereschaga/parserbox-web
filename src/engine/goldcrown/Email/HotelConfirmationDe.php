<?php

namespace AwardWallet\Engine\goldcrown\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class HotelConfirmationDe extends \TAccountChecker
{
    public $mailFiles = "goldcrown/it-39925959.eml, goldcrown/it-433989148.eml, goldcrown/it-665531755.eml";

    public $lang = '';
    public $currency;

    public $LangDetect = [
        "en" => ["Booking number"],
        "de" => ["Buchungsnummer"],
    ];

    public static $dictionary = [
        'de' => [
        ],

        'en' => [
            'Buchungsnummer'                => 'Booking number:',
            'Ihre persönlichen Kundendaten' => 'Your personal customer data',
            'Erwachsene'                    => 'Adult',
            'Zimmer:'                       => 'Room:',
            'Anreise/Abreise'               => 'Arrival/Departure:',
            'Gesamtsumme'                   => 'Total amount',
            'Stornierbar bis:'              => 'Can be canceled until:',
            'Nacht'                         => 'Night',
            'Uhr am Anreisetag'             => 'on the date of arrival',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $email->setType("HotelConfirmationDe");
        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//*[contains(normalize-space(.),'bestwestern.de')]")->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'reservierung@bestwestern.de') !== false
            || isset($headers['subject']) && stripos($headers['subject'], 'Best Western') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'reservierung@bestwestern.de') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->hotel();
        $confirmationNumber = $this->getNode($this->t('Buchungsnummer'));
        $r->general()->confirmation($confirmationNumber);

        $perNight = $this->http->FindSingleNode("//span[{$this->contains($this->t('Erwachsene'))}]/ancestor::td[1]/following-sibling::td[1]/p/span[3]");

        if (empty($perNight)) {
            $perNight = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Erwachsene'))}]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space()][last()]");
        }

        if (preg_match("#\:?\s*(\S{1,3})\s*([\d,\.]+)#u", $perNight, $math)) {
            $r->price()->currency($this->normalizeCurrency($math[1]));
            $r->addRoom()->setRate($math[2] . ' / ' . $this->t('Nacht'));
        }

        if (empty($perNight)) {
            $perNight = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Erwachsene'))}]/ancestor-or-self::td[1]/following-sibling::td[1]");

            if (preg_match("#([A-Z]{3}|.)\s([\d,]+\s*\/\s*Nacht)#u", $perNight, $math)) {
                $this->currency = $math[1];
                $r->price()->currency($math[1]);
                $r->addRoom()->setRate($math[2]);
            }
        }

        if (empty($perNight) || preg_match("#^\/\s*night#", $perNight)) {
            $perNight = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Room price:')]/ancestor::tr[1]/descendant::td[2]", null, true, "/^([\d\.\,]+\s*\D{1,3}\s*\/\s*night)$/");

            if (!empty($perNight)) {
                $r->addRoom()->setRate($perNight);
            }
        }

        $inOut = explode(' / ', $this->getNode($this->t('Anreise/Abreise')));

        if (preg_match("#\w+, (.+)#", array_shift($inOut), $mathec) && preg_match("#\w+, (.+)#", array_shift($inOut),
                $v)) {
            $v[1] = preg_replace("/\s+{$this->t('add hotel stay to calendar')}/", "", $v[1]);

            $r->booked()->checkIn(strtotime($mathec[1]));
            $r->booked()->checkOut(strtotime($v[1]));
        }

        $total = $this->http->FindSingleNode("//span[{$this->contains($this->t('Inklusive Steuern & Gebühren'))}]/ancestor::td[1]/following-sibling::td[1]/p/strong");

        if (empty($total) || strlen($total) == 1) {
            $total = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Gesamtsumme'))}]/ancestor-or-self::td[1]");
        }

        if (!empty($total)) {
            if (preg_match("/^{$this->opt($this->t('Gesamtsumme'))}\s*(?<points>[\d\.]+\s*Points)[\s+]+(?<currency>\D)\s*(?<total>[\d\.\,]+)$/u", $total, $m)
                || preg_match("/^(?:{$this->opt($this->t('Gesamtsumme'))}\:?)?\s*(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)/", $total, $m)
                || preg_match("/(?:{$this->opt($this->t('Gesamtsumme'))}\:?)?\s*(?<total>[\d\.\,]+)\s*(?<currency>\D{1,3})/", $total, $m)
            ) {
                $currency = $this->normalizeCurrency($m['currency']);

                $r->price()
                    ->currency($currency)
                    ->total(PriceHelper::parse($m['total'], $currency));

                if (isset($m['points']) && !empty($m['points'])) {
                    $r->price()
                        ->spentAwards($m['points']);
                }
            } else {
                $r->price()->total(PriceHelper::parse($total, $this->currency));
            }
        }

        $guestNames = $this->http->FindSingleNode("//span[{$this->contains($this->t('Ihre persönlichen Kundendaten'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[1]/p/span/text()[not(contains(normalize-space(), 'Herr'))][1]");

        if (empty($guestNames)) {
            $guestNames = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Ihre persönlichen Kundendaten'))}]/ancestor::tr[1]/following-sibling::tr[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Herr'))][1]");
        }

        if (empty($guestNames)) {
            $guestNames = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'nlichen Kundendaten')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]/text()[not(contains(normalize-space(), 'Herr'))][1]");
        }

        if (!empty($guestNames)) {
            if (is_array($guestNames)) {
                $r->general()->travellers($guestNames);
            } else {
                $r->general()->traveller($guestNames);
            }
        }

        $phoneFaxNumber = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Tel:')]");

        if (preg_match("#Tel: (.+), Fax: (.+)#", $phoneFaxNumber, $m)) {
            $r->hotel()->phone($m[1]);
            $r->hotel()->fax($m[2]);
        }

        $hotelName = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Tel:')]/strong");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(.), 'Hoteladresse:')]/preceding::h3[1])[1]");
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//img[contains(@src, 'star')]/preceding::text()[normalize-space()][1]");
        }
        $r->hotel()->name($hotelName);

        $address = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Tel:')]/preceding-sibling::text()[1]");

        if (empty($address) && !empty($hotelName)) {
            $addressBlock = implode("\n", $this->http->FindNodes("//text()[contains(normalize-space(.), 'Tel:')]/preceding::text()[{$this->eq($hotelName)}][1]/ancestor::p[1]/descendant::text()[normalize-space()]"));

            if (preg_match("/^{$hotelName}\n(?<address>(?:.+\n){1,2})Operating company:/", $addressBlock, $m)) {
                $address = str_replace("\n", ", ", $m['address']);
            }
        }
        $r->hotel()->address($address);

        $rooms = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Zimmer:'))}]", null,
            "#(\d)\s+{$this->opt($this->t('Zimmer:'))}#u"));

        if (count($rooms) === 0) {
            $rooms = array_filter($this->http->FindNodes("//text()[starts-with(translate(normalize-space(), '0123456789', 'dddddddddd'), 'd Room')]", null,
                "#(\d)\s+{$this->opt($this->t('Room'))}#u"));
        }

        $r->booked()->rooms(count($rooms));

        $roomTypeDescription = implode("; ",
            $this->http->FindNodes("//text()[{$this->contains($this->t('Zimmer:'))}]/ancestor::tr[1]/following::tr[1]"));

        if (empty($roomTypeDescription)) {
            $roomTypeDescription = implode("; ",
                $this->http->FindNodes("//text()[starts-with(translate(normalize-space(), '0123456789', 'dddddddddd'), 'd Room')]/following::text()[normalize-space()][1]"));
        }

        $r->addRoom()->setDescription($roomTypeDescription);

        $deadline = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Stornierbar bis:'))}]/ancestor-or-self::td[1]/following-sibling::td[1]");
        $r->general()
            ->cancellation($deadline);

        if (!empty($deadline)) {
            if (preg_match("/(\d{1,2}:\d{1,2}).+?,\s(\d)\sTag[e]? im Voraus/", $deadline, $m)) {
                $r->parseDeadlineRelative($m[2] . " day", $m[1]);
            } elseif (preg_match("/^([\d\:]+) {$this->opt($this->t('Uhr am Anreisetag'))}/u", $deadline, $m)) {
                $r->booked()
                    ->deadlineRelative('0 day', $m[1]);
            } elseif (preg_match("/^([\d\:]+)\s*\,\s*(\d+)\s*Days? in advance/u", $deadline, $m)) {
                $r->booked()
                    ->deadlineRelative($m[2] . ' day', $m[1]);
            }
        }

        return $email;
    }

    private function getNode($str)
    {
        return $this->http->FindSingleNode("//*[contains(text(), '{$str}')]/ancestor-or-self::td[1]/following-sibling::td[1]");
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'NOK' => ['kr'],
            'AUD' => ['A$'],
            'USD' => ['US$'],
            'ZAR' => ['R'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function assignLang()
    {
        foreach ($this->LangDetect as $lang => $words) {
            foreach ($words as $word) {
                if (strpos($this->http->Response["body"], $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
}
