<?php

namespace AwardWallet\Engine\bahamasair\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "bahamasair/it-196901970.eml, bahamasair/it-281953301.eml, bahamasair/it-282546956.eml, bahamasair/it-285624800.eml, bahamasair/it-285892358.eml, bahamasair/it-295262103.eml, bahamasair/it-653022035.eml, bahamasair/it-758212573.eml, bahamasair/it-790789411.eml, bahamasair/it-792398446.eml, bahamasair/it-84320240.eml, bahamasair/it-877686129.eml, bahamasair/it-884462254.eml";

    public $providerCode;
    public static $detectProviders = [
        'bahamasair' => [
            'from' => [
                '@bahamasair.com',
            ],
            'body' => [
                '//a[contains(@href,".bahamasair.com")]',
                'Thank you for making Bahamasair your airline',
                'Sent by Bahamasair Holdings Ltd',
            ],
        ],
        'tanzania' => [
            'from' => ['airtanzania.co.tz'],
            'body' => [
                '//a[contains(@href,"airtanzania.crane.aero")]',
                '@airtanzania.co.tz',
                'Sent by Air Tanzania',
            ],
        ],
        'winair' => [
            'from' => ['@fly-winair.com'],
            'body' => [
                '//a[contains(@href,"fly-winair.sx")]',
                '@fly-winair.com',
                'www.fly-winair.sx',
            ],
        ],
        'precision' => [
            'from' => ['@precisionairtz.com'],
            'body' => [
                '//a[contains(@href,"book-precision.crane.aero") or contains(@href,"precisionairtz.crane.aero")]',
                'Sent by Precision Airlines',
                'choosing precision Air',
                '@precisionairtz.com',
            ],
        ],
        'airpeace' => [
            'from' => ['@flyairpeace.com'],
            'body' => [
                '//a[contains(@href,"flyairpeace.com")]',
                'Sent by Air Peace',
                '@flyairpeace.com ',
            ],
        ],
        [
            'from' => ['@flyliat20.com'],
            'body' => [
                '//a[contains(@href,".flyliat20.com")]',
                'Sent by LIAT',
                'www.flyliat20.com',
            ],
        ],
        [
            'from' => ['@nac.com.np'],
            'body' => [
                '@nac.com.np',
                'Sent by Nepal Airlines',
            ],
        ],
        [
            'from' => ['@caboverdeairlines.com'],
            'body' => [
                'Cabo Verde Airlines',
                '@caboverdeairlines.com',
            ],
        ],
        [
            'from' => ['@flypassionair.com'],
            'body' => [
                '@flypassionair.com',
                'Sent by PassionAir',
                'Flypassionair.com',
            ],
        ],
        [
            'from' => ['@sunriseairways.net'],
            'body' => [
                '@sunriseairways.net',
                'Sent by Sunrise Airways',
            ],
        ],
        [
            'from' => ['@ibomair.com'],
            'body' => [
                'choosing Ibom Air',
                'Sent by Ibom Air',
                '@ibomair.com',
            ],
        ],
        [
            'from' => ['@umzaair.com'],
            'body' => [
                'Sent by Umza Aviation Services',
                '@umzaair.com',
            ],
        ],
        [
            'from' => ['@maiair.aero'],
            'body' => [
                'Sent by Myanmar Airways International',
                '@maiair.com',
            ],
        ],
        [
            'from' => ['@airalbania.com.al'],
            'body' => [
                'Sent by Air Albania',
                '@airalbania.com.al',
            ],
        ],
        [
            'from' => ['@transnusa.co.id'],
            'body' => [
                '//a[contains(@href,"book-transnusa.")]',
                'Sent by TransNusa',
                '@transnusa.co.id',
            ],
        ],
        [
            'from' => ['@flyslm.com'],
            'body' => [
                '//a[contains(@href,".flyslm.com")]',
                'Sent by SURINAM AIRWAYS',
                '@flyslm.com',
            ],
        ],
        [
            'from' => ['@airmontenegro.com'],
            'body' => [
                '//a[contains(@href,".airmontenegro.com")]',
                'Sent by Air Montenegro',
                '@airmontenegro.com',
            ],
        ],
    ];
    public $detectSubject = [
        'Reservation Confirmation',
        'Ticket Confirmation',
        'Check-in Information Mail',
        'Online Check-in Information',
        'REISSUE BOOKING',
        'Boarding Information',
        'SSR OPTION Mail',
        'Flight Cancellation Mail',
    ];

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Your PNR No'                     => ['Your PNR No', 'Your reservationNo', 'Your Reference No', 'Your reservation number'],
            'Flight Number'                   => ['Flight Number', 'Flight No'],
            'Departure Port'                  => ['Departure Port', 'Departure'],
            'Go to PNR Page'                  => ['Go to PNR Page', 'Go to Reservation Page', 'Manage booking', 'View your Itinerary'],
            'Bundle'                          => ['Bundle', 'Gate'],
            'has been unavoidably cancelled'  => ['has been unavoidably cancelled', 'Flight Cancellation'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@bahamasair.com') !== false;
    }

    public static function getEmailProviders()
    {
        return array_filter(array_keys(self::$detectProviders), function ($v) {
            return (is_numeric($v)) ? false : true;
        });
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$detectProviders as $code => $detect) {
            if (empty($detect['from'])) {
                continue;
            }

            $byFrom = false;

            foreach ($detect['from'] as $dfrom) {
                if (stripos($headers['from'], $dfrom) !== false) {
                    $byFrom = true;

                    break;
                }
            }

            if ($byFrom == false) {
                continue;
            }

            foreach ($this->detectSubject as $dSubject) {
                if (stripos($headers['subject'], $dSubject) !== false) {
                    $this->providerCode = $code;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectedProvider = $this->getProviderByBody() !== null ? true : false;

        foreach (self::$dictionary as $lang => $dict) {
            if ($detectedProvider !== true) {
                if (empty($dict['Your PNR No']) || empty($dict['Flight Number']) || empty($dict['Departure Port']) || empty($dict['Go to PNR Page'])) {
                    continue;
                }

                if ($this->http->XPath->query("//tr[count(*) = 5][*[1][{$this->eq($dict['Flight Number'])}]][*[2][{$this->eq($dict['Departure Port'])}]]"
                        . "/ancestor::td/following-sibling::*[{$this->starts($dict['Your PNR No'])}][.//text()[{$this->eq($dict['Go to PNR Page'])}]]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            } else {
                if (empty($dict['Your PNR No']) || empty($dict['Departure Port'])) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($dict['Your PNR No'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Departure Port'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->parseEmailHtml($email);

        if (empty($this->providerCode)) {
            $this->providerCode = $this->getProvider($parser);
        }

        if (!empty($this->providerCode) && !is_numeric($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
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

    private function getProviderByBody(): ?string
    {
        foreach (self::$detectProviders as $code => $detect) {
            if (empty($detect['body'])) {
                continue;
            }

            foreach ($detect['body'] as $search) {
                if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                    || (stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)
                ) {
                    $this->providerCode = $code;

                    return $code;
                }
            }
        }

        return null;
    }

    private function getProvider(\PlancakeEmailParser $parser): ?string
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (!empty($this->providerCode)) {
            return $this->providerCode;
        }

        return $this->getProviderByBody();
    }

    private function parseEmailHtml(Email $email): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02  |  0167544038003-004
        ];

        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//tr[{$this->eq($this->t('Your PNR No'))}]/following-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
        ;

        if ($this->http->XPath->query("//node()[{$this->contains($this->t('has been unavoidably cancelled'))}]")->length > 0) {
            $f->general()
                ->cancelled()
                ->status('Cancelled');
        }

        $seats = $meals = [];
        $travellers = $infants = [];
        $nodes = $this->http->XPath->query("//tr[{$this->starts($this->t('Seat:'))}]/preceding-sibling::tr[1][normalize-space()][count(*) > 2][not({$this->contains($this->t('Seat:'))})]");

        foreach ($nodes as $root) {
            $name = $this->http->FindSingleNode("*[1]", $root);
            $surname = $this->http->FindSingleNode("*[2]", $root);
            $ticket = $this->http->FindSingleNode("*[normalize-space()][last()]", $root, true, "/^\s*(\d{8,})\s*$/");

            if (!empty($name) && !empty($surname)) {
                if (!empty($this->http->FindSingleNode("*[3][{$this->eq($this->t('Infant'))}]", $root))) {
                    $infants[] = $name . ' ' . $surname;
                } else {
                    $travellers[] = $name . ' ' . $surname;
                }

                if (!empty($ticket) && !in_array($ticket, array_column($f->getTicketNumbers(), 0))) {
                    $f->issued()
                        ->ticket($ticket, false, $name . ' ' . $surname);
                }
            }

            $flNumber = $this->http->FindSingleNode("preceding::text()[{$this->starts($this->t('Flight No:'))}][1]", $root, true, "/^\s*Flight No:\s*([A-Z\d ]+?)\s*$/");

            if (!empty($flNumber)) {
                $seat = $this->http->FindSingleNode("following-sibling::tr[1]/*[{$this->starts($this->t('Seat:'))}]",
                    $root, true, "/^\s*Seat:\s*(\d{1,3}[A-Z])\s*$/");

                if (!empty($seat)) {
                    $seats[$flNumber][] = ['seat' => $seat, 'name' => $name . ' ' . $surname];
                } else {
                    $seats[$flNumber][] = [];
                }
                $meals[$flNumber][] = $this->http->FindSingleNode("following-sibling::tr[1]/*[starts-with(normalize-space(), 'Meal:')]",
                    $root, true, "/^\s*Meal:\s*(\S.+)\s*$/");
            }
        }

        $f->general()->travellers(array_unique($travellers), true);

        if (!empty($infants)) {
            $f->general()->infants(array_unique($infants), true);
        }

        $airportsNames = $airportsNamesAll = [];
        $routeName = $this->http->FindNodes("//tr/*[{$this->starts($this->t('Flight No:'))}]/preceding-sibling::*[1]");

        foreach ($routeName as $rn) {
            if (preg_match("/^\s*(?<name1>.+?)\s*\((?<code1>[A-Z]{3})\)\s*-\s*(?<name2>.+?)\s*\((?<code2>[A-Z]{3})\)\s*$/", $rn, $m)) {
                $m = preg_replace("/^\s*T\d (?:-\s*)?/", '', $m);
                $airportsNamesAll[$m['code1']][] = $m['name1'];
                $airportsNamesAll[$m['code2']][] = $m['name2'];
            }
        }

        foreach ($airportsNamesAll as $code => $an) {
            if (count(array_unique($an)) === 1) {
                $airportsNames[$code] = $an[0];
            }
        }

        // Segments
        $xpath = "//tr[*[normalize-space()][1][{$this->eq($this->t('Flight Number'))}] and *[normalize-space()][2][{$this->eq($this->t('Departure Port'))}]]/following::text()[normalize-space()][1]/ancestor::*[not({$this->contains($this->t('Your PNR No'))})][last()]//tr[count(*[normalize-space()]) > 3][not({$this->contains($this->t('Departure Port'))})]";
        $nodes = $this->http->XPath->query($xpath);
        // $this->logger->debug("[XPATH-flight]: " . $xpath);

        if (count($seats) !== $nodes->length) {
            $seats = [];
        }

        if (count($seats) !== $nodes->length) {
            $meals = [];
        }

        foreach ($nodes as $key => $root) {
            $s = $f->addSegment();

            // Airline
            $node = $this->http->FindSingleNode("td[1]", $root);

            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])[-\s]*(?<fn>\d{1,5})\s*$/", $node, $m)) {
                $s->airline()->name($m['al'])->number($m['fn']);
            }

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("td[2]", $root))
                ->date(strtotime(str_replace('/', '.', $this->http->FindSingleNode("td[4]", $root))))
            ;

            if (!empty($s->getDepCode()) && isset($airportsNames[$s->getDepCode()])) {
                $s->departure()
                    ->name($airportsNames[$s->getDepCode()]);
            }

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("td[3]", $root))
            ;
            $date = strtotime(str_replace('/', '.', $this->http->FindSingleNode("td[5]", $root)));

            if (empty($date) && !empty($this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure Date'))}]/following::text()[normalize-space()][1][{$this->eq($this->t('Bundle'))}]"))) {
                $s->arrival()
                    ->noDate();
            } else {
                $s->arrival()
                    ->date($date);
            }

            if (!empty($s->getArrCode()) && isset($airportsNames[$s->getArrCode()])) {
                $s->arrival()
                    ->name($airportsNames[$s->getArrCode()]);
            }

            $terminalInfo = $this->http->FindSingleNode("following::tr[not(.//tr)][1][{$this->starts('Terminal Info:')}]", $root, true, "/Terminal Info:\s*(.+)/");

            if (!empty($terminalInfo)) {
                if (preg_match("/{$this->opt('Departs From')}\s+(?<depTerminal>.+)\s+{$this->opt('and')}\s+{$this->opt('arrives at')}\s+(?<arrTerminal>.+)$/i", $terminalInfo, $m)) {
                    $s->departure()
                        ->terminal($m["depTerminal"]);
                    $s->arrival()
                        ->terminal($m["arrTerminal"]);
                } elseif (preg_match("/{$this->opt('Departs From')}\s*(.+)$/i", $terminalInfo, $m)) {
                    $s->departure()
                        ->terminal($m[1]);
                }
            }

            // Extra
            if (!empty($s->getAirlineName()) && !empty($s->getFlightNumber())) {
                if (isset($seats[$s->getAirlineName() . $s->getFlightNumber()])) {
                    $seats[$s->getAirlineName() . $s->getFlightNumber()] = array_filter($seats[$s->getAirlineName() . $s->getFlightNumber()]);

                    foreach ($seats[$s->getAirlineName() . $s->getFlightNumber()] as $seatValue) {
                        $s->extra()->seat($seatValue['seat'], false, false, $seatValue['name']);
                    }
                }

                if (isset($meals[$s->getAirlineName() . $s->getFlightNumber()]) && !empty(array_filter($meals[$s->getAirlineName() . $s->getFlightNumber()]))) {
                    $s->extra()->meals(array_filter($meals[$s->getAirlineName() . $s->getFlightNumber()]));
                }
            }
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Your PNR No']) || empty($phrases['Departure Port'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Your PNR No'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Departure Port'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

    private function contains($field, $text = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field, $text = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'starts-with(' . $text . ',"' . $s . '")';
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

    private function opt($field, $delimiter = '/'): string
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
