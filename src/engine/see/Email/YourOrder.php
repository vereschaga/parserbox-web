<?php

namespace AwardWallet\Engine\see\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourOrder extends \TAccountChecker
{
    public $mailFiles = "see/it-337901989.eml, see/it-327004703-junk.eml";

    public $lang = '';

    public static $dictionary = [
        'fr' => [
            'hello'        => 'Bonjour',
            'confNumber'   => ['Référence de commande:', 'Référence de commande :'],
            'summaryOrder' => ['Résumé de la commande'],
        ],
    ];

    private $subjects = [
        'fr' => ['Confirmation de votre commande'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@seetickets.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".seetickets.com/") or contains(@href,"fr.seetickets.com") or contains(@href,"www.seetickets.es")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourOrder' . ucfirst($this->lang));

        $patterns = [
            // 4:19PM    |    4:19P    |    18.45    |    2:00 p. m.    |    3pm    |    20h
            'time' => '\d{1,2}(?:[.:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]?\.?|[ ]*[Hh])?',
            // Mr. Hao-Li Huang
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        $ev = $email->add()->event();
        $ev->type()->event();

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('hello'))}]", null, "/^{$this->opt($this->t('hello'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }
        $ev->general()->traveller($traveller);

        $confirmation = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('confNumber'))}]");

        if (preg_match("/({$this->opt($this->t('confNumber'))})[:\s]*([-A-Z\d]{5,})(?:\s*\(|$)/", $confirmation, $m)) {
            $ev->general()->confirmation($m[2], trim($m[1], ': '));
        }

        $patterns['date'] = '(?:'
            . '[[:alpha:]]+[,. ]+\d{1,2}[,. ]+[[:alpha:]]+[,. ]+\d{2,4}' // dim., 17 sept. 2023    |    Jeudi, 23 Juin 2022
            . '|[[:alpha:]]+[,. ]+\d{1,2}[,. ]+[[:alpha:]]+ ?\.?' // ven., 15 sept.
            . '|\d{1,2}\/\d{1,2}\/\d{2,4}' // 15/09/2023
            . '|[[:alpha:]]+[,. ]+\d{1,2}' // Vendredi 01
        . ')';

        $eventName = $address = $dateStart = $dateEnd = $timeStart = $timeEnd = null;

        $summaryOrderRows = $this->http->FindNodes("//tr[{$this->eq($this->t('summaryOrder'))}]/following::tr[not(.//tr) and normalize-space()][1]/descendant::text()[normalize-space()]");
        $summaryOrderText = implode("\n", $summaryOrderRows);

        if (mb_stripos($summaryOrderText, "Cette commande ne contient pas vos billets d'entrée à l'événement") !== false
            && mb_stripos($summaryOrderText, "Les Billets seront émis une fois que tous les versements ont été payés") !== false
        ) {
            $email->setIsJunk(true);
            $email->removeItinerary($ev);

            return $email;
        }

        $dates = null;

        if (count($summaryOrderRows) === 3) {
            $eventName = $summaryOrderRows[0];
            $dates = $summaryOrderRows[1];
            $address = $summaryOrderRows[2];
        }

        $ev->place()->name($eventName)->address($address);

        $this->logger->debug($dates);
        $this->logger->debug("/^{$this->opt($this->t('Du'))}\s+(?<date1>{$patterns['date']})(?:(?:\s+{$this->opt($this->t('à'))}\s+|[-–, ]+)(?<time1>{$patterns['time']}))?\s+{$this->opt($this->t('au'))}\s+(?<date2>{$patterns['date']})(?:(?:\s+{$this->opt($this->t('à'))}\s+|[-–, ]+)(?<time2>{$patterns['time']}))?$/iu");

        if (preg_match("/^{$this->opt($this->t('Du'))}\s+(?<date1>{$patterns['date']})(?:(?:\s+{$this->opt($this->t('à'))}\s+|[-–, ]+)(?<time1>{$patterns['time']}))?\s+{$this->opt($this->t('au'))}\s+(?<date2>{$patterns['date']})(?:(?:\s+{$this->opt($this->t('à'))}\s+|[-–, ]+)(?<time2>{$patterns['time']}))?$/iu", $dates, $m)
            || preg_match("/^(?<date1>{$patterns['date']})(?:(?:\s+{$this->opt($this->t('à'))}\s+|[-–, ]+)(?<time1>{$patterns['time']}))?\s+[-–]+\s+(?<date2>{$patterns['date']})(?:(?:\s+{$this->opt($this->t('à'))}\s+|[-–, ]+)(?<time2>{$patterns['time']}))?$/iu", $dates, $m)
        ) {
            // Du Vendredi 01 au Dimanche 03 Septembre 2023    |    ven., 15 sept. à 12h - dim., 17 sept. 2023 à 20h    |    15/09/2023 08:30 - 17/09/2023 06:30
            $dateStart = $m['date1'];
            $dateEnd = $m['date2'];

            if (preg_match("/^[[:alpha:]]+[,.\s]+\d{1,2}$/iu", $dateStart) // Vendredi 01
                && preg_match("/^{$this->opt($this->t('Du'))}\s+{$this->opt($dateStart)}\s+{$this->opt($this->t('au'))}\s+/iu", $dates) // Du Vendredi 01 au
                && preg_match("/^[[:alpha:]]+[,.\s]+\d{1,2}([,.\s]+[[:alpha:]]+[,.\s]+\d{2,4})$/iu", $dateEnd, $m2) // Dimanche 03 Septembre 2023
            ) {
                // it-337901989.eml
                $dateStart .= $m2[1];
            }

            if (!empty($m['time1'])) {
                $timeStart = $m['time1'];
            }

            if (!empty($m['time2'])) {
                $timeEnd = $m['time2'];
            }

            if (!$timeEnd) {
                $timeEnd = '23:59';
            }
        } elseif (preg_match("/^(?<date>{$patterns['date']})(?:(?:\s+{$this->opt($this->t('à'))}\s+|[-–, ]+)(?<time>{$patterns['time']}))?$/u", $dates, $m)) {
            // Samedi, 2 Sept. 2023 à 15.30
            $dateStart = $m['date'];

            if (!empty($m['time'])) {
                $timeStart = $m['time'];
            }
        }

        $year = preg_match("/\b(2[01]\d{2})\b/", $dates, $m) ? $m[1] : null;

        if ($dateStart && $timeStart) {
            $ev->booked()->start(strtotime($this->normalizeTime($timeStart), $this->preprocessingDate($dateStart, $year)));
        } elseif ($dateStart) {
            $ev->booked()->start($this->preprocessingDate($dateStart, $year));
        }

        if ($dateEnd && $timeEnd) {
            $bookedEnd = strtotime($this->normalizeTime($timeEnd), $this->preprocessingDate($dateEnd, $year));
            $ev->booked()->end($ev->getStartDate() && $bookedEnd && $ev->getStartDate() > $bookedEnd ? strtotime('+1 days', $bookedEnd) : $bookedEnd);
        }

        if (empty($dateEnd) && empty($timeEnd)) {
            $ev->booked()->noEnd();
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/*[normalize-space()][2]", null, true, '/^(.*?\d.*?)(?:\s*\(|$)/');

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)
            || preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)
        ) {
            // 45.00€    |    €45.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $ev->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $preRows = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/preceding-sibling::*[normalize-space()]");

            foreach ($preRows as $i => $priceRow) {
                $priceName = $this->http->FindSingleNode("*[normalize-space()][1]", $priceRow);
                $priceCharge = $this->http->FindSingleNode("*[normalize-space()][2]", $priceRow, true, '/^(.*?\d.*?)(?:\s*\(|$)/');

                if ($i === 0 && preg_match("/^(\d{1,3})\s*[Xx]\s*\S.+/", $priceName, $multipliers)
                    && (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $priceCharge, $m)
                        || preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $priceCharge, $m)
                    )
                ) {
                    $ev->price()->cost(PriceHelper::parse($m['amount'], $currencyCode) * $multipliers[1]);

                    continue;
                }

                if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $priceCharge, $m)
                    || preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $priceCharge, $m)
                ) {
                    $ev->price()->fee($priceName, PriceHelper::parse($m['amount'], $currencyCode));
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['summaryOrder'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['summaryOrder'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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

    /**
     * Dependencies `$this->normalizeDate()`.
     *
     * @param string|null $d Unformatted string with date
     * @param string|null $y String with year
     *
     * @return int|false
     */
    private function preprocessingDate(?string $d, ?string $y = null)
    {
        if (preg_match("/^(?<wday>[-[:alpha:]]+)[,.\s]+(?<date>[[:alpha:]]+[,.\s]+\d{1,2}|\d{1,2}[,.\s]+[[:alpha:]]+) ?\.?$/u", $d, $m)) {
            // ven., 15 sept.
            $dateNormal = $this->normalizeDate($m['date']);
            $weekDateNumber = WeekTranslate::number1($m['wday']);

            return EmailDateHelper::parseDateUsingWeekDay($dateNormal . ' ' . $y, $weekDateNumber);
        }

        return strtotime($this->normalizeDate($d));
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $text, $m)) {
            // 17/09/2023
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/', $text, $m)) {
            // 17/09/23
            $day = $m[1];
            $month = $m[2];
            $year = '20' . $m[3];
        } elseif (preg_match('/^(?:[-[:alpha:]]{2,}[,.\s]+)?(\d{1,2})[,.\s]+([[:alpha:]]{3,})[,.\s]+(\d{4})$/u', $text, $m)) {
            // dim., 17 sept. 2023    |    17 sept. 2023
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(\d{1,2})[,.\s]+([[:alpha:]]{3,}) ?\.?$/u', $text, $m)) {
            // 17 sept.
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

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace('/(\d)[ ]*\.[ ]*(\d)/', '$1:$2', $s); // 01.55 PM    ->    01:55 PM
        $s = preg_replace('/^(\d{1,2})\s*[Hh]$/', '$1:00', $s); // 20h    ->    20:00

        return $s;
    }
}
