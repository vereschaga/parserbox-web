<?php

namespace AwardWallet\Engine\wagonlit\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NeedApproval extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-119444491.eml, wagonlit/it-614146312.eml, wagonlit/it-639529750.eml, wagonlit/it-86310166.eml";

    public $reFrom = "@contactCWT.com";
    public $reBody = [
        'en' => ['Destination', 'Distance:'],
    ];
    public $reBodyXPath = [
        'en' => "//text()[normalize-space(.)='From:']/ancestor::tr[1][contains(.,'Destination:') and contains(.,'Stops')]",
    ];
    public $reSubject = [
        'NEED PAX APPROVAL',
    ];
    public $lang = '';
    public $date = '';
    public static $dict = [
        'en' => [
            'statusVariants' => ['Confirmed'],
        ],
    ];

    public static $providerDetect = [
        'virtuoso' => ['@largaytravel.com'],
        'frosch'   => ['@frosch.com', 'www.frosch.com'],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $body = $this->http->Response['body'];
        $this->assignLang($body);

        if ($this->http->XPath->query("//text()[normalize-space(.)='Check In']/ancestor::tr[1][contains(.,'Check Out:') and contains(.,'Number of Rooms:')]")->length > 0) {
            $this->parseHotel($email);
        }

        if ($this->http->XPath->query("//text()[normalize-space(.)='From:']/ancestor::tr[1][contains(.,'Destination:') and contains(.,'Stops')]")->length > 0) {
            $this->parseFlight($email);
        }

        foreach (self::$providerDetect as $code => $pDetect) {
            if ($this->http->XPath->query("//node()[{$this->contains($pDetect)}]")
                || $this->containsText($parser->getCleanFrom(), $pDetect) === false
            ) {
                $email->setProviderCode($code);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach ($this->reBodyXPath as $re) {
            if ($this->http->XPath->query($re)->length > 0) {
                $body = $parser->getHTMLBody();

                return $this->assignLang($body);
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providerDetect);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseHotel(Email $email): void
    {
        $nodes = $this->http->XPath->query("//text()[normalize-space()='Number of Rooms']/ancestor::table[3]/preceding::tr[normalize-space()][1][contains(normalize-space(), 'Confirmation:')]");

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $h->general()
                ->confirmation($this->http->FindSingleNode(".", $root, true, "/{$this->opt($this->t('Confirmation:'))}\s*(\d+)/"));

            $cancellation = $this->http->FindSingleNode("./following::table[1]/descendant::text()[contains(normalize-space(), 'Cancellation policy:')]/following::text()[normalize-space()][1]/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Cancellation policy:'))}\s*(.+)/");

            if (!empty($cancellation)) {
                $h->general()
                    ->cancellation($cancellation);
            }

            $rootType = $this->http->FindSingleNode("./following::table[1]/descendant::text()[contains(normalize-space(), 'Room Type')]/following::text()[normalize-space()][1]/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Room Type'))}\s*(.+)/");
            $rate = $this->http->FindSingleNode("./following::table[1]/descendant::text()[contains(normalize-space(), 'Rate/Night')]/following::text()[normalize-space()][1]/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Rate/Night'))}\s*(.+)/");

            if (!empty($rootType) || !empty($rate)) {
                $room = $h->addRoom();

                if (!empty($rootType)) {
                    $room->setType(trim($rootType, ':'));
                }

                if (!empty($rate)) {
                    $room->setRate(trim($rate, ':'));
                }
            }

            $h->hotel()
                ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $root))
                ->address($this->http->FindSingleNode("./following::table[1]/descendant::text()[contains(normalize-space(), 'Address')]/following::text()[normalize-space()][1]/ancestor::td[1]", $root));

            $phone = $this->http->FindSingleNode("./following::table[1]/descendant::text()[contains(normalize-space(), 'Phone')]/following::text()[normalize-space()][1]/ancestor::td[1]", $root);

            if (!empty($phone)) {
                $h->setPhone($phone);
            }

            $fax = $this->http->FindSingleNode("./following::table[1]/descendant::text()[contains(normalize-space(), 'Fax')]/following::text()[normalize-space()][1]/ancestor::td[1]", $root);

            if (!empty($fax)) {
                $h->setFax($fax);
            }

            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("./following::table[1]/descendant::text()[contains(normalize-space(), 'Check In')]/following::text()[normalize-space()][1]/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Check In'))}[\s:]*(.+)/")))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("./following::table[1]/descendant::text()[contains(normalize-space(), 'Check Out')]/following::text()[normalize-space()][1]/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Check Out'))}[\s:]*(.+)/")));

            $rooms = $this->http->FindSingleNode("./following::table[1]/descendant::text()[contains(normalize-space(), 'Number of Rooms')]/following::text()[normalize-space()][1]/ancestor::td[1]", $root, true, "/\:?\s*(\d+)/");

            if (!empty($rooms)) {
                $h->booked()
                    ->rooms($rooms);
            }
        }
    }

    private function parseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='PNR Booking Ref:']/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='Carrier Ref']/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');
        }

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[normalize-space()='PNR Booking Ref:']", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        } elseif ($this->http->XPath->query("//text()[contains(normalize-space(),'PNR Booking Ref:')]")->length === 0) {
            $f->general()->noConfirmation();
        }

        $travellers = $this->http->FindNodes("//text()[normalize-space(.)='PASSENGERS']/ancestor::table[1]/following-sibling::table//tr[count(descendant::tr)=0]/td[normalize-space(.)][1]");

        if (!empty($travellers)) {
            $f->general()
                ->travellers($travellers, true);
        }

        $accounts = array_filter($this->http->FindNodes("//text()[normalize-space(.)='PASSENGERS']/ancestor::table[1]/following-sibling::table//tr[count(descendant::tr)=0]/td[normalize-space(.)][2]", null, "#[A-Z\d]{5,}#"));

        if (count($accounts) > 0) {
            $f->setAccountNumbers($accounts, false);
        }

        $xpath = "//text()[normalize-space(.)='From:']/ancestor::tr[1][contains(.,'Destination:') and contains(.,'Stops')]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $node = $this->http->FindSingleNode("./td[2]", $root);

            if (preg_match("#Flying Time:\s+(.+)\s+Stops:\s+(\d+)#", $node, $m)) {
                $s->extra()
                    ->duration($m[1])
                    ->stops($m[2]);
            }

            $aircraft = $this->http->FindSingleNode("ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/descendant::text()[normalize-space()='Type']/following::text()[normalize-space()][1]", $root);

            if (empty($aircraft)) {
                $aircraft = $this->http->FindSingleNode("./ancestor::table[contains(normalize-space(), 'Type')][1]/descendant::text()[starts-with(normalize-space(), 'Type')]/following::text()[normalize-space()][1]", $root);
            }

            if (!empty($aircraft)) {
                $s->extra()
                    ->aircraft($aircraft);
            }

            $miles = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[1]/descendant::text()[contains(.,'Miles') or contains(.,'Distance')]/following::text()[normalize-space(.)][1]", $root);

            if (!empty($miles)) {
                $s->extra()
                    ->miles($miles);
            }

            $cabin = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[1]/descendant::text()[contains(.,'Class')]/following::text()[normalize-space(.)][1]", $root, true, "/^(.+)\s\(/");

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            $bookingCode = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[1]/descendant::text()[contains(.,'Class')]/following::text()[normalize-space(.)][1]", $root, true, "/\(([A-Z]{1})\)/");

            if (!empty($bookingCode)) {
                $s->extra()
                    ->bookingCode($bookingCode);
            }

            $s->departure()
                ->date($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[contains(normalize-space(), ':')][1]/td[normalize-space(.)][1]", $root)))
                ->name($this->http->FindSingleNode("./following-sibling::tr[2]/td[normalize-space(.)][1]", $root));

            $depCode = $this->http->FindSingleNode("./following-sibling::tr[1]/td[normalize-space(.)][1]", $root, true, "#[A-Z]{3}#");

            if (!empty($depCode)) {
                $s->departure()
                    ->code($depCode);
            } else {
                $s->departure()
                    ->noCode();
            }

            $s->arrival()
                ->date($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[contains(normalize-space(), ':')][1]/td[normalize-space(.)][2]", $root)))
                ->name($this->http->FindSingleNode("./following-sibling::tr[2]/td[normalize-space(.)][2]", $root));

            $arrCode = $this->http->FindSingleNode("./following-sibling::tr[1]/td[normalize-space(.)][2]", $root, true, "#[A-Z]{3}#");

            if (!empty($arrCode)) {
                $s->arrival()
                    ->code($arrCode);
            } else {
                $s->arrival()
                    ->noCode();
            }

            $depTerminal = $this->http->FindSingleNode("./ancestor::table[1]/descendant::tr/td[1]/descendant::text()[contains(normalize-space(),'TERMINAL')]", $root, true, "#TERMINAL[:\s]+(.+)#");

            if (empty($depTerminal)) {
                $depTerminal = $this->http->FindSingleNode("./following-sibling::tr[4]/td[normalize-space(.)][1]", $root, true, "#Terminal[:\s]+(.+)#");
            }

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            $arrTerminal = $this->http->FindSingleNode("./ancestor::table[1]/descendant::tr/td[2]/descendant::text()[contains(normalize-space(),'TERMINAL')]", $root, true, "#TERMINAL[:\s]+(.+)#");

            if (empty($arrTerminal)) {
                $arrTerminal = $this->http->FindSingleNode("./following-sibling::tr[4]/td[normalize-space(.)][2]", $root, true, "#Terminal[:\s]+(.+)#");
            }

            if (!empty($arrTerminal)) {
                $s->arrival()
                    ->terminal($arrTerminal);
            }

            $sHeader = $this->http->FindSingleNode("ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1]", $root);

            if (preg_match("/Flight\s+Number\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/", $sHeader, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (preg_match("/Status\s*:\s*({$this->opt($this->t('statusVariants'))})$/i", $sHeader, $m)) {
                $s->extra()->status($m[1]);
            }

            $seats = explode("/", $this->http->FindSingleNode("./ancestor::table[contains(normalize-space(), 'Seat Number:')][1]/descendant::text()[starts-with(normalize-space(), 'Seat Number:')]/following::text()[normalize-space()][1]", $root));

            if (count($seats) > 0) {
                $s->setSeats($seats);
            }

            $carrier = $operator = null;
            $carrierRef = $this->http->FindSingleNode("ancestor::table[contains(normalize-space(),'Carrier Ref')][1]/descendant::text()[starts-with(normalize-space(),'Carrier Ref')]/following::text()[normalize-space()][1]", $root, true, '/^[A-Z\d]{5,}$/');
            $operatedBy = $this->http->FindSingleNode("ancestor::table[contains(normalize-space(),'Operated By')][1]/descendant::text()[starts-with(normalize-space(),'Operated By')]/following::text()[normalize-space()][1]", $root, true, '/^[\/\s]*(.{2,})$/');

            if (preg_match("/^(.{2,}?)\s+DBA\s+(.{2,})$/", $operatedBy, $m)) {
                // Gojet Airlines DBA United Express
                $carrier = $m[1];
                $operator = $m[2];
            } elseif ($carrierRef && $operatedBy) {
                $carrier = $operatedBy;
            } elseif ($operatedBy) {
                $operator = $operatedBy;
            }

            if (!empty($carrierRef) && !empty($carrier)) {
                $s->airline()->carrierConfirmation($carrierRef)->carrierName($carrier)->operator($operator, false, true);
            } else {
                $s->airline()->operator($operator, false, true);
            }
        }
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $year = date('Y', $this->date);
        $in = [
            //Wed 16 Aug 08:23AM
            '#^\s*(\w+)\s+(\d+)\s+(\w+)\s*(\d+:\d+(?:\s*[ap]m)?)\s*$#ui',
            //Wed 16 Aug
            '#^\s*(\w+)\s+(\d+)\s+(\w+)\s*$#ui',
        ];
        $out = [
            '$1, $2 $3 ' . $year . ', $4',
            '$1, $2 $3 ' . $year,
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("/^(?<week>[[:alpha:]\-]+), (?<date>\d+ [[:alpha:]]+ .+)/u", $date, $m)) {
            if ($year > 2000) {
                $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

                return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
            }
        } elseif (preg_match("/^\d+ [[:alpha:]]+ \d{4}(,\s*\d{1,2}:\d{2}(?: ?[ap]m)?)?$/ui", $date)) {
            return strtotime($date);
        }

        return null;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
