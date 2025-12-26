<?php

namespace AwardWallet\Engine\justfly\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "justfly/it-629225524.eml, justfly/it-630146577.eml, justfly/it-631493798.eml, justfly/it-632059423.eml, justfly/it-632452425.eml";

    public static $providers = [
        'justfly' => [
            'from'      => ['noreply@justfly.com'],
            'bodyXPath' => [
                "//img[contains(@src,'.justfly.com/images/')] | //a[contains(@href,'.justfly.com/')]",
            ],
            'subjectKeyword' => 'justfly.com',
        ],
        'flighthub' => [
            'from'      => ['noreply@flighthub.com'],
            'bodyXPath' => [
                "//img[contains(@src,'.flighthub.com/images/')] | //a[contains(@href,'.flighthub.com/')]",
            ],
            'subjectKeyword' => 'FlightHub',
        ],
    ];

    public $detectSubject = [
        'Your justfly.com booking is confirmed!',
        'Your FlightHub booking is confirmed!',
        'You’re all set! E-ticket issued',
    ];

    public $detectBody = [
        'en' => [
            'Your Booking is Confirmed with justfly.com!',
            'You’re all set for your justfly.com booking!',
            'You’re all set for your FlightHub booking!',
            'Your Booking is Confirmed with FlightHub!',
            'Your Booking is Confirmed with justfly',
        ],
    ];
    public $date;
    public $lang;
    public static $dictionary = [
        'en' => [
            'Booking Number:' => [
                'justfly.com Booking Confirmation Number:',
                'FlightHub Booking Confirmation Number:',
                'Booking Confirmation Number:',
            ],
            // 'Booking Status:' => '',

            // 'Travellers' => '',
            // 'E-Ticket:' => '',

            'Itinerary' => 'Itinerary',
            // 'Terminal' => '',
            // 'to' => '', // Belize City (BZE Terminal 10) to San Pedro (SPR)
            // 'Airline confirmation:' => '',
            // 'Flight time' => '',
            // 'Total trip time' => '',

            // 'Requested Seats' => '',

            // 'Base Fare (' => '',
            // 'Taxes & Fees (' => '',
            // 'Total Price (' => '',
            'Grand Total (' => ['Grand Total:', 'Grand Total ('],
        ],
    ];
    public $providerCode;

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$providers as $code => $pDetect) {
            if ($this->containsText($headers['from'], $pDetect['from']) !== true
                && $this->containsText($headers['subject'], $pDetect['subjectKeyword']) !== true) {
                continue;
            }

            if ($this->containsText($headers['subject'], $this->detectSubject) === true) {
                $this->providerCode = $code;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug('can\'t determine a language (body)');

            return $email;
        }

        $this->date = strtotime($parser->getDate());

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (empty($this->providerCode)) {
            $this->providerCode = $this->getProvider($parser);
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->getProviderByBody() && $this->detectBody()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        $cnt = count(self::$dictionary);

        return $cnt;
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        foreach (self::$providers as $code => $pDetect) {
            if ($this->containsText($parser->getCleanFrom(), $pDetect['from']) === true
                || $this->containsText($parser->getSubject(), $pDetect['subjectKeyword']) === true
            ) {
                $this->providerCode = $code;

                return true;
            }

            $criteria = $pDetect['bodyXPath'];

            foreach ($criteria as $search) {
                if ($this->http->XPath->query($search)->length > 5) {
                    $this->providerCode = $code;

                    return true;
                }
            }
        }

        return false;
    }

    private function getProviderByBody()
    {
        foreach (self::$providers as $code => $pDetect) {
            $criteria = $pDetect['bodyXPath'];

            foreach ($criteria as $search) {
                if ($this->http->XPath->query($search)->length > 5) {
                    $this->providerCode = $code;

                    return true;
                }
            }
        }

        return null;
    }

    private function parseEmail(Email $email)
    {
        // Travel Agency
        $conf = $this->http->FindSingleNode("//node()[{$this->eq($this->t('Booking Number:'))}]/following::text()[normalize-space(.)!=''][1]");
        $email->ota()
            ->confirmation($conf);

        // FLIGHT
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();
        $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('E-Ticket:'))}]/preceding::tr[normalize-space()][1]",
            null, "/^\s*(.+?)\s*(?:\(.+?\))?\s*$/");

        if (empty($travellers)) {
            $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Travellers'))}]/following::text()[normalize-space()][1]/ancestor::*[not(.//text()[{$this->eq($this->t('Travellers'))}])][last()]"
                . "//tr[not(.//tr)][count(descendant::text()[normalize-space()]) = 1]//strong",
                null, "/^\s*(.+?)\s*(?:\(.+?\))?\s*$/");
        }
        $f->general()
            ->travellers($travellers, true);

        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Status:'))}]/following::text()[normalize-space(.)!=''][1]");

        if (!empty($status)) {
            $f->general()
                ->status($status);
        }

        // Issued
        $tickets = array_unique(array_filter($this->http->FindNodes("//td[{$this->eq($this->t('E-Ticket:'))}]/following-sibling::td[normalize-space()][1]/descendant-or-self::td[normalize-space()]",
            null, "/^\s*([\d\-]{10,})\s*$/")));

        if (!empty($tickets)) {
            foreach ($tickets as $ticket) {
                $pax = preg_replace("/\s+\(.+\)/", "", $this->http->FindSingleNode("//text()[{$this->eq($ticket)}]/preceding::strong[1][{$this->contains($travellers)}]"));

                if (!empty($pax)) {
                    $f->addTicketNumber($ticket, false, $pax);
                } else {
                    $f->addTicketNumber($ticket, false);
                }
            }
        }

        $xpath = "//text()[{$this->eq($this->t('Itinerary'))}]/following::img[following::text()[{$this->starts($this->t('Total trip time'))}]]/ancestor::*[normalize-space()][1]";
        $nodes = $this->http->XPath->query($xpath);
        //$this->logger->debug("[XPATH]: " . $xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $sText = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));
            //$this->logger->debug('$sText = '.print_r( $sText,true));

            // Airline
            if (preg_match("/{$this->opt($this->t('Airline confirmation:'))}\s*(?<conf>.+?)\s*\((?<al>.+?)\s+(?<fn>\d{1,5})\)/", $sText, $m)
            || preg_match("/{$this->opt($this->t('Airline confirmation:'))}?\s*(?:Pending|(?<conf>[A-Z\d]{6}))?\s*\(?.*\s(?<al>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?:\s+|\-)(?<fn>\d{1,5})\)?/", $sText, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);

                if (preg_match("/^\s*([A-Z\d]{5,7})\s*$/", $m['conf'])) {
                    $s->airline()
                        ->confirmation(trim($m['conf']));
                }
            }

            // Departue, Arrival
            $re = "/^\s*(?<dName>[^\(\)]+?)\s*\(\s*(?<dCode>[A-Z]{3})(?<dTerminal>\s+.+)?\s*\)\s+{$this->opt($this->t('to'))}\s+"
                . "(?<aName>[^\(\)]+?)\s*\(\s*(?<aCode>[A-Z]{3})(?<aTerminal>\s+.+)?\s*\)\s+"
                . "(?<dDate>\S.+?)\s+-\s+(?<aDate>\S.+?)\n/";
            // $this->logger->debug('$re = '.print_r( $re,true));
            if (preg_match($re, $sText, $m)) {
                $s->departure()
                    ->code($m['dCode'])
                    ->name($m['dName'])
                    ->date($this->normalizeDate($m['dDate']))
                    ->terminal(trim(preg_replace("/\s*\b{$this->opt($this->t('Terminal'))}\s*\b/", '', $m['dTerminal'] ?? '')), true, true)
                ;
                $s->arrival()
                    ->code($m['aCode'])
                    ->name($m['aName'])
                    ->date($this->normalizeDate($m['aDate']))
                    ->terminal(trim(preg_replace("/\s*\b{$this->opt($this->t('Terminal'))}\s*\b/", '', $m['aTerminal'] ?? '')), true, true)
                ;
                $routeName = $m['dCode'] . ' - ' . $m['aCode'];
                $seats = array_filter($this->http->FindNodes("//*[self::th or self::td][{$this->eq($routeName)}]/following-sibling::*[self::th or self::td]",
                    null, "/^\s*(\d{1,3}[A-Z])\s*$/"));

                if (!empty($seats)) {
                    foreach ($seats as $seat) {
                        $pax = $this->http->FindSingleNode("//text()[{$this->eq($seat)}]/preceding::strong[1][{$this->contains($travellers)}]");

                        if (!empty($pax)) {
                            $s->addSeat($seat, true, true, $pax);
                        } else {
                            $s->addSeat($seat);
                        }
                    }
                }
            }

            // Extra
            if (preg_match("/{$this->opt($this->t('Flight time'))}\s*-\s*(.+)/", $sText, $m)) {
                $s->extra()
                    ->duration($m[1]);
            }

            if (preg_match("/(.+)\n{$this->opt($this->t('Flight time'))}/", $sText, $m)) {
                if (preg_match("/^\s*cabin-class_([a-z]{1,2})\s*$/", $m[1], $mat)) {
                    $s->extra()->bookingCode(strtoupper($mat[1]));
                } elseif (preg_match("/^\s*cabin-class_([a-z_]{3,})\s*$/", $m[1], $mat)) {
                    $s->extra()->cabin(ucfirst(str_replace('_', ' ', $mat[1])));
                } else {
                    $s->extra()->cabin($m[1]);
                }
            }
        }

        // Price
        $currency = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Grand Total ('))}]",
            null, true, "/{$this->opt($this->t('Grand Total ('))}\s*([A-Z]{3})\s*\)/");

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Grand Total ('))}]/following::text()[normalize-space()][1]",
                null, true, "/\s*(\D{1,3})/");
        }
        $email->price()
            ->currency($currency)
            ->total(PriceHelper::parse($this->http->FindSingleNode("//td[{$this->starts($this->t('Grand Total ('))}]/following-sibling::*[normalize-space()]",
                null, true, "/^\D*(\d[\W\d]*?)\D*$/"), $currency));

        $fares = $this->http->FindNodes("//td[{$this->starts($this->t('Base Fare ('))}]/following-sibling::*[normalize-space()]",
            null, "/^\D*(\d[\W\d]*?)\D*$/");
        $taxes = $this->http->FindNodes("//td[{$this->starts($this->t('Taxes & Fees ('))}]/following-sibling::*[normalize-space()]",
            null, "/^\D*(\d[\W\d]*?)\D*$/");

        if (count(array_unique($fares)) == 1) {
            // if the values are different, it is not known how many adults and how many children
            $costValue = PriceHelper::parse($fares[0], $currency);

            if (!empty($costValue)) {
                $email->price()
                    ->cost(count($f->getTravellers()) * $costValue);
            }
        }

        if (count(array_unique($taxes)) == 1) {
            // if the values are different, it is not known how many adults and how many children

            $taxValue = PriceHelper::parse($taxes[0], $currency);

            if (!empty($taxValue)) {
                $email->price()
                    ->tax(count($f->getTravellers()) * $taxValue);
            }
        }

        $fNodes = $this->http->XPath->query("//tr[not(.//tr)][not({$this->starts($this->t('Total Price ('))})][preceding::*[{$this->starts($this->t('Total Price ('))}]][not(following::*[{$this->starts($this->t('Total Price ('))}])][following::*[{$this->starts($this->t('Grand Total ('))}]][count(*[normalize-space()]) = 2]");

        foreach ($fNodes as $fRoot) {
            $name = $this->http->FindSingleNode("*[normalize-space()][1]", $fRoot);
            $value = PriceHelper::parse($this->http->FindSingleNode("*[normalize-space()][2]", $fRoot, true, "/^\D*(\d[\W\d]*?)\D*$/"), $currency);

            $email->price()
                ->fee($name, $value);
        }

        return true;
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $year = date('Y', $this->date);
        $in = [
            // Wed, Jan 10, 05:14 PM
            // Sun., 10 Jan.    |    sex, 20 de ago    |    Sa., 4. Dez.
            '/^\s*([-[:alpha:]]+)[.\s]*[,\s]+([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1, $3 $2 ' . $year . ', $4',
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }
//        $this->logger->debug('$date preg_replace = '.print_r( $date,true));

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

            if ($weeknum === null) {
                foreach (self::$dictionary as $lang => $dict) {
                    $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $lang));

                    if ($weeknum !== null) {
                        break;
                    }
                }
            }
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function detectBody()
    {
        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//*[{$this->contains($dBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $words) {
            if (!empty($words['Itinerary'])
                && $this->http->XPath->query("//*[{$this->contains($words['Itinerary'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
