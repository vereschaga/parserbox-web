<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-732512721.eml, lufthansa/it-755300969.eml, lufthansa/it-755488630.eml";
    public static $providers = [
        'lufthansa' => [
            'from'    => '@lufthansa.com',
            'bodyUrl' => ['lufthansa.com'],
            'body'    => [
                'Your Lufthansa Team', // en
            ],
        ],

        'austrian' => [
            'from'    => 'service@austrian.com',
            'bodyUrl' => ['.austrian.com'],
            'body'    => [
                'Your Austrian Team', // en
            ],
        ],

        'brussels' => [
            'from'    => '@boardingpass.brusselsairlines.com',
            'bodyUrl' => ['.brusselsairlines.com/', 'www.brusselsairlines.com', 'once.brusselsairlines.com'],
            'body'    => [
                'Your Brussels Airlines Team', // en
            ],
        ],
    ];

    public $lang;
    public static $dictionary = [
        'en' => [
            //            'Status' => '',
            //            'Seat' => '',
            //            'operated by' => '',
            //            'Terminal' => '',
            'Booking code'  => 'Booking code',
            'Ticket number' => 'Ticket number',
        ],
        'de' => [
            'Status'        => 'Status',
            'Seat'          => 'Sitz',
            //            'operated by' => '',
            'Terminal'      => 'Terminal',
            'Booking code'  => 'Buchungscode',
            'Ticket number' => 'Ticketnummer',
        ],
    ];

    private $detectSubject = [
        // en
        'Boarding pass for your flight | ',
        'Check-in Confirmation for your flight | ',
        // de
        'Bordkarte für Ihren Flug | ',
        'Check-in Bestätigung für Ihren Flug | ',
    ];
    private $detectBody = [
        'en' => [
            'We have issued the boarding pass for your upcoming flight',
            'We have issued a check-in confirmation for your upcoming flight',
        ],
        'de' => [
            'Wir haben eine mobile Bordkarte für Ihre bevorstehende Reise ausgestellt',
            'Wir haben eine Check-in Bestätigung für Ihre bevorstehende Reise ausgestellt',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]lufthansa.com\b/u", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$providers as $code => $provider) {
            if (!empty($provider['from']) && stripos($headers['from'], $provider['from']) !== false) {
                foreach ($this->detectSubject as $subject) {
                    if (stripos($headers['subject'], $subject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectedProvider = false;

        foreach (self::$providers as $code => $provider) {
            if (!empty($provider['body']) && $this->http->XPath->query("//node()[{$this->contains($provider['body'])}]")->length > 0
                || !empty($provider['bodyUrl']) && $this->http->XPath->query("//a/@href[{$this->contains($provider['bodyUrl'])}]")->length > 0
            ) {
                $detectedProvider = true;

                break;
            }
        }

        if ($detectedProvider === false) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
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

        $providerCode = null;

        foreach (self::$providers as $code => $provider) {
            if (!empty($provider['from']) && stripos($parser->getCleanFrom(), $provider['from']) !== false
                || !empty($provider['body']) && $this->http->XPath->query("//node()[{$this->contains($provider['body'])}]")->length > 0
                || !empty($provider['bodyUrl']) && $this->http->XPath->query("//a/@href[{$this->contains($provider['bodyUrl'])}]")->length > 0
            ) {
                $providerCode = $code;

                break;
            }
        }

        if (!empty($providerCode)) {
            $email->setProviderCode($providerCode);
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

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Booking code"]) && !empty($dict["Ticket number"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Booking code'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Ticket number'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email): void
    {
        $patterns = [
            'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 175-2345005149-23  |  1752345005149/23
        ];

        $f = $email->add()->flight();

        // General
        $confirmation = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2][*[normalize-space()][1][{$this->eq($this->t('Booking code'))}]]/*[normalize-space()][2]", null, true, '/^\s*([A-Z\d]{5,8})\s*$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->eq($this->t('Booking code'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Status'))}]/preceding::text()[normalize-space()][1]/ancestor::tr[1]",
                null, true, "/^\s*([[:alpha:] \-.]+,[[:alpha:] \-.]+(?:\s*\(Infant\))?)\s*$/");

        if (empty($traveller) && $this->http->XPath->query("//text()[{$this->eq($this->t('Status'))}]")->length === 0) {
            $rootNotStatus = $this->http->XPath->query("//text()[{$this->eq($this->t('Seat'))}]/ancestor::*[following-sibling::*[descendant::text()[normalize-space()][1][{$this->eq($this->t('Terminal'))}]][1]][count(preceding-sibling::*[normalize-space()])=2 or count(preceding-sibling::*[normalize-space()])=3]/preceding-sibling::*[normalize-space()]");

            if ($rootNotStatus->length == 2 || $rootNotStatus->length == 3) {
                $traveller = $this->http->FindSingleNode("*", $rootNotStatus->item($rootNotStatus->length - 2));
            }
        }

        $this->logger->debug('$traveller = ' . print_r($traveller, true));

        $isInfant = false;

        if (preg_match("/^\s*(.+?)\s*\(Infant\)\s*$/u", $traveller, $m)) {
            $isInfant = true;
            $traveller = $m[1];
        }

        if ($traveller) {
            $traveller = $this->normalizeTraveller($traveller);
        }

        if ($isInfant) {
            $f->general()
                ->infant($traveller, true);
        } else {
            $f->general()
                ->traveller($traveller, true);
        }

        // Issued
        $ticket = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2][*[normalize-space()][1][{$this->eq($this->t('Ticket number'))}]]/*[normalize-space()][2]", null, true, "/^\s*({$patterns['eTicket']})\s*$/");
        $f->issued()->ticket($ticket, false, $traveller);

        // Segment
        $sXpath = "//text()[{$this->eq($this->t('Terminal'))}]/preceding::text()[normalize-space()][1]/ancestor::*[not(.//text()[{$this->eq($this->t('Terminal'))}])][last()]//tr[not(.//tr)]";
        $roots = $this->http->XPath->query($sXpath);
        $rootsD = 0;

        $s = $f->addSegment();
        // Airline
        $flight = $this->http->FindSingleNode("*[normalize-space()][2]", $roots->item(0));

        if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})\s*$/", $flight, $m)) {
            $s->airline()
                ->name($m['al'])
                ->number($m['fn']);
        }
        $operatedBy = $this->http->FindSingleNode("*[normalize-space()][1]", $roots->item(1), true, "/{$this->opt($this->t('operated by'))}\s*(.+)/");

        if (!empty($operatedBy)) {
            $rootsD = 1;
            $s->airline()
                ->operator($operatedBy);
        }

        $date = $this->http->FindSingleNode("*[normalize-space()][1]", $roots->item(0), true, "/^\s*\d{1,2}[A-Z]{2,}\d{2}\s*$/");
        // Departure
        $s->departure()
            ->code($this->http->FindSingleNode("*[normalize-space()][1]", $roots->item(1 + $rootsD), true, "/^\s*([A-Z]{3})\s*$/"))
            ->name($this->http->FindSingleNode("*[normalize-space()][1]", $roots->item(2 + $rootsD)))
            ->terminal($this->http->FindSingleNode("//tr[not(.//tr)][{$this->eq($this->t('Terminal'))}]/following-sibling::tr[normalize-space()][1]"), false, true)
        ;
        $time = $this->http->FindSingleNode("*[normalize-space()][1]", $roots->item(3 + $rootsD));

        if (!empty($date) && !empty($time)) {
            $s->departure()
                ->date($this->normalizeDate($date . ', ' . $time));
        }

        // Arrival
        $s->arrival()
            ->code($this->http->FindSingleNode("*[normalize-space()][2]", $roots->item(1 + $rootsD), true, "/^\s*([A-Z]{3})\s*$/"))
            ->name($this->http->FindSingleNode("*[normalize-space()][2]", $roots->item(2 + $rootsD)))
        ;
        $time = $this->http->FindSingleNode("*[normalize-space()][2]", $roots->item(3 + $rootsD));

        if (!empty($date) && !empty($time)) {
            if (preg_match("/^\s*(\d{1,2}:\d{2})\s*([\-+]\s*\d)\d*$/", $time, $m)) {
                $s->arrival()
                    ->date($this->normalizeDate($date . ', ' . $m[1]));

                if (!empty($s->getArrDate())) {
                    $s->arrival()
                        ->date(strtotime($m[2] . ' days', $s->getArrDate()));
                }
            } else {
                $s->arrival()
                    ->date($this->normalizeDate($date . ', ' . $time));
            }
        } elseif (!empty($date) && !empty($s->getDepDate())) {
            $s->arrival()
                ->noDate();
        }

        // Extra
        $cabin = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Status'))}]/ancestor::tr[2][count(*[normalize-space()]) = 2][descendant::text()[normalize-space()][1][{$this->eq($this->t('Status'))}]]/*[normalize-space()][2][count(.//text()[normalize-space()]) < 3]/descendant::text()[normalize-space()][last()]",
            null, true, "/^\s*([[:alpha:]][[:alpha:] ]+)\s*$/");

        if (empty($cabin) && ($rootNotStatus->length == 2 || $rootNotStatus->length == 3)) {
            $cabin = $this->http->FindSingleNode("(./descendant::tr/*[not(.//tr)])[last()]", $rootNotStatus->item($rootNotStatus->length - 1));
        }
        $s->extra()
            ->cabin($cabin);

        $seat = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Seat'))}]/following::tr[normalize-space()][1]",
            null, true, "/^\s*(\d{1,3}[A-Z]|WL)\s*$/");

        if ($seat == 'WL') {
            $s->extra()
                ->status('waitlisted');
        } elseif (empty($seat) && $isInfant === true) {
        } else {
            $s->extra()->seat($seat, false, false, $traveller);
        }
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

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

    private function normalizeDate(?string $date): ?int
    {
        $this->logger->debug('date begin = ' . print_r($date, true));

        if (empty($date)) {
            return null;
        }

        $in = [
            //            // 30MAR24, 10:30
            '/^\s*(\d{1,2})\s*([[:alpha:]]+)\s*(\d{2})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 20$3, $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        $this->logger->debug('date end = ' . print_r($date, true));

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

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MASTER|MSTR|MISS|MRS|MR|MS|DR)';

        return preg_replace([
            "/^(.{2,}?)\s+(?:{$namePrefixes}[.\s]*)+$/is",
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
            '/^\s*(.*?)\s*,\s*(.*?)\s*$/',
        ], [
            '$1',
            '$1',
            '$2 $1',
        ], $s);
    }
}
