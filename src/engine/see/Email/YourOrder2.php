<?php

namespace AwardWallet\Engine\see\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourOrder2 extends \TAccountChecker
{
    public $mailFiles = "see/it-341451038.eml";

    public $lang = '';

    public static $dictionary = [
        'fr' => [
            'orderDetails' => ['Détails de votre commande'],
            'confNumber'   => ['Référence'],
            'dear'         => 'Cher / Chère',
            'seats'        => ['Places:', 'Places :'],
            'Address:'     => 'Address:',
        ],

        'en' => [
            'orderDetails' => ['Reference Number:'],
            'confNumber'   => ['Reference Number:'],
            'Address:'     => 'Address:',
        ],
    ];

    private $subjects = [
        'fr' => ['Confirmation de votre commande'],
        'en' => [' - E-Ticket '],
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
            && $this->http->XPath->query('//a[contains(@href,".seetickets.com/") or contains(@href,"www.seetickets.com") or contains(@href,".seetickets.fr/") or contains(@href,"faq.seetickets.fr")]')->length === 0
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
        $email->setType('YourOrder2' . ucfirst($this->lang));

        $patterns = [
            // 4:19PM    |    4:19P    |    18.45    |    2:00 p. m.    |    3pm    |    20h
            'time' => '\d{1,2}(?:[.:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]?\.?|[ ]*[Hh])?',
            // Mr. Hao-Li Huang
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        $ev = $email->add()->event();
        $ev->type()->event();

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('dear'))}]", null, "/^{$this->opt($this->t('dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }

        if (!empty($traveller)) {
            $ev->general()->traveller($traveller);
        }

        $detailsHeader = $this->http->FindSingleNode("//text()[{$this->starts($this->t('orderDetails'))}]");

        if (preg_match("/({$this->opt($this->t('confNumber'))})[:\s]+([-A-Z\d]{5,})(?:\s+-\s+|$)/", $detailsHeader, $m)) {
            $ev->general()->confirmation($m[2], $m[1]);
        }

        $patterns['date'] = '(?:'
            . '[[:alpha:]]+[,. ]+\d{1,2}[,. ]+[[:alpha:]]+[,. ]+\d{2,4}' // sam. 07 janv. 2023
        . ')';

        if (preg_match("/{$this->opt($this->t('- Date'))}[:\s]+({$patterns['date']})$/", $detailsHeader, $m)) {
            $ev->general()->date2($this->normalizeDate($m[1]));
        }

        $eventName = $address = $dateStart = $timeStart = null;

        $orderDetailsRows = $this->http->FindNodes("//text()[{$this->starts($this->t('orderDetails'))}]/following::text()[normalize-space()][1]/ancestor::*[count(descendant::text()[normalize-space()])=3][1]/descendant::text()[normalize-space()]");

        $dateTime = null;

        if (count($orderDetailsRows) === 3) {
            $eventName = $orderDetailsRows[0];
            $address = $orderDetailsRows[1];
            $dateTime = $orderDetailsRows[2];
        }

        $addressDetails = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Address:')]", null, true, "/{$this->opt($this->t('Address:'))}\s*(.+)/");

        if (!empty($addressDetails)) {
            $address = $addressDetails;
        }

        $ev->place()->name($eventName)->address($address);

        if (stripos($dateTime, 'Date:') !== false) {
            $dateTime = str_replace('Date:', '', $dateTime);
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Time:')]")->length > 0) {
            $time = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Time:')]", null, true, "/{$this->opt($this->t('Time:'))}\s*(\d+(?:\.|\:)\d+)/");
            $dateTime = str_replace(['.', 'nd', 'th'], [':', ''], $dateTime . ' ' . $time);
        }

        if (preg_match("/^(?<date>{$patterns['date']})(?:(?:\s+{$this->opt($this->t('à'))}\s+|[-–, ]+)(?<time>{$patterns['time']}))?$/iu", $dateTime, $m)
        || preg_match("/^\s*(?<date>\d+\s*\w+\s*\d{4})\s*(?<time>\d+\:\d+)$/iu", $dateTime, $m)) {
            $dateStart = $m['date'];
            $timeStart = $m['time'];
        }

        if ($dateStart && $timeStart) {
            $ev->booked()->start(strtotime($timeStart, strtotime($this->normalizeDate($dateStart))))->noEnd();
        } elseif ($dateStart) {
            $ev->booked()->start2($this->normalizeDate($dateStart))->noEnd();
        }

        $seatsText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('orderDetails'))}]/following::text()[{$this->starts($this->t('seats'))}]", null, true, "/^{$this->opt($this->t('seats'))}[:\s]*(.*\d.*)$/");

        if ($seatsText) {
            $ev->booked()->seats(preg_split('/(\s*,)+/', $seatsText));
        }

        $guests = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Quantity:')]", null, true, "/{$this->opt($this->t('Quantity:'))}\s*(\d+)/");

        if (!empty($guests)) {
            $ev->setGuestCount($guests);
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/*[normalize-space()][2]", null, true, '/^(.*?\d.*?)(?:\s*\(|$)/');

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)
            || preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)
        ) {
            // 163,45€    |    €163,45
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $ev->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $preRows = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/preceding-sibling::*[normalize-space()]");

            foreach ($preRows as $i => $priceRow) {
                $priceName = $this->http->FindSingleNode("*[normalize-space()][1]", $priceRow);
                $priceCharge = $this->http->FindSingleNode("*[normalize-space()][2]", $priceRow, true, '/^(.*?\d.*?)(?:\s*\(|$)/');

                if ($i === 0
                    && (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $priceCharge, $m)
                        || preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $priceCharge, $m)
                    )
                ) {
                    $ev->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));

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
            if (!is_string($lang) || empty($phrases['orderDetails']) || empty($phrases['confNumber']) || empty($phrases['Address:'])) {
                continue;
            }

            if (($this->http->XPath->query("//*[{$this->contains($phrases['orderDetails'])}]")->length > 0
                || $this->http->XPath->query("//*[{$this->contains($phrases['Address:'])}]")->length > 0)
                && $this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
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
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(?:[-[:alpha:]]{2,}[,.\s]+)?(\d{1,2})[,.\s]+([[:alpha:]]{3,})[,.\s]+(\d{4})$/u', $text, $m)) {
            // dim., 17 sept. 2023    |    17 sept. 2023
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
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
}
