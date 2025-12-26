<?php

namespace AwardWallet\Engine\icelandair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parsers icelandair/YourFlightTicket (in favor of icelandair/YourIcelandairTicket)

class YourIcelandairTicket extends \TAccountChecker
{
    public $mailFiles = "icelandair/it-9065487.eml, icelandair/it-9857444-is.eml, icelandair/it-443324189.eml, icelandair/it-437292952-is.eml, icelandair/it-439023736-is.eml";

    public $reSubject = [ // required `Icelandair` in string!
        "is" => "Icelandair: Flugmiði til",
        "en" => "Your Icelandair Ticket to",
    ];

    public $reBody = 'Icelandair';
    public $reBody2 = [
        "is"  => "Ferðin þín",
        "en"  => "Your journey",
        "en2" => "Outbound flight",
    ];

    public static $dictionary = [
        "is" => [
            'Booking reference' => ['Bókunarnúmer', 'Bókunarnúmer flugs'],
            'operator'          => 'Rekstrarfélag',
            'To Terminal'       => 'Lending við flugstöðvarbyggingu',
            'From Terminal'     => 'Frá Terminal',
            'statusVariants'    => 'Staðfest',
            // 'Class' => '',
            'Seat'            => ['Sæti', 'Seat'],
            'Ticket no.'      => ['Miðanúmer', 'Ticket no.'],
            'Passengers'      => 'Farþegar',
            'Date'            => ['Dagsetning', 'Date'],
            'ffNumber'        => 'Saga Blue meðlimur',
            'totalPrice'      => ['Heildarupphæð:', 'Total airfare:'],
            'Air fare:'       => ['Flugfargjald:', 'Air fare:'],
            'Extras:'         => ['Aukaþjónusta:', 'Extras:'],
            'Taxes and fees:' => ['Skattar og gjöld:', 'Taxes and fees:'],
        ],
        "en" => [
            'operator'       => 'Operated by',
            'statusVariants' => 'Confirmed',
            // 'ffNumber' => '',
            'totalPrice' => ['Total airfare:', 'Total due:'],
        ],
    ];

    public $lang = "en";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@icelandair.is') !== false
            || stripos($from, '@tickets.icelandair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $email->setType('YourIcelandairTicket' . ucfirst($this->lang));

        $xpathTime = 'contains(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'\/’[:alpha:] ]*[[:alpha:]]', // Holowko/Matthew Mr
        ];

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//table[{$this->eq($this->t('Booking reference'))}]/following::table[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//table[{$this->eq($this->t('Booking reference'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $segments = $this->http->XPath->query("//tr[ count(*)=4 and *[2][normalize-space()='' and descendant::img] and *[3][{$xpathTime}] ]");

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("*[1]", $root)));

            $td3Rows = $this->http->FindNodes("*[3]/descendant-or-self::*[ div[normalize-space()][3] ][1]/div[normalize-space()]", $root);
            $td3Text = implode("\n", $td3Rows);

            $airports = preg_split("/\s+[-–]\s+/", $td3Rows[0]);

            if (count($airports) === 2) {
                $patternNC = "/^(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/";
                $patternC = "/^[\s(]*(?<code>[A-Z]{3})[\s)]*$/";

                if (preg_match($patternNC, $airports[0], $m)) {
                    $s->departure()->name($m['name'])->code($m['code']);
                } elseif (preg_match($patternC, $airports[0], $m)) {
                    $s->departure()->code($m['code']);
                } else {
                    $s->departure()->name($airports[0]);
                }

                if (preg_match($patternNC, $airports[1], $m)) {
                    $s->arrival()->name($m['name'])->code($m['code']);
                } elseif (preg_match($patternC, $airports[1], $m)) {
                    $s->arrival()->code($m['code']);
                } else {
                    $s->arrival()->name($airports[1]);
                }
            }

            if ($date && preg_match("/({$patterns['time']})\s+[-–]\s+({$patterns['time']})(?:\s*[+]\s*(?<overnight>\d{1,3}\b)|$)/u", $td3Rows[1], $m)) {
                $s->departure()->date(strtotime($m[1], $date));
                $dateArr = strtotime($m[2], $date);

                if ($dateArr && !empty($m['overnight'])) {
                    $dateArr = strtotime("+{$m['overnight']} days", $dateArr);
                }

                $s->arrival()->date($dateArr);
            }

            if (preg_match("/\b(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $td3Rows[2], $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            if (preg_match("/^{$this->opt($this->t('operator'))}[: ]+(.{2,})$/m", $td3Text, $m)) {
                if (preg_match("/^(.{2,}?)\s+(\d+)$/", $m[1], $m2)) {
                    // Alaska Airlines 539
                    $s->airline()->carrierName($m2[1])->carrierNumber($m2[2]);
                } else {
                    $s->airline()->operator($m[1]);
                }
            }

            if (preg_match("/^{$this->opt($this->t('To Terminal'))}\s+([\w ]+)$/im", $td3Text, $m)) {
                $s->arrival()->terminal($m[1]);
            }

            if (!empty($td3Rows[3]) && !preg_match("/^{$this->opt($this->t('operator'))}/i", $td3Rows[3], $m)
                && !preg_match("/^{$this->opt($this->t('To Terminal'))}/i", $td3Rows[3], $m)
            ) {
                $s->extra()->aircraft($td3Rows[3]);
            }

            $td4Rows = $this->http->FindNodes("*[4]/descendant::text()[normalize-space()]", $root);
            $td4Text = implode("\n", $td4Rows);

            $duration = $this->re("/^(\d[hm \d]+)(?:\n|$)/i", $td4Text);

            if (!empty($duration)) {
                $s->extra()->duration($duration);
            }

            $s->extra()->status($this->re("/^({$this->opt($this->t('statusVariants'))})$/m", $td4Text), false, true);

            if (preg_match("/^{$this->opt($this->t('From Terminal'))}\s+([\w ]+)$/im", $td4Text, $m)) {
                $s->departure()->terminal($m[1]);
            }

            if (preg_match("/^(.*{$this->opt($this->t("Class"))}.*)$/m", $td4Text, $m)) {
                $s->extra()->cabin($m[1]);
            }

            if (!empty($s->getDepCode()) && !empty($s->getArrCode())) {
                $routeVariants = [$s->getDepCode() . ' - ' . $s->getArrCode(), $s->getDepCode() . '-' . $s->getArrCode()];
                $seats = array_filter($this->http->FindNodes("//tr[ *[4][{$this->eq($this->t('Seat'))}] ]/ancestor::table[1]/descendant::tr[ *[2][{$this->eq($routeVariants)}] ]/*[4]", null, '/^(\d+[A-Z])[* ]*$/'));

                foreach ($seats as $seat) {
                    $pax = $this->http->FindSingleNode("//text()[{$this->eq($seat)}]/ancestor::tr[1]/preceding::table[normalize-space()][1]/descendant::text()[normalize-space()][1]");

                    if (!empty($pax)) {
                        $s->extra()
                            ->seat($seat, false, false, $pax);
                    } else {
                        $s->extra()
                            ->seat($seat);
                    }
                }
            }
        }

        $tickets = array_unique(array_filter($this->http->FindNodes("//tr[ *[3][{$this->eq($this->t('Ticket no.'))}] ]/ancestor::table[1]/descendant::tr/*[3]", null, "/^\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}$/")));

        foreach ($tickets as $ticket) {
            $pax = $this->http->FindSingleNode("//text()[{$this->contains($ticket)}]/ancestor::td[1][{$this->starts($this->t('Ticket number:'))}]/ancestor::tr[1]", null, true, "/^(.+)\b\s+{$this->opt($this->t('Ticket number:'))}/");

            if (!empty($pax)) {
                $f->addTicketNumber($ticket, true, preg_replace("/\s(?:Mr|Ms)$/", "", $pax));
            } else {
                $f->addTicketNumber($ticket, false);
            }
        }

        $travellers = $accounts = [];
        $travellerValues = $this->http->FindNodes("//tr[ *[1][not(.//tr)]/descendant::img and count(*[normalize-space()])=1 and preceding::tr[ *[normalize-space()][1][{$this->eq($this->t('Passengers'))}] ] and following::tr/*[{$this->eq($this->t('Date'))}]/following-sibling::*[{$this->eq($this->t('Seat'))}] ]");

        foreach ($travellerValues as $tVal) {
            if (preg_match("/^(?<name>{$patterns['travellerName']})(?:\s*\(.[^)(]*\))?\s+{$this->opt($this->t('ffNumber'))}[: ]+(?<account>\d{5,})$/u", $tVal, $m)
                || preg_match("/^(?<name>{$patterns['travellerName']})(?:\s*\(.[^)(]*\))?\s+(?<account>\d{5,})$/u", $tVal, $m)
            ) {
                // Breighner/Jordan Mr (ADT) Saga Blue meðlimur 4925173743    |    Breighner/Jordan Mr (ADT) 4925173743
                $travellers[] = $m['name'];
                $accounts[] = $m['account'];
            } elseif (preg_match("/^({$patterns['travellerName']})(?:\s*\(.[^)(]*\))?$/u", $tVal, $m)) {
                // Breighner/Jordan Mr (ADT)
                $travellers[] = $m[1];
            } else {
                $travellers[] = null;
            }
        }

        if (count($travellers) > 0) {
            $f->general()
                ->travellers(preg_replace('/^(.{2,}?)\s+(?:Mr|Ms)[\s.]*$/i', '$1', $travellers), true);
        }

        if (count($accounts) > 0) {
            $f->program()->accounts($accounts, false);
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ *[7][{$this->eq($this->t('totalPrice'))}] ]/following::tr[normalize-space()][1]/descendant-or-self::tr[ *[7] ][1]/*[7]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // CAD 1,050.04
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//tr[ *[7] and *[1][{$this->eq($this->t('Air fare:'))}] ]/following::tr[normalize-space()][1]/descendant-or-self::tr[ *[7] ][1]/*[1]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $baseFare, $m)) {
                $f->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $extras = $this->http->FindSingleNode("//tr[ *[7] and *[3][{$this->eq($this->t('Extras:'))}] ]/following::tr[normalize-space()][1]/descendant-or-self::tr[ *[7] ][1]/*[3]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $extras, $m)) {
                $feeName = $this->http->FindSingleNode("//tr[ *[7] ]/*[3][{$this->eq($this->t('Extras:'))}]");
                $f->price()->fee(rtrim($feeName, ': '), PriceHelper::parse($m['amount'], $currencyCode));
            }

            $taxes = $this->http->FindSingleNode("//tr[ *[7] and *[5][{$this->eq($this->t('Taxes and fees:'))}] ]/following::tr[normalize-space()][1]/descendant-or-self::tr[ *[7] ][1]/*[5]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $taxes, $m)) {
                $feeName = $this->http->FindSingleNode("//tr[ *[7] ]/*[5][{$this->eq($this->t('Taxes and fees:'))}]");
                $f->price()->fee(rtrim($feeName, ': '), PriceHelper::parse($m['amount'], $currencyCode));
            }
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)([^\s\d]+)(\d{4})$#", //15November2017
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }
}
