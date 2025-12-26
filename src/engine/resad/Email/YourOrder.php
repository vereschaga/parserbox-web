<?php

namespace AwardWallet\Engine\resad\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class YourOrder extends \TAccountChecker
{
    public $mailFiles = "resad/it-160697521.eml, resad/it-160158978-es.eml, resad/it-156732657-nl.eml";

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'Order'         => ['Solicitar o pedir'],
            'Name on order' => ['Nombre en el pedido'],
            // 'Total' => '',
        ],
        'nl' => [
            'Order'         => ['Bestelling'],
            'Name on order' => ['Naam op bestelling'],
            'Total'         => 'Totaal',
        ],
        'en' => [
            'Order'         => ['Order'],
            'Name on order' => ['Name on order'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@ra.co') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,"//ra.co/") or contains(@href,"//residentadvisor.page.link/")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Resident Advisor Ltd. All rights reserved")]')->length === 0
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
        }
        $email->setType('YourOrder' . ucfirst($this->lang));

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $event = $email->add()->event();

        $xpathDates = "//*[ count(*)=2 and *[1][descendant::img[contains(@src,'/calendar.')] and normalize-space()=''] and *[2][normalize-space()] ]";

        $name = $this->http->FindSingleNode($xpathDates . "/preceding::text()[normalize-space()][1]/ancestor::h2");

        $date1 = $date2 = null;
        $datesVal = $this->http->FindSingleNode($xpathDates . "/*[2][normalize-space()]");

        if (preg_match('/^(.{3,}?)\s+-\s+(.{6,})$/', $datesVal, $m)) {
            // Sat, 19 Mar - Sun, 20 Mar 2022
            $m[1] = $this->normalizeDate($m[1]);
            $date2 = strtotime($this->normalizeDate($m[2]));

            if ($m[1] && !preg_match('/\d{4}$/', $m[1])) {
                $date1 = EmailDateHelper::parseDateRelative($m[1], $date2, false, '%D% %Y%');
            } elseif ($m[1]) {
                $date1 = strtotime($m[1]);
            }
        } elseif (preg_match('/^.*\d.*$/', $datesVal)) {
            // Sun, 20 Mar 2022
            $date1 = strtotime($this->normalizeDate($datesVal));
            $date2 = $date1;
        }

        $time1 = $time2 = null;
        $timesVal = $this->http->FindSingleNode($xpathDates . "/following-sibling::tr[normalize-space()][1][ *[1][not(descendant::img) and normalize-space()=''] ]/*[2]");

        if (preg_match("/^({$patterns['time']})\s+-\s+({$patterns['time']})$/", $timesVal, $m)) {
            // 23:00 - 20:00
            $time1 = $m[1];
            $time2 = $m[2];
        }

        $event->booked()
            ->start(strtotime($time1, $date1))
            ->end(strtotime($time2, $date2))
        ;

        $location = $this->http->FindSingleNode("//*[ count(*)=2 and *[1][descendant::img[contains(@src,'/location.')] and normalize-space()=''] ]/*[2][normalize-space()]");
        $event->place()->type(Event::TYPE_EVENT)->name($name)->address($location);

        $totalPrice = $this->http->FindSingleNode("//*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/node()[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\-\d)(]+?)$/', $totalPrice, $matches)
        ) {
            // €23.60    |    45,00 €
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $email->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $order = $this->http->FindSingleNode("//*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->eq($this->t('Order'))}] ]/node()[normalize-space()][2]", null, true, '/^[-A-Z\d]{5,}$/');
        $orderTitle = $this->http->FindSingleNode("//*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->eq($this->t('Order'))}] ]/node()[normalize-space()][1]", null, true, '/^(.+?)[\s:：]*$/u');
        $event->general()->confirmation($order, $orderTitle);

        $traveller = $this->http->FindSingleNode("//*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->eq($this->t('Name on order'))}] ]/node()[normalize-space()][2]", null, true, "/^{$patterns['travellerName']}$/u");

        if ($traveller) {
            $event->general()->traveller($traveller, true);
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
            if (!is_string($lang) || empty($phrases['Order']) || empty($phrases['Name on order'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Order'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Name on order'])}]")->length > 0
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^[-[:alpha:]]{2,}[,.\s]+(\d{1,2})(?:\s+de)?\s+([[:alpha:]]{3,})[.\s]+(?:de\s+)?(\d{4})$/u', $text, $m)) {
            // zat, 30 apr. 2022
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^[-[:alpha:]]{2,}[,.\s]+(\d{1,2})(?:\s+de)?\s+([[:alpha:]]{3,})[.\s]*$/u', $text, $m)) {
            // zat, 30 apr.
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
