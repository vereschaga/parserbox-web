<?php

namespace AwardWallet\Engine\justfly\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ElectronicTicket extends \TAccountChecker
{
    public $mailFiles = "justfly/it-2219134.eml, justfly/it-2504074.eml, justfly/it-2868710.eml, justfly/it-3008647.eml, justfly/it-30811062.eml, justfly/it-3383527.eml, justfly/it-3686832.eml, justfly/it-5154625.eml, justfly/it-5197242.eml, justfly/it-5238079.eml, justfly/it-5598464.eml, justfly/it-5598475.eml";

    private $subjects = [
        'en' => ['Your Electronic Ticket'],
    ];

    private $langDetectors = [
        'en' => [
            'our electronic tickets are ready',
            'Are you ready for your flight',
            'To access your booking details at all times',
            'Thank you for booking with',
        ],
    ];
    private $lang = '';
    private static $dictionary = [
        'en' => [
            'YOUR BOOKING REFERENCE NUMBER IS:' => ['YOUR BOOKING REFERENCE NUMBER IS:', 'Booking Reference Number:'],
        ],
    ];

    private $providerCode = '';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'justfly.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true || stripos($headers['from'], 'flighthub.com') !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // Detecting Provider
        $this->assignProvider();

        // Detecting Language
        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return null;
        }

        $result = [];

        $its = $this->parseEmail($email);

        if (!empty($its['TotalCharge'])) {
            $tot = $its['TotalCharge'];
            unset($its['TotalCharge']);
            $result['parsedData']['TotalCharge']['Amount'] = $tot['Amount'];
            $result['parsedData']['TotalCharge']['Currency'] = $tot['Currency'];
        }
        $email->setProviderCode($this->providerCode);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider() === false) {
            return false;
        }

        // Detecting Language
        return $this->assignLang();
    }

    public static function getEmailProviders()
    {
        return ['justfly', 'flighthub'];
    }

    protected function parseEmail(Email $email)
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $bookingRefNum = $this->http->FindSingleNode("//strong[contains(normalize-space(.),'Justfly')]", null, true, "/[0-9-]+/");

        $pax = [];
        $travellers = $this->http->FindNodes("//text()[contains(normalize-space(.),'eTicket Number')]/ancestor::td[1]/preceding-sibling::td", null, "/^{$patterns['travellerName']}$/");
        $travellers = array_filter($travellers);

        if (count($travellers) === 0) {
            $travellers = $this->http->FindNodes("//text()[normalize-space(.)='Traveller(s)']/ancestor::tr[1]/following-sibling::tr/descendant::td[not(.//td) and normalize-space(.)][1]", null, "/^{$patterns['travellerName']}$/");
        }

        if (count($travellers)) {
            $pax = array_unique($travellers);
        }

        $xpath = "//td[3][count(descendant::tr)=0 and contains(., ':')]/ancestor::tr[2]";
        $rows = $this->http->XPath->query($xpath);

        if ($rows->length === 0) {
            $this->logger->alert('Segments root not found: ' . $xpath);
        }

        $rls = $this->http->XPath->query($xpath = "//text()[{$this->contains($this->t('Record Locator:'))}]/ancestor::*[1]");
        $this->logger->debug($xpath);

        foreach ($rls as $node) {
            if (preg_match("#^(.+?)\s+{$this->opt($this->t('Record Locator:'))}\s*([A-Z\d]+)#", $node->nodeValue, $m)) {
                $airline[$m[1]] = $m[2];
            } elseif (preg_match("#^(.+?)\s+{$this->opt($this->t('Record Locator:'))}#", $node->nodeValue, $m)
                && ($val = $this->http->FindSingleNode("following-sibling::*[normalize-space(.)!=''][1]", $node))) {
                $airline[$m[1]] = $val;
            }
        }
        // it-2219134.eml
        // it-5197242.eml
        if (empty($airline) && empty($bookingRefNum)) {
            $bookingRefNum = $this->http->FindSingleNode("//text()[{$this->eq($this->t('YOUR BOOKING REFERENCE NUMBER IS:'))}]/following::text()[normalize-space()][1]", null, true, "#^\s*([\d\-]{9,})\s*$#");

            if (empty($bookingRefNum)) {
                $bookingRefNum = $this->http->FindSingleNode("//text()[{$this->contains($this->t('YOUR BOOKING REFERENCE NUMBER IS:'))}]/ancestor::*[1]/following-sibling::*[normalize-space()][1]");
            }

            if (!$bookingRefNum) {
                $bookingRefNum = $this->http->FindSingleNode("//text()[{$this->contains($this->t('YOUR BOOKING REFERENCE NUMBER IS:'))}]/following-sibling::*[normalize-space()][1]");
            }
        }

        if (empty($airline) && empty($bookingRefNum)) {
            $this->logger->alert('Confirmation empty');
        }

        $airs = [];

        foreach ($rows as $row) {
            $flightCells = $this->http->FindNodes("descendant::tr[2]/td[1]", $row);

            if (
                preg_match('/([A-z ]+)\s+\d/', array_shift($flightCells), $m)
                && isset($airline)
                && !empty($airline[trim($m[1])])
            ) {
                $airs[$airline[trim($m[1])]][] = $row;

                continue;
            } elseif ($bookingRefNum) {
                $airs[$bookingRefNum][] = $row;
            } elseif ($this->http->XPath->query('//node()[contains(normalize-space(.),"To review your itinerary and for instructions to check-in online, follow the link below")]')->length > 0) {
                $airs[CONFNO_UNKNOWN][] = $row;
            }
        }

        foreach ($airs as $rl => $rows) {
            $f = $email->add()->flight();

            foreach ($rows as $row) {
                $segment = $f->addSegment();

                $rename = $this->http->FindNodes("descendant::td[2]//text()[normalize-space(translate(.,'1234567890-','')) != '']", $row);
                $segment->departure()->name(array_shift($rename));

                if (preg_match("#^\s*([A-Z]{3})\s*$#", array_shift($rename), $m)) {
                    $segment->departure()->code($m[1]);
                }
                $segment->arrival()->name(array_shift($rename));

                if (preg_match("#^\s*([A-Z]{3})\s*$#", array_shift($rename), $m)) {
                    $segment->arrival()->code($m[1]);
                }

                // DepartureTerminal
                // ArrivalTerminal
                $patterns['terminal'] = '/Terminal\s+([-A-z\d\s]+)$/i'; // Terminal C
                $terminalDep = $this->http->FindSingleNode('./descendant::td[3]//*[self::hr or self::br]/preceding::text()[normalize-space(.)][1]', $row, true, $patterns['terminal']);

                if ($terminalDep) {
                    $segment->departure()->terminal($terminalDep);
                }
                $terminalArr = $this->http->FindSingleNode('./descendant::td[3]//*[self::hr or self::br]/following::text()[normalize-space(.)][1]', $row, true, $patterns['terminal']);

                if ($terminalArr) {
                    $segment->arrival()->terminal($terminalArr);
                }

                $res = $this->http->FindNodes("descendant::td[4]//text()[normalize-space(.)]", $row);
                $segment->departure()->date(strtotime(array_shift($res)));
                $segment->arrival()->date(strtotime(array_shift($res)));

                $flightCells = $this->http->FindNodes("descendant::tr[2]/td[1]", $row);
                $flightRow = implode(' ', $flightCells);

                // AirlineName
                // FlightNumber
                if (preg_match('/([A-z ]+)\s+(\d+)/', array_shift($flightCells), $m)) {
                    $segment->airline()->name($m[1]);
                    $segment->airline()->number($m[2]);
                }

                // Operator
                if (preg_match('/Operated by\s*(.{2,})/', $flightRow, $m)) {
                    $segment->airline()->operator($m[1]);
                }
            }

            if ($rl == CONFNO_UNKNOWN) {
                $f->general()->noConfirmation();
            } else {
                $f->general()->confirmation($rl);
            }

            if ($pax) {
                $f->general()->travellers($pax, true);
            }
            $s = $f->getSegments();
            $airline = $s[0]->getAirlineName();

            if (!empty($airline)) {
                $nodes = array_filter($this->http->FindNodes("//text()[contains(.,'eTicket Number') and contains(.,'{$airline}')]/ancestor::td[1]", null, "#\d[\d-]+#"));

                if (count($nodes) > 0) {
                    $f->issued()->tickets($nodes, false);
                }
            }
        }
        $total = $this->http->FindSingleNode("//text()[contains(normalize-space(.), \"Total:\")]", null, true);
        $currency = $this->http->FindSingleNode("//text()[contains(normalize-space(.), \"Total:\")]", null, true, "/[A-Z]{3}/");

        if (preg_match('/[0-9-,.]+/', $total, $math)) {
            $total = preg_replace('/,/', '', $math[0]);
        } else {
            $total = "";
        }
        $its = $email->getItineraries();

        if (count($its) == 1) {
            $its[0]->price()->total($total);
            $base = $this->http->FindSingleNode("//text()[contains(normalize-space(.), \"Airfare:\")]/ancestor::td[1]/following-sibling::td[1]", null, true);

            if (preg_match("/[0-9-,.]+/", $base, $mat)) {
                $base = preg_replace('/,/', '', $mat[0]);
                $its[0]->price()->cost($base);
            }
            $tax = $this->http->FindSingleNode("//text()[contains(normalize-space(.), \"Taxes\")]/ancestor::td[1]/following-sibling::td[1]", null, true);

            if (preg_match("/[0-9-,.]+/", $tax, $b)) {
                $tax = preg_replace('/,/', '', $b[0]);
                $its[0]->price()->tax($tax);
            }
            $its[0]->price()->currency($currency);
        } else {
            $email->price()->total($total);
            $email->price()->currency($currency);
        }

        return $its;
    }

    private function assignProvider(): bool
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Thanks for booking with Justfly") or contains(.,"www.justfly.com")]')->length > 0;

        if ($condition1) {
            $this->providerCode = 'justfly';

            return true;
        }

        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"FlightHub wishes you a safe and pleasant trip") or contains(.,"www.flighthub.com")]')->length > 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//link.flighthub.com")]')->length > 0;

        if ($condition1 || $condition2) {
            $this->providerCode = 'flighthub';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
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

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
