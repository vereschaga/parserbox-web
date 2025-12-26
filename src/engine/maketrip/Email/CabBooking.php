<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CabBooking extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-277132093.eml, maketrip/it-281609977.eml, maketrip/it-645629506-goibibo.eml";

    public static $detectProvider = [
        'goibibo' => [
            'from'   => ['noreply@goibibo.com'],
            'link'   => ['goibibo.com', 'go.ibi.bo/'],
            'imgAlt' => [], // =
            'imgSrc' => ['goibibo-logo.png'], // contains
            'text'   => ['Goibibo'],
        ],
        'maketrip' => [ // always last!
            'from'   => ['@makemytrip.com', 'MakeMyTrip'],
            'link'   => '.makemytrip.com',
            'imgAlt' => ['mmt_logo', 'MakeMyTrip'], // =
            'imgSrc' => ['.mmtcdn.com'], // contains
            'text'   => ['MakeMyTrip'],
        ],
    ];

    public $detectBody = [
        'en' => ['Your car and driver details'],
    ];

    public $date;

    public static $dictionary = [
        'en' => [
            'otaConfNumber'           => ['Booking Id:', 'Booking Id :', 'Booking ID'],
            'Your trip will start at' => ['Your trip will start at', 'Cab booked on'],
            'orSimilar'               => ['or Similar', 'or similar'],
            'BOOKING DETAILS'         => ['BOOKING DETAILS', 'Trip Details'],
            'statusPhrases'           => ['is'],
            'statusVariants'          => ['confirmed'],
        ],
    ];

    private $providerCode;
    private $lang = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        $this->assignProvider();
        $this->assignLang();

        $this->parseEmail($email);

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->assignProvider() && $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'your MakeMyTrip cab booking is confirmed to ') !== false
            || stripos($headers['subject'], 'your Goibibo cab booking is confirmed to ') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'MakeMyTrip') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function assignProvider(): bool
    {
        foreach (self::$detectProvider as $code => $providerParams) {
            if ($this->http->XPath->query("//img[" . $this->eq($providerParams['imgAlt'], '@alt') . " or " . $this->contains($providerParams['imgSrc'], '@src') . "]")->length > 0
                || $this->http->XPath->query("//a[" . $this->contains($providerParams['link'], '@href') . "]")->length > 0
            ) {
                $this->providerCode = $code;

                return true;
            }
        }

        return false;
    }

    private function parseEmail(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:\s*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'date'          => '\b[-[:alpha:]]+\s*,\s*\d{1,2}\s+[[:alpha:]]+\b', // Tue, 07 Feb
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        // Travel Agency
        $tripNum = $this->http->FindSingleNode("//text()[{$this->eq($this->t('otaConfNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,25}$/')
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('otaConfNumber'))}]", null, true, "/^{$this->opt($this->t('otaConfNumber'))}[:\s]+([-A-Z\d]{5,25})$/");
        $email->ota()
            ->confirmation($tripNum);

        $dateStr = strtotime($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your trip will start at'))}]", null, true, "/{$this->opt($this->t('Your trip will start at'))}\s*(.+?)\s*$/"));

        if (!empty($dateStr)) {
            $this->date = $dateStr;
        }

        $t = $email->add()->transfer();

        $t->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//tr/*[1][{$this->eq($this->t('Booked for'))}]/following::*[not(.//tr[normalize-space()])][1]/descendant::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u"))
        ;

        $s = $t->addSegment();

        $xpath = "//img[contains(@src, 'timer.png') or contains(@src, '/timer-icon')]/ancestor::tr[normalize-space()][1]/*";

        $departureText = implode("\n", $this->http->FindNodes($xpath . '[1]/descendant::text()[normalize-space()]'));
        $arrivalText = implode("\n", $this->http->FindNodes($xpath . '[3]/descendant::text()[normalize-space()]'));

        /*
            08:06 AM
            Tue, 07 Feb
            Terminal 2, Chhatrapati Shivaji Airport, Mumbai
        */

        if (preg_match($pattern = "/^(?<time>{$patterns['time']})\n+(?<date>{$patterns['date']})\n+(?<address>.{3,})$/", $departureText, $m)) {
            $s->departure()
                ->date(strtotime(preg_replace('/\s+/', ' ', $m['time']), $this->normalizeDate($m['date'])))
                ->address($m['address']);
        }

        if (preg_match($pattern, $arrivalText, $m)) {
            $dateArr = strtotime(preg_replace('/\s+/', ' ', $m['time']), $this->normalizeDate($m['date']));

            if ($dateArr === $s->getDepDate()) {
                $s->arrival()->noDate();
            } else {
                $s->arrival()->date($dateArr);
            }

            $s->arrival()->address($m['address']);
        }

        $xpathModel = $xpath . "/ancestor::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->contains($this->t('orSimilar'))}]";

        $s->extra()
            ->duration($this->http->FindSingleNode($xpath . "[2]/descendant::text()[normalize-space()][1]", null, true, '/^\d[\d hrsmin]*$/i'), false, true)
            ->model($this->http->FindSingleNode($xpathModel))
            ->image($this->http->FindSingleNode("//text()[{$this->eq($this->t('BOOKING DETAILS'))}]/following::td[not(.//td)][1]//img/@src[contains(.,'/Cab_Images/')]", null, false)
                ?? $this->http->FindSingleNode($xpathModel . "/preceding::node()[self::text()[normalize-space()] or self::img][1][self::img]/@src", null, false), false, true)
        ;

        if (!empty($s->getCarModel())) {
            $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($s->getCarModel())}]", null, "/\s{$this->opt($this->t('statusPhrases'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

            if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
                $status = array_shift($statusTexts);
                $t->general()->status($status);
            }
        }

        // Price
        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total Price'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/')
        ?? $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::*[ tr[normalize-space()][2] ][1]/tr[normalize-space()][1][{$this->eq($this->t('Total Price'))}] ]/*[normalize-space()][2]/descendant::*[ tr[normalize-space()][2] ][1]/tr[normalize-space()][1]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // Rs. 3025
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $email->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$str = '.print_r( $str,true));
        $year = date("Y", $this->date);

        $in = [
            // Sun, 18 Jun
            "/^([-[:alpha:]]+)\s*,\s*(\d{1,2})\s*([[:alpha:]]+)$/u",
        ];
        $out = [
            "$1, $2 $3 $year",
        ];
        $str = preg_replace($in, $out, $str);

        // if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
        //     if ($en = MonthTranslate::translate($m[2], $this->lang)) {
        //         $str = $m[1] . $en . $m[3];
        //     } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
        //         $str = $m[1] . $en . $m[3];
        //     }
        // }
        // $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match('/^(?<week>[-[:alpha:]]+), (?<date>\d+ [[:alpha:]]+ .+)/u', $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function assignLang(): bool
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($body, $dBody) !== false) {
                    $this->lang = $lang;

                    return true;
                }
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

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'INR' => ['Rs.'],
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

    private function eq($field, string $node = 'normalize-space()'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return $node . ' = "' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = 'normalize-space()'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(' . $node . ', "' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, string $node = 'normalize-space()'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(' . $node . ', "' . $s . '")';
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
}
