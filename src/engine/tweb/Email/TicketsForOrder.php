<?php

namespace AwardWallet\Engine\tweb\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TicketsForOrder extends \TAccountChecker
{
    public $mailFiles = "tweb/it-470620665.eml, tweb/it-471152418.eml, tweb/it-471258375.eml";
    public $subjects = [
        'Your TicketWeb Tickets for Order#',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";
    public $showName = [];
    public $total = 0;
    public $tax = 0;
    public $fee = 0;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && (stripos($headers['from'], '@ticketweb.ca') !== false)) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (stripos($text, 'www.ticketmaster.com') !== false
                    && stripos($text, 'This is your ticket') !== false
                    && stripos($text, 'TicketWeb is not responsible for any inconvenience') !== false
                ) {
                    return true;
                }
            }
        } else {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'TicketWeb')]")->length > 0
                && $this->http->XPath->query("//text()[contains(normalize-space(), 'ORDER SUMMARY')]")->length > 0
                && $this->http->XPath->query("//text()[contains(normalize-space(), 'Venue Directions')]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]ticketweb\.com$/', $from) > 0;
    }

    public function ParseTicket(Email $email, string $text)
    {
        $ticketsArray = array_filter(preg_split("/(This is your ticket)/", $text));

        foreach ($ticketsArray as $ticketText) {
            $confNumber = $this->re("/PRICE\s*[\d\,\.]+\s*\n\s+([A-Z\d]{8,})\n/m", $ticketText);

            $name = $this->http->FindSingleNode("//text()[normalize-space()='Event Details:']/following::text()[string-length()>5][1]");

            if (empty($name) && preg_match("/\s*(?<name1>.+)\s*FEE.+\n(?<name2>.+)\n\n\n/", $ticketText, $m)) {
                if (stripos($m['name1'], $confNumber) !== false) {
                    $m['name1'] = preg_replace("/({$confNumber})/", "", $m['name1']);
                }

                if (stripos($m['name2'], 'TAXES') !== false) {
                    $m['name2'] = preg_replace("/(TAXES\s*[\d\.\,]+)/", "", $m['name2']);
                }
                $name = trim($m['name1']) . ' ' . trim($m['name2']);
            }

            if (!empty($name)) {
                if (count($this->showName) === 0 || in_array($name, $this->showName) === false) {
                    $this->showName[] = $name;

                    $e = $email->add()->event();

                    $e->setName($name);

                    $e->type()
                        ->show();

                    $e->general()
                        ->traveller($this->re("/PURCHASED BY.+\n\s*([A-z\s\-\']+)[ ]{10,}\w+/", $ticketText))
                        ->confirmation($confNumber);

                    $seg = $this->re("/\n\n*(.+\n\s+.+\n.*Online\n*\s+(?:\w*\s*)?\d{1,2}\/\d{1,2}\/\d{1,2}\s+[\d\:]+A?P?M?\s*(?:DRS@[\d\:]+|DOOR)\s*\w+\,\s*\d+\s*\w+\s*\d{4}\n*)/", $ticketText);

                    if (empty($seg)) {
                        $seg = $this->re("/\n\n*(.+\n\s+.+\n.*Online\n*\s+(?:\w*\s*)?\w+\s*\d+\,\s*\d{4}\s+[\d\:]+A?P?M?\s*(?:DRS@[\d\:]+|DOOR)?\s*\w+\,\s*\d+\s*\w+\s*\d{4}\n*)/", $ticketText);
                    }

                    $segTable = $this->splitCols($seg, [0, 105]);

                    if (preg_match("/^(?<place>.+)\n(?<address>.+)\n+(?<dateStart>(?:\w+\s*)?\d{1,2}\/\d{1,2}\/\d{1,2}\s+[\d\:]+A?P?M?)\s*/", $segTable[0], $m)
                     || preg_match("/^(?<place>.+)\n(?<address>.+)\n+(?<dateStart>(?:\w+\s*)?\w+\s*\d+\,\s*\d{4}\s+[\d\:]+A?P?M?)\s*/", $segTable[0], $m)) {
                        $e->setAddress(trim($m['place']) . ', ' . $m['address']);

                        $e->booked()
                            ->guests(1)
                            ->start(strtotime($m['dateStart']))
                            ->noEnd();
                    }

                    if (preg_match("/(?<DateBooked>\d+\s*\w+\s*\d{4})/", $segTable[1], $m)) {
                        $e->general()
                            ->date(strtotime($m['DateBooked']));
                    }

                    $total = $this->re("/PRICE\s*([\d\.\,]+)/", $ticketText);
                    $fee = $this->re("/FEE\s*([\d\.\,]+)/", $ticketText);
                    $taxes = $this->re("/TAXES\s*([\d\.\,]+)/", $ticketText);

                    if (count($ticketsArray) === 1) {
                        if (!empty($total)) {
                            $e->price()
                                ->total($total);
                        }

                        if (!empty($fee)) {
                            $e->price()
                                ->fee('FEE', $fee);
                        }

                        if (!empty($taxes)) {
                            $e->price()
                                ->tax($taxes);
                        }
                    } else {
                        $this->total = array_sum([$this->total, $total]);
                        $this->fee = array_sum([$this->fee, $fee]);
                        $this->tax = array_sum([$this->tax, $taxes]);
                    }
                } elseif (count($this->showName) > 0 && in_array($name, $this->showName) === true) {
                    $e->booked()
                        ->guests($e->getGuestCount() + 1);
                    $e->general()
                        ->confirmation($confNumber);

                    $this->total = array_sum([$this->total, $total]);
                    $this->fee = array_sum([$this->fee, $fee]);
                    $this->tax = array_sum([$this->tax, $taxes]);
                }
            }
        }

        if (!empty($this->total)) {
            $e->price()
                ->total($this->total);
        }

        if (!empty($this->tax)) {
            $e->price()
                ->tax($this->tax);
        }

        if (!empty($this->fee)) {
            $e->price()
                ->fee('Fee', $this->fee);
        }
    }

    public function ParseTicketHTML(Email $email)
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Order Number:')]/following::text()[string-length()>4][1]"));

        $e = $email->add()->event();

        $e->type()
            ->show();

        $e->general()
            ->noConfirmation()
            ->date(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Date of Purchase:']/following::text()[string-length()>3][1]", null, true, "/^\w+\s*(\w+\s*\d+\,\s*\d{4})/")));

        $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thank you for your purchase,')]", null, true, "/{$this->opt($this->t('Thank you for your purchase,'))}\s*(.+)\!/");

        if (strlen(trim($traveller)) > 1) {
            $e->general()
                ->traveller($traveller);
        }

        $e->booked()
            ->guests($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Venue Directions')]/following::text()[string-length()>4][1]/ancestor::tr[1]", null, true, "/^\s*(\d+)\s+\w+/us"));

        $e->setAddress(implode(", ", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Venue Directions')]/preceding::text()[string-length()>4][1]/ancestor::table[2]/descendant::text()[string-length()>4]")));

        $e->setName($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'ORDER SUMMARY')]/following::b[1]"));

        $dateStart = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Time displayed is local to the venue)')]/preceding::text()[normalize-space()][2]", null, true, "/^(\w+\s*\w+\s*\d+\,?\s*\d{4})/");
        $timeStartText = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Time displayed is local to the venue)')]/preceding::text()[normalize-space()][1]"/*, null, true, "/^([\d\:]+\s*A?P?M?)(?:$|\s*\()/"*/);

        if (preg_match("/^(?<time>[\d\:]+\s*A?P?M?)(?:$|\s*\()/", $timeStartText, $m)
        || preg_match("/^(?:DRS|Doors)\s*[@]\s*(?<time>[\d\:]+\s*A?P?M)$/", $timeStartText, $m)) {
            $timeStart = $m['time'];
        }

        if (!empty($dateStart) && !empty($timeStart)) {
            $e->booked()
                ->start(strtotime($dateStart . ', ' . $timeStart))
                ->noEnd();
        }

        $priceArray = $this->http->FindNodes("//text()[normalize-space() = 'Total Payment']/ancestor::table[2]/descendant::tr");

        foreach ($priceArray as $key => $priceName) {
            $priceValue = $this->http->FindSingleNode("//text()[normalize-space() = 'Total Payment']/ancestor::table[2]/following::table[1]/descendant::table[$key]");

            if (!empty($priceName) && !empty($priceValue)) {
                if (stripos($priceName, 'Total Payment') !== false) {
                    if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)$/", $priceValue, $m)) {
                        $e->price()
                            ->total(PriceHelper::parse($m['total'], $this->normalizeCurrency($m['currency'])))
                            ->currency($this->normalizeCurrency($m['currency']));
                    }
                } elseif (stripos($priceName, 'Fee') !== false) {
                    $feeSumm = $this->re("/^\D{1,3}\s*([\d\.\,]+)/", $priceValue);
                    $e->price()
                        ->fee($priceName, PriceHelper::parse($feeSumm, $this->normalizeCurrency($m['currency'])));
                } elseif (stripos($priceName, 'Subtotal') !== false) {
                    if (preg_match("/^(?<currency>\D{1,3})\s*(?<cost>[\d\.\,]+)$/", $priceValue, $m)) {
                        $e->price()
                            ->cost(PriceHelper::parse($m['cost'], $this->normalizeCurrency($m['currency'])));
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (preg_match_all("/CONFIRMATION NUMBER.+\n\s*([A-Z\d]{8,})/", $text, $m)) {
                    $confs = array_unique($m[1]);

                    foreach ($confs as $conf) {
                        $email->ota()
                            ->confirmation($conf);
                    }
                }

                $this->ParseTicket($email, $text);
            }
        } else {
            $this->ParseTicketHTML($email);
        }

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
            return preg_quote($s);
        }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'INR' => ['Rs.'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
