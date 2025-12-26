<?php

namespace AwardWallet\Engine\zenchef\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class EventReservation extends \TAccountChecker
{
	public $mailFiles = "zenchef/it-873759679.eml, zenchef/it-875247140.eml, zenchef/it-886038853.eml, zenchef/it-886201241.eml, zenchef/it-888356950.eml, zenchef/it-888660352.eml, zenchef/it-888665815.eml";
    public $subjects = [
        'Reservation for',
        'Reservation canceled',
        'Reservation changed',
        // nl
        'Reservering geannuleerd',
        'Reservering gewijzigd',
        'Reservering voor',
        // de
        'Reservierung für',
        'Erinnerung für'
    ];

    public $emailSubject = '';
    public $date = '';
    public $lang = '';

    public $detectLang = [
        "en" => ['Show on map'],
        "nl" => ['Toon op kaart'],
        "de" => ['Auf Karte anzeigen'],
    ];

    public static $dictionary = [
        "en" => [
            'Hi' => ['Hi', 'Dear'],
            'cancelledText' => ['has been canceled', 'Reservation canceled'],
            'is confirmed' => ['is confirmed', 'has been canceled', 'has been changed'],
        ],
        "nl" => [
            'Hi' => ['Hi', 'Beste'],
            'cancelledText' => ['is geannuleerd', 'Reservering geannuleerd'],
            'is confirmed' => ['uur is bevestigd', 'is geannuleerd'],
            'Your reservation' => ['Je reservering', 'Uw reservering'],
            'for' => 'voor',
            'on' => 'op',
            'Date' => 'Datum',
            'till' => 'tot',
            'Time' => 'Tijd',
            'Reservation ID' => 'Reserverings ID',
            'People' => 'Personen',
            'people' => 'personen',
            'Show on map' => 'Toon op kaart',
            'Total' => 'Totaal',
            'at' => 'om',
            'Reservation at' => 'Reservering op'
        ],
        "de" => [
            // 'Hi' => ['Hi', ' '],
            // 'cancelledText' => [''],
            'is confirmed' => ['wurde bestätigt'],
            'Your reservation' => ['Ihre Reservierung', 'Dies ist eine Erinnerung an Ihre Reservierung'],
            'for' => 'für',
            'on' => 'am',
            'Date' => 'Datum',
            'till' => 'bis',
            'Time' => 'Zeit',
            'Reservation ID' => 'Reservierungsnummer',
            'People' => 'Personen',
            'people' => 'personen',
            'Show on map' => 'Auf Karte anzeigen',
            'Total' => 'Totaal',
            'at' => 'um'
        ]
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.formitable.com') !== false) {
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
        $this->assignLang();

        if ($this->http->XPath->query("//a[contains(@href, 'formitable.com')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Formitable'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.formitable\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->emailSubject = $parser->getSubject();

        $this->date = EmailDateHelper::getEmailDate($this, $parser) - 86400;

        if ($this->date === null){
            $email->setIsJunk(true, 'Year is null');
        }

        $this->Event($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Event(Email $email)
    {
        $e = $email->add()->event();

        $e->type()
            ->restaurant();

        $e->general()
            ->traveller($this->http->FindSingleNode("//tr[ancestor::td][{$this->starts($this->t('Hi'))}][following-sibling::tr[{$this->starts($this->t('Your reservation'))}]]", null, true, "/{$this->opt($this->t('Hi'))}\s*(.+)\,/"));

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation ID'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-z\d]{10})$/");

        if ($confirmation !== null){
            $e->general()
                ->confirmation($confirmation);
        } else if (!$this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation ID'))}]/following::text()[normalize-space()][1]")){
            $e->general()
                ->noConfirmation();
        }

        if ($this->http->XPath->query("//node()[{$this->contains($this->t('cancelledText'))}]")->length > 0) {
            $e->general()
                ->status('Cancelled')
                ->cancelled();
        }

        $e->place()
            ->name($name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Show on map'))}]/ancestor::td[1]/descendant::text()[normalize-space()][1]"));

        if ($name !== null){
            $addressNodes = $this->http->FindNodes("//td[{$this->starts($name)} and {$this->contains($this->t('Show on map'))}][count(./p) = 2]/descendant::text()[normalize-space()][preceding::strong[{$this->eq($name)}] and following::text()[{$this->eq($this->t('Show on map'))}]]");

            if (!empty($addressNodes)){
                $e->place()
                    ->address(implode(", ", $addressNodes));
            }
        }

        $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Show on map'))}]/following::text()[normalize-space()][1]", null, false, "/^[\d\(\-\)\+ ]+$/");

        if ($phone !== null){
            $e->place()->phone($phone);
        }

        $date = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation at'))}]", null, true, "/^{$this->opt($this->t('Reservation at'))}[ ]*((?:[[:alpha:]]+)?[ ]*[0-9]{1,2}\.?[ ]*(?:[[:alpha:]]+)?[ ]*\,?[ ]*[0-9]{4})$/u");

        if ($date === null){
            $date = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Date'))}]/following::text()[normalize-space()][1]", null, true, "/^((?:[[:alpha:]]+)?[ ]*[0-9]{1,2}\.?[ ]*(?:[[:alpha:]]+)?)$/u");
        }

        $time = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Time'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+\:\d+\s*A?P?M?[ ]*{$this->opt($this->t('till'))}?[ ]*(?:\d+\:\d+\s*A?P?M?)?)$/u");

        if ($date !== null && $time !== null) {
            if (preg_match("/^(\d+\:\d+\s*A?P?M?)[ ]*{$this->opt($this->t('till'))}[ ]*(\d+\:\d+\s*A?P?M?)$/u", $time, $t)){
                $e->setStartDate($this->normalizeDate($date . ', ' . $t[1]));

                $e->setEndDate($this->normalizeDate($date . ', ' . $t[2]));
            } else {
                $e->setStartDate($this->normalizeDate($date . ', ' . $time));

                $e->setNoEndDate(true);
            }
        } else {
            $date = $this->http->FindSingleNode("//td[{$this->starts($this->t('Your reservation'))}][{$this->contains($this->t('is confirmed'))}]", null, false, "/^{$this->opt($this->t('Your reservation'))}[ ]*{$this->opt($this->t('for'))}?[ ]*.*[ ]*{$this->opt($this->t('on'))}[ ]*(.+)[ ]*{$this->opt($this->t('is confirmed'))}\./");

            if ($date !== null){
                $e->setStartDate($this->normalizeDate($date));

                $e->setNoEndDate(true);
            }
        }

        $guests = $this->http->FindSingleNode("//text()[{$this->eq($this->t('People'))}]/following::text()[normalize-space()][1]", null, false, "/^([0-9]+)x$/u");

        if ($guests !== null){
            $e->booked()->guests($guests);
        } else {
            $guests = $this->http->FindSingleNode("//td[{$this->starts($this->t('Your reservation'))}][{$this->contains($this->t('is confirmed'))}]", null, false, "/^{$this->opt($this->t('Your reservation'))}[ ]*{$this->opt($this->t('for'))}[ ]*(\d+)[ ]+{$this->opt($this->t('people'))}[ ]*{$this->opt($this->t('on'))}/");

            if ($guests !== null){
                $e->booked()->guests($guests);
            }
        }

        $totalPrice = $this->http->FindSingleNode("//td[{$this->eq($this->t('Total'))}]/following-sibling::td[normalize-space()][1]");

        if (preg_match('/^(?<currency>[^\d\)\(]+?)[ ]*(?<amount>\d[\,\.\'\d ]*)$/', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[\,\.\'\d ]*)\s*(?<currency>[^\d\)\(]+?)$/', $totalPrice, $matches)) {
            $currency = $this->normalizeCurrency($matches['currency']);

            $e->price()
                ->currency($currency)
                ->total(PriceHelper::parse($matches['amount'], $currency))
                ->cost(PriceHelper::parse($this->http->FindSingleNode("//td[{$this->eq($this->t('Total'))}]/ancestor::tr[normalize-space()][1]/preceding-sibling::tr[normalize-space()][last()]/descendant::td[normalize-space()][2]", null, false, "/^(?:[^\d\)\(]+)?\s*(\d[\,\.\'\d ]*)\s*(?:[^\d\)\(]+)?$/"), $currency));
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
                return 'normalize-space(.)="' . $s . '"';
            }, $field)) . ')';
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

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function normalizeDate(string $string)
    {
        $year = date("Y", $this->date);
        $in = [
            // October 25, 8:15
            "/([[:alpha:]]+)[ ]+([0-9]{1,2})\.?[ ]*\,[ ]*([0-9]{1,2}\:[0-9]{2})/u",
            // October 25, 2024, 8:15
            "/([[:alpha:]]+)[ ]+([0-9]{1,2})\.?[ ]*\,[ ]*([0-9]{4})[ ]*\,[ ]*([0-9]{1,2}\:[0-9]{2})/u",
            // 25 October 2025, 8:15
            "/([0-9]{1,2})\.?[ ]+([[:alpha:]]+)[ ]*([0-9]{4})[ ]*\,[ ]*([0-9]{1,2}\:[0-9]{2})/u",
            // 25 October, 8:15
            "/([0-9]{1,2})\.?[ ]+([[:alpha:]]+)[ ]*\,[ ]*([0-9]{1,2}\:[0-9]{2})/u",
            // 2 december om 18:30
            "/([0-9]{1,2})\.?[ ]+([[:alpha:]]+)[ ]*{$this->opt($this->t('at'))}[ ]*([0-9]{1,2}\:[0-9]{2})/u",
        ];

        // %year% - for date without year and without week

        $out = [
            "$2 $1 %year% $3",
            "$2 $1 $3 $4",
            "$1 $2 $3 $4",
            "$1 $2 %year% $3",
            "$1 $2 %year% $3",
        ];

        $string = preg_replace($in, $out, trim($string));

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%year%)#", $string, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $string = str_replace($m[1], $en, $string);
            }
        }

        if (!empty($this->date) && $this->date > strtotime('01.01.2000') && strpos($string, '%year%') !== false && preg_match('/^\s*(?<date>\d+ \w+) %year%(?:\s+(?<time>\d{1,2}:\d{1,2}.*))?$/', $string, $m)) {
            $string = EmailDateHelper::parseDateRelative($m['date'], $this->date);

            if (!empty($string) && !empty($m['time'])) {
                return strtotime($m['time'], $string);
            }

            return $string;
        } elseif ($year > 2000 && preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $string, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

            return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/^\d+ [[:alpha:]]+ \d{4}(\s*\d{1,2}:\d{2}(?: ?[ap]m)?)?$/ui", $string)) {
            return strtotime($string);
        } else {
            return null;
        }

        return null;
    }
}
