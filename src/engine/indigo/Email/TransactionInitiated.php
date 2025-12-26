<?php

namespace AwardWallet\Engine\indigo\Email;

use AwardWallet\Schema\Parser\Email\Email;

class TransactionInitiated extends \TAccountChecker
{
    public $mailFiles = "indigo/it-652137447.eml, indigo/it-652192051.eml, indigo/it-653073885.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Your Booking Details' => 'Your Booking Details',
            'Transaction ID'       => 'Transaction ID',
        ],
    ];

    private $detectFrom = "donotreply@goindigo.in";
    private $detectSubject = [
        // en
        'IndiGo - Transaction Initiated',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]goindigo\.in\b/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'IndiGo') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['goindigo.in'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['IndiGo All rights reserved'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (
                !empty($dict['Your Booking Details']) && $this->http->XPath->query("//*[{$this->eq($dict['Your Booking Details'])}]")->length > 0
                && !empty($dict['Transaction ID']) && $this->http->XPath->query("//*[{$this->contains($dict['Transaction ID'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Your Booking Details"]) && $this->http->XPath->query("//*[{$this->eq($dict['Your Booking Details'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('PNR/Booking Reference'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*([A-Z\d]{5,7})\s*$/");

        if (empty($conf) && empty($this->http->FindSingleNode("(//*[{$this->contains('PNR')}][following::*[{$this->contains($this->t('Transaction ID'))}]])[1]"))) {
            $f->general()
                ->noConfirmation();
        } else {
            $f->general()
                ->confirmation($conf);
        }

        $nodes = $this->http->XPath->query("//td[not(.//td)][contains(., ' • ')]");

        foreach ($nodes as $root) {
            $text = $root->nodeValue;
            // $this->logger->debug('$text = '.print_r( $text,true));
            // OneWay  •  06 Oct 23  •  Direct  •  2 Pax
            // RoundTrip  •  2-11 Aug 23  •  IXR-BOM Direct • BOM-IXR Direct  •  2 Pax
            // RoundTrip  •  25-02 Jan 24  •  IXR-BOM Direct • BOM-IXR Direct  •  2 Pax
            // 01 Apr 24 • Direct • 4 Pax
            $re = "/^\s*(?:[[:alpha:]]+\s+• +)?(?<date>[^•]*\d[^•]*)\s+•\s+(?<route>.+)\s+•\s+\d+ Pax\s*$/";

            if (preg_match($re, $text, $m)) {
                if (preg_match("/^\s*(?:Direct|(?<stops>\d+) Stops?)\s*$/i", $m['route'], $mat)) {
                    // 1 segment
                    $s = $f->addSegment();

                    // Airline
                    $s->airline()
                        ->name('IndiGo')
                        ->noNumber();

                    // Departure
                    $s->departure()
                        ->code($this->http->FindSingleNode("preceding::tr[1]/preceding::td[1]/ancestor::*[1]/*[normalize-space()][1]",
                            $root, true, "/^\s*([A-Z]{3})\s*$/"))
                        ->noDate()
                        ->day($this->normalizeDate($m['date']));

                    // Arrival
                    $s->arrival()
                        ->code($this->http->FindSingleNode("preceding::tr[1]/preceding::td[1]/ancestor::*[1]/*[normalize-space()][2]",
                            $root, true, "/^\s*([A-Z]{3})\s*$/"))
                        ->noDate();

                    // Extra
                    if (!empty($mat['stops'])) {
                        $s->extra()
                            ->stops($mat['stops']);
                    }
                } elseif (preg_match("/^\s*(?<d1>[A-Z]{3})-(?<a1>[A-Z]{3})\s+(?:Direct|(?<stops1>\d+) Stops?)\s+•\s+(?<d2>[A-Z]{3})-(?<a2>[A-Z]{3})\s+(?:Direct|(?<stops2>\d+) Stops?)\s*$/",
                    $m['route'], $mat)) {
                    // 2 segments
                    $s1 = $f->addSegment();
                    $s2 = $f->addSegment();

                    // Airline
                    $s1->airline()
                        ->name('IndiGo')
                        ->noNumber();
                    $s2->airline()
                        ->name('IndiGo')
                        ->noNumber();

                    $date1 = $date2 = null;

                    if (preg_match("/^\s*(\d{1,2})\s*-\s*(\d{1,2})\s+([[:alpha:]]+\s+\d{2})\s*$/", $m['date'], $match)) {
                        $date1 = $this->normalizeDate($match[1] . ' ' . $match[3]);
                        $date2 = $this->normalizeDate($match[2] . ' ' . $match[3]);

                        if ($match[2] < $match[1]) {
                            $date1 = strtotime('-1 month', $date1);
                        }

                        if ($date1 > $date2) {
                            $date1 = strtotime('-1 year', $date1);
                        }
                    }
                    // Departure
                    $s1->departure()
                        ->code($mat['d1'])
                        ->noDate()
                        ->day($date1);
                    $s2->departure()
                        ->code($mat['d2'])
                        ->noDate()
                        ->day($date2);

                    // Arrival
                    $s1->arrival()
                        ->code($mat['a1'])
                        ->noDate();
                    $s2->arrival()
                        ->code($mat['a2'])
                        ->noDate();

                    // Extra
                    if (!empty($mat['stops1'])) {
                        $s1->extra()
                            ->stops($mat['stops1']);
                    }

                    if (!empty($mat['stops2'])) {
                        $s2->extra()
                            ->stops($mat['stops2']);
                    }
                }
            } else {
                $s = $f->addSegment();
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
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

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r($date, true));

        if (empty($date)) {
            return null;
        }

        $in = [
            //            // 16 Apr 24
            '/^\s*(\d{1,2})\s*([[:alpha:]]+)\s+(\d{2})\s*$/iu',
        ];
        $out = [
            '$1 $2 20$3',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));

        // $this->logger->debug('date end = ' . print_r($date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
