<?php

namespace AwardWallet\Engine\hopper\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parser capitalcards/VehicleDetails (in favor of hopper/BookingConfirmation)

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "hopper/it-150478474.eml, hopper/it-89286563.eml, hopper/it-163433894-nl.eml, hopper/it-531633792-fr.eml";
    public $subjects = [
        '/Bevestiging van je Hopper-boeking\s*:\s*(?-i)[-A-z\d]{5}/i', // nl
        '/Votre confirmation de réservation Hopper\s*:\s*(?-i)[-A-z\d]{5}/i', // fr
        '/Your Hopper booking confirmation\s*:\s*(?-i)[-A-z\d]{5}/i', // en
    ];

    public $lang = '';
    public $year = '';

    public static $dictionary = [
        'nl' => [
            'Pick-up'             => ['Ophalen'],
            'Driver Details'      => ['Gegevens bestuurder'],
            'statusVariants'      => ['Bevestigd'],
            'Confirmed'           => 'Bevestigd',
            'Drop-off'            => 'Inleveren',
            // 'in'                  => '',
            'Age'                 => 'Leeftijd',
            'carType'             => ['SuppliersChoice'],
            'or Similar'          => 'of soortgelijk',
            'Cancellation Policy' => 'Annuleringsbeleid',
            'Trip Total'          => 'Totaalprijs van de reis',
            'Base Fare'           => 'Basistarief',
        ],
        'fr' => [
            'Pick-up'             => ['Prise en charge'],
            'Driver Details'      => ['Détails du conducteur'],
            'statusVariants'      => ['Confirmé'],
            'Confirmed'           => 'Confirmé',
            'Drop-off'            => 'Retour',
            'in'                  => 'à',
            'Age'                 => 'Âge',
            'carType'             => ['Voiture haut de game'],
            'or Similar'          => 'ou similaire',
            'Cancellation Policy' => ['Politique d’annulation', "Politique d'annulation"],
            'Trip Total'          => 'Total voyage',
            'Base Fare'           => 'Tarif de base',
        ],
        'en' => [
            'Pick-up'        => ['Pick-up'],
            'Driver Details' => ['Driver Details'],
            'statusVariants' => ['Confirmed'],
            'carType'        => ['SUV', 'Intermediate', 'Exact Car Match'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->subjects as $subject) {
            if (preg_match($subject, $headers['subject']) > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".hopper.com/") or contains(@href,"go.hopper.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Hopper. All rights reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]hopper\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('BookingConfirmation' . ucfirst($this->lang));
        $emailDate = strtotime($parser->getDate());
        $this->year = date('Y', $emailDate ? $emailDate : null);

        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        ];

        $r = $email->add()->rental();

        $status = $this->http->FindSingleNode("//*[{$this->contains(['border-radius:11px', 'border-bottom-left-radius:11px'], 'translate(@style," ","")')} and {$this->eq($this->t('statusVariants'))}]");
        $cancellation = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Cancellation Policy'))}]/following-sibling::tr[normalize-space()][1]");

        $confirmation = $this->http->FindSingleNode("//text()[contains(normalize-space(),'managed')]/following::text()[normalize-space()][1]", null, true, $pattern = '/^[-A-z\d\s)(]{5,}$/')
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('statusVariants'))}]/following::text()[normalize-space()][1]", null, true, $pattern)
        ;

        $r->general()
            ->status($status)
            ->cancellation($cancellation)
            ->confirmation($confirmation ? str_replace(['(', ')', ' '], '', $confirmation) : null)
            ->travellers($this->http->FindNodes("//text()[{$this->contains($this->t('Driver Details'))}]/following::text()[{$this->contains($this->t('Age'))}]/preceding::text()[normalize-space()][1]"));

        $r->car()
            ->type($this->http->FindSingleNode("//text()[{$this->contains($this->t('or Similar'))}]/ancestor::tr[1]/preceding::tr[1]")
                ?? $this->http->FindSingleNode("//tr[{$this->eq($this->t('carType'))}]"))
            ->model($this->http->FindSingleNode("//text()[{$this->contains($this->t('or Similar'))}]/ancestor::tr[1]")
                ?? $this->http->FindSingleNode("//tr[{$this->eq($this->t('carType'))}]/following::tr[normalize-space()][1][not(descendant::img)]"))
        ;

        $r->pickup()->location($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick-up'))}]/ancestor::tr[1]/following::tr[1]"));
        $r->dropoff()->location($this->http->FindSingleNode("//text()[{$this->contains($this->t('Drop-off'))}]/ancestor::tr[1]/following::tr[1]"));

        // Jun 03    |    03 jun
        $patterns['date'] = "[[:alpha:]]+[. ]+\d{1,2}|\d{1,2}[. ]+[[:alpha:]]+";

        // Jun 03, 12:00 PM
        $patterns['dateTime'] = "(?<dateFull>.{3,}?)(?:[ ]*[,]+[ ]*|\s+{$this->opt($this->t('in'))}\s+)(?<time>{$patterns['time']})";

        // ven. 20 oct.
        $patterns['wdayDate'] = "(?<wday>[[:alpha:]]+)[,. ]+(?<date>{$patterns['date']})[. ]*";

        $datePickUpVal = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick-up'))}]/ancestor::tr[1]/following::tr[2]");
        $dateDropOffVal = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Drop-off'))}]/ancestor::tr[1]/following::tr[2]");

        if (preg_match("/^{$patterns['dateTime']}/", $datePickUpVal, $matches)) {
            $datePickUp = null;

            if (preg_match("/^{$patterns['wdayDate']}$/u", $matches['dateFull'], $m)) {
                $weekDateNumber = WeekTranslate::number1($m['wday'], $this->lang);
                $datePickUpNormal = $this->normalizeDate($m['date']);
                $datePickUp = EmailDateHelper::parseDateUsingWeekDay($datePickUpNormal . ' ' . $this->year, $weekDateNumber);
            } elseif (preg_match("/^(?<date>{$patterns['date']})[. ]*$/u", $matches['dateFull'], $m)) {
                $datePickUpNormal = $this->normalizeDate($m['date']);
                $datePickUp = EmailDateHelper::calculateDateRelative($datePickUpNormal, $this, $parser, '%D% %Y%');
            } elseif (preg_match('/\b\d{4}\b/', $matches['dateFull'])) {
                $datePickUp = strtotime($matches['dateFull']);
            }

            $r->pickup()->date(strtotime($matches['time'], $datePickUp));
        }

        if (preg_match("/^{$patterns['dateTime']}/", $dateDropOffVal, $matches)) {
            $dateDropOff = null;

            if (preg_match("/^{$patterns['wdayDate']}$/u", $matches['dateFull'], $m)) {
                $weekDateNumber = WeekTranslate::number1($m['wday'], $this->lang);
                $dateDropOffNormal = $this->normalizeDate($m['date']);
                $dateDropOff = EmailDateHelper::parseDateUsingWeekDay($dateDropOffNormal . ' ' . $this->year, $weekDateNumber);
            } elseif (preg_match("/^(?<date>{$patterns['date']})[. ]*$/u", $matches['dateFull'], $m)) {
                $dateDropOffNormal = $this->normalizeDate($m['date']);
                $dateDropOff = EmailDateHelper::calculateDateRelative($dateDropOffNormal, $this, $parser, '%D% %Y%');
            } elseif (preg_match('/\b\d{4}\b/', $matches['dateFull'])) {
                $dateDropOff = strtotime($matches['dateFull']);
            }

            $r->dropoff()->date(strtotime($matches['time'], $dateDropOff));
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Trip Total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/', $totalPrice, $matches)
        ) {
            // US$113.62    |    134,35 CA$
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $r->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Base Fare'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $baseFare, $m)
                || preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $baseFare, $m)
            ) {
                $r->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feeNodes = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and preceding-sibling::tr/*[normalize-space()][1][{$this->eq($this->t('Base Fare'))}] and following-sibling::tr/*[normalize-space()][1][{$this->eq($this->t('Trip Total'))}] ]");

            foreach ($feeNodes as $root) {
                $feeName = $this->http->FindSingleNode("*[normalize-space()][1]", $root);
                $feeCharge = $this->http->FindSingleNode("*[normalize-space()][2]", $root, true, '/^.*\d.*$/');

                if (preg_match('/^-\s*(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeCharge, $m)
                    || preg_match('/^-\s*(?<amount>\d[,.\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $feeCharge, $m)
                ) {
                    $r->price()->discount(PriceHelper::parse($m['amount'], $currencyCode));
                } elseif (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeCharge, $m)
                    || preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $feeCharge, $m)
                ) {
                    $r->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }
        }

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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Pick-up']) || empty($phrases['Driver Details'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Pick-up'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Driver Details'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^([[:alpha:]]+)[. ]+(\d{1,2})[. ]*$/u', $text, $m)) {
            // Sep 19
            $month = $m[1];
            $day = $m[2];
            $year = '';
        } elseif (preg_match('/^(\d{1,2})[. ]+([[:alpha:]]+)[. ]*$/u', $text, $m)) {
            // 20 oct.
            $day = $m[1];
            $month = $m[2];
            $year = '';
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs'],
            'BRL' => ['R$'],
            'USD' => ['US$'],
            'CAD' => ['CA$'],
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
