<?php

namespace AwardWallet\Engine\hdfc\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightBooking extends \TAccountChecker
{
    public $mailFiles = "hdfc/it-26624707.eml, hdfc/it-26884291.eml, hdfc/it-27289093.eml";

    private $detectFrom = '@smartbuyoffers.';

    private $detectSubject = [
        'Flight Booking', //en
    ];

    private $detectCompany = 'HDFC Bank SmartBuy ';

    private $detectBody = [
        'en' => ['Itinerary Details'],
    ];
    private $date;
    private $lang = 'en';
    private static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        //		foreach ($this->detectBody as $lang => $detectBody) {
        //			foreach ($detectBody as $dBody) {
        //				if (strpos($body, $dBody) !== false) {
        //					$this->lang = $lang;
        //					break;
        //				}
        //			}
        //		}

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $this->lang);

        $this->date = strtotime($parser->getHeader('date'));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (stripos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return ['cleartrip', 'goibibo', 'yatra'];
    }

    private function parseEmail(Email $email)
    {
        // Provider
        $provider = trim($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'This ticket is booked through our partner')]", null, true, "#This ticket is booked through our partner (.+?)\.#"));
        $companies = [
            'Clear Trip' => 'cleartrip',
            'Goibibo'    => 'goibibo',
            'Yatra'      => 'yatra',
        ];

        if (!empty($provider)) {
            if (isset($companies[$provider])) {
                $email->setProviderCode($companies[$provider]);
            } else {
                $email->setProviderKeyword($provider);
            }
        }

        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->starts("HDFC Bank SmartBuy Ref No") . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "#^\s*(\d+)\s*$#"))
        ;

        $f = $email->add()->flight();

        // General
        $conf = $this->http->FindSingleNode("//text()[" . $this->starts("HDFC Bank SmartBuy Ref No") . "]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1][td[1][" . $this->contains("Ref Number") . "]]/td[2]", null, true, "#^\s*([\w]{5,})\s*$#");

        if (!empty($conf)) {
            $f->general()->confirmation($conf);
        }

        if (empty($conf) && !empty($this->http->FindSingleNode("//text()[" . $this->starts("HDFC Bank SmartBuy Ref No") . "]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1][" . $this->starts("Booked on") . "]"))) {
            $f->general()->noConfirmation();
        }

        if (empty($conf) && !empty($this->http->FindSingleNode("//text()[" . $this->starts("HDFC Bank SmartBuy Ref No") . "]/ancestor::tr[1]/following-sibling::tr[" . $this->contains("Ref Number") . "]//text()[" . $this->contains("Ref Number") . "]/following::text()[normalize-space()][" . $this->starts("Booked on") . "]"))) {
            $f->general()->noConfirmation();
        }

        $date = $this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts("Booked on ") . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]"));

        if (!empty($date)) {
            $this->date = $date;
            $f->general()->date($date);
        }

        $f->general()
            ->status($this->http->FindSingleNode("//text()[" . $this->starts("Status") . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]"))
            ->travellers($this->http->FindNodes("//text()[normalize-space() = 'Passenger Details'][1]/following::td[normalize-space()='Name'][1]/ancestor::tr[1]/following-sibling::tr/td[normalize-space()][1]"), true)
        ;

        if ($f->getStatus() == 'Failed') {
            $f->general()->cancelled(true);
        }

        // Segments
        $xpath = "//text()[normalize-space() = 'Depart'][1]/ancestor::tr[1]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $s = $f->addSegment();

            // Airline
            $info = implode("\n", $this->http->FindNodes("./preceding-sibling::tr[1]/td[1]//text()[normalize-space()]", $root));

            if (!empty($info) && preg_match("#(?:^|\s+)(?<al>[A-Z\d]{2})\s*-\s*(?<fn>\d{1,5})\s*\n\s*.+?\s*:\s*.+\s+PNR\s*:\s*(?<pnr>[A-Z\d]{5,7})?(\s+|\s*$)#", $info, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;

                if (!empty($m['pnr'])) {
                    $s->airline()
                        ->confirmation($m['pnr']);
                }
            }

            $regexp = "#(?<name>.+)\(\s*(?<code>[A-Z]{3})\s*\)(?:\s*-(?<name2>.+))?\s+(?<date>.+)#";
            // Departure
            $dep = implode("\n", $this->http->FindNodes("./following-sibling::tr[1]/td[normalize-space()][1]//text()[normalize-space()]", $root));

            if (!empty($dep) && preg_match($regexp, $dep, $m)) {
                $s->departure()
                    ->name(trim($m['name']) . ((!empty($m['name2'])) ? ', ' . $m['name2'] : ''))
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']))
                ;
            }
            // Arrival
            $arr = implode("\n", $this->http->FindNodes("./following-sibling::tr[1]/td[normalize-space()][2]//text()[normalize-space()]", $root));

            if (!empty($dep) && preg_match($regexp, $arr, $m)) {
                $s->arrival()
                    ->name(trim($m['name']) . ((!empty($m['name2'])) ? ', ' . $m['name2'] : ''))
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']))
                ;
            }
        }

        // Price
        $total = $this->http->FindSingleNode('//td[' . $this->eq(['Netpay Amount', 'Net Paid Amount']) . ']/following-sibling::td[normalize-space(.)][1]');

        if (empty($total)) {
            $total = $this->http->FindSingleNode('//td[' . $this->eq($this->t('Total Amount')) . ']/following-sibling::td[normalize-space(.)][1]');
        }

        if ($total && preg_match('/^\s*(?<currency>[^\d\s]+)\s*(?<amount>\d[,.\d ]*)\s*$/', $total, $m)
                || preg_match('/^\s*(?<amount>\d[,.\d\s]*)\s*(?<currency>[^\d\s]+)\s*$/', $total, $m)) {
            $f->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($this->normalizeCurrency($m['currency']))
            ;
        }

        return $email;
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function normalizeAmount(string $string): ?string
    {
        if (is_numeric($string)) {
            return (float) $string;
        }

        return null;
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$'],
            'INR' => ['Rs.'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function normalizeDate($instr)
    {
        $year = date('Y', $this->date);
        $in = [
            "#^\s*(\d{2})-(\d{2})-(\d{2})\s*$#", // 23-11-15
            "#^\s*[^\d\s]+\s*(\d{1,2})\s*([^\d\s]+)\s*(\d{4})\s+(\d+:\d+(\s*[ap]m)?)\s*$#i", // Mon 26 Sep 2016 11:30
            "#^\s*([^\d\s]+)\s*(\d{1,2})\s*([^\d\s]+)\s*at\s*(\d+:\d+(\s*[ap]m)?)\s*$#i", // Sun 07 Aug at 11:05 PM
        ];
        $out = [
            "$1.$2.20$3",
            "$1 $2 $3 $4",
            "$1, $2 $3 $4",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#^(?<week>[^\d\s]+),\s+(?<date>.+?)(?<time>\s+\d+:\d+.*)\s*$#", $str, $m)) {
            $week = WeekTranslate::number1($m['week'], $this->lang);

            if (empty($week)) {
                return false;
            }
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'] . ' ' . $year, $week);
            $str = strtotime($m['time'], $str);
        } else {
            $str = strtotime($str);
        }

        return $str;
    }
}
