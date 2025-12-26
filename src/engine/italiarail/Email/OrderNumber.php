<?php

namespace AwardWallet\Engine\italiarail\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OrderNumber extends \TAccountChecker
{
    public $mailFiles = "italiarail/it-235024320.eml, italiarail/it-240213966.eml";
    public $subjects = [
        'Your ItaliaRail order number is ',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'YOUR E-TICKET(S)' => ['YOUR E-TICKET(S)', 'Your e-Ticket(s)'],
            'ORDER #'          => ['ORDER #', 'ORDER#', 'Order number'],
            'Lead Passenger:'  => ['Lead Passenger:', 'Lead passenger:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ((isset($headers['from']) && stripos($headers['from'], '@italiarail.com') !== false)
            || (!empty($headers['subject']) && stripos($headers['subject'], 'ItaliaRail') !== false)) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'info@italiarail.com')]")->length > 0
            || $this->http->XPath->query("//a[contains(@href, 'italiarail.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('ORDER #'))}]")->length > 0
                && ($this->http->XPath->query("//text()[{$this->contains($this->t('SEAT ASSIGNMENT'))}]")->length > 0
                    || $this->http->XPath->query("//text()[{$this->contains($this->t('YOUR E-TICKET(S)'))}]")->length > 0
                    || $this->http->XPath->query("//text()[{$this->contains($this->t('Early-bird ticket'))}]")->length > 0);
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]italiarail\.com$/', $from) > 0;
    }

    public function ParseEmail(Email $email)
    {
        $t = $email->add()->train();

        $travellers = array_unique($this->http->FindNodes("//text()[normalize-space()='PASSENGER NAME(S)']/ancestor::tr[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), ')'))]"));

        if (count($travellers) == 0) {
            $travellers = array_unique($this->http->FindNodes("//text()[{$this->contains($this->t('YOUR E-TICKET(S)'))}]/following::text()[normalize-space()='Class:']/preceding::text()[normalize-space()][1]",
                null, "/^[[:alpha:] \-]+$/u"));
        }

        if (empty($travellers) && empty($this->http->FindSingleNode("(//text()[contains(normalize-space(), 'PASSENGER NAME(S)')])[1]"))) {
            $travellers[] = $this->http->FindSingleNode("//text()[normalize-space() = 'Lead Passenger:']/following::text()[normalize-space()][1]");
        }

        if (count(array_filter($travellers)) === 0) {
            $travellers = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Class:')]/ancestor::table[1]/descendant::text()[starts-with(normalize-space(), 'Adult') or starts-with(normalize-space(), 'Child')]/following::text()[normalize-space()][1]");
        }

        $t->general()
            ->travellers($travellers, true);

        $pnr = array_unique($this->http->FindNodes("//text()[{$this->starts($this->t('Lead Passenger:'))}]/preceding::text()[normalize-space()][1]", null, "/^([A-Z\d]+)$/"));

        if (isset($pnr[0]) && strlen($pnr[0]) < 3) {
            $pnr = array_unique($this->http->FindNodes("//text()[starts-with(normalize-space(),'Lead Passenger:')]/preceding::text()[normalize-space()][1]/ancestor::div[1]", null, "/^([A-Z\d]+)$/"));
        }

        if (count($pnr) == 1) {
            $t->general()
                ->confirmation($pnr[0], 'PNR');
        } elseif (count($pnr) > 1) {
            foreach ($pnr as $conf) {
                $t->general()
                    ->confirmation($conf, 'PNR');
            }
        } elseif (count($pnr) == 0) {
            $t->general()->noConfirmation();
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Total']/following::text()[string-length()>3][1]", null, true, "/^\s*\D*\s*([\d\,\.]+)/u");
        $currency = $this->http->FindSingleNode("//text()[normalize-space()='Total']/following::text()[string-length()>3][1]", null, true, "/^\s*(\D*)\s*([\d\,\.]+)/u");

        if (!empty($total) && !empty($currency)) {
            $currency = $this->normalizeCurrency($currency);
            $t->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Subtotal']/following::text()[string-length()>3][1]", null, true, "/^\s*\D*\s*([\d\,\.]+)/");

            if (!empty($cost)) {
                $t->price()
                    ->cost($cost);
            }

            $fee = $this->http->FindSingleNode("//text()[normalize-space()='Booking fee']/following::text()[string-length()>3][1]", null, true, "/^\s*\D*\s*([\d\,\.]+)/");

            if (!empty($fee)) {
                $t->price()
                    ->fee('Processing Fee', $fee);
            }
        }

        $tickets = array_unique($this->http->FindNodes("//text()[normalize-space()='TICKET NUMBER(S)']/ancestor::tr[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), ')'))]"));

        if (count($tickets) == 0) {
            $tickets = array_filter(array_unique($this->http->FindNodes("//text()[normalize-space()='Ticket:']/following::text()[normalize-space()][1]", null, "/^(\d{7,})$/")));
        }

        if (count($tickets) > 0) {
            $t->setTicketNumbers($tickets, false);
        }

        $nodes = $this->http->XPath->query("//img[contains(@src, 'mcauto')]/ancestor::table[normalize-space()][1][contains(., '20')][following::tr[normalize-space()][1][.//img]][not(contains(normalize-space(), 'Seat:'))]");
        // [contains(., '20')] - contains year

        if ($nodes->length === 0) {
            $nodes = $this->http->XPath->query("//text()[normalize-space()='Your PNR']/preceding::text()[normalize-space()]/ancestor::table[normalize-space()][1][contains(., '20')][following::tr[normalize-space()][1][.//img]][not(contains(normalize-space(), 'Seat:'))]");
        }

        foreach ($nodes as $root) {
            $segmentText = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));

            $s = $t->addSegment();

            $depText = $this->http->FindSingleNode(".", $root);

            if (preg_match("/^(?<name>\D+(?:T(?<depTerminal>\d+))?)\s+[·]\s+(?<date>.+)\s+[·]\s+(?<time>[\d\:]+)\s*[A-Z]{3}/u", $depText, $m)
            || preg_match("/^(?<name>.+)\s+(?<date>[A-z]{2,}\s*\d+\,\s*\d{4})\s*(?<time>[\d\:]+)\s*[A-Z]{3}$/u", $depText, $m)) {
                $s->departure()
                    ->date(strtotime($m['date'] . ', ' . $m['time']))
                    ->name($m['name'])
                    ->geoTip('Europe');
            }

            $arrText = $this->http->FindSingleNode("./following::tr[contains(normalize-space(), ':')][1]", $root);

            if (preg_match("/^(?<name>\D+)\s+[·]\s+(?<date>.+)\s+[·]\s+(?<time>[\d\:]+)\s*[A-Z]{3}/u", $arrText, $m)
                || preg_match("/^(?<name>.+)\s+(?<date>[A-z]{2,}\s*\d+\,\s*\d{4})\s*(?<time>[\d\:]+)\s*[A-Z]{3}$/u", $arrText, $m)
                || (preg_match("/^(?<name>[^·]+)\s+[·]\s+(?<date>.+)\s+[·]\s+(?<time>[\d\:]+)\s*[A-Z]{3}/u", $arrText, $m) && strlen(preg_replace("/\D+/", '', $m['name'])) < 2)
            ) {
                // Malpensa Aeroporto (MXP) T1 · May 30, 2023 · 11:46 CET
                // Milano Porta Garibaldi · May 30, 2023 · 11:05 CET
                $s->arrival()
                    ->date(strtotime($m['date'] . ', ' . $m['time']))
                    ->name($m['name'])
                    ->geoTip('Europe')
                ;
            }

            $cabin = array_unique(array_filter($this->http->FindNodes("./following::text()[normalize-space()='SEAT ASSIGNMENT'][1]/ancestor::tr[1]/descendant::text()[normalize-space()='Carriage:' or normalize-space()='CARRIAGE']/following::text()[normalize-space()][2][not(contains(normalize-space(), 'Seat'))]", $root)));

            if (count($cabin) == 1) {
                $s->extra()
                    ->cabin($cabin[0]);
            }

            $trainInfo = $this->http->FindSingleNode("./following::tr[contains(normalize-space(), '·')][1]", $root);

            if (preg_match("/^(?<type>[\w\s]+)[·]\s*(?<number>[\dA-Z]+)\s*[·]\s*(?<duration>[\d\shm]+)$/u", $trainInfo, $m)) {
                $s->setNumber($m['number']);

                $s->extra()
                    ->duration($m['duration']);

                $s->setServiceName($m['type']);
            } else {
                $trainInfo = $this->http->FindSingleNode("./following::text()[string-length()>5][1]", $root);

                if (preg_match("/^(?<type>\w+)[\s\·]+(?<number>[\dA-Z]+)[\s\·]+(?<duration>[\d\shm]+)$/", $trainInfo, $m)) {
                    $s->setNumber($m['number']);

                    $s->extra()
                        ->duration($m['duration']);

                    $s->setServiceName($m['type']);
                }
            }

            $carNumber = [];
            $seats = [];
            $cabin = [];

            $notLead = "[not(preceding::text()[normalize-space()][1][normalize-space()='Lead passenger:'])]";

            foreach ($travellers as $traveller) {
                $seats[] = $this->http->FindSingleNode("./following::text()[normalize-space()='{$traveller}']{$notLead}[1]/ancestor::table[1]/descendant::text()[normalize-space()='Seat:']/following::text()[normalize-space()][2]", $root, true, "/^(\d+\D?)$/");
                $cabin[] = $this->http->FindSingleNode("./following::text()[normalize-space()='{$traveller}']{$notLead}[1]/ancestor::table[1]/descendant::text()[normalize-space()='Class:']/following::text()[normalize-space()][1]", $root);
                $carNumber[] = $this->http->FindSingleNode("./following::text()[normalize-space()='{$traveller}']{$notLead}[1]/ancestor::table[1]/descendant::text()[normalize-space()='Seat:']/following::text()[normalize-space()][1]", $root, true, "/^(\d+\D?)$/");
            }

            $s->setSeats(array_filter(array_unique($seats)));

            if (count(array_filter(array_unique($cabin))) > 0) {
                $s->setCabin(implode(', ', array_filter(array_unique($cabin))));
            }

            if (count(array_filter(array_unique($carNumber))) > 0) {
                $s->setCarNumber(implode(', ', array_filter(array_unique($carNumber))));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $otaConf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('ORDER #'))}]", null, true, "/{$this->opt($this->t('ORDER #'))}\s*([A-Z\d\-]{10,})\b/");

        if (empty($otaConf)) {
            $otaConf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('ORDER #'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d\-]{10,})\s*$/");
        }

        if (empty($otaConf)) {
            $otaConf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('ORDER #'))}]", null, true, "/{$this->opt($this->t('ORDER #'))}\s*([A-Z\d\-]{10,})\b/");
        }

        if (!empty($otaConf)) {
            $email->ota()->confirmation($otaConf, 'ORDER #');
        }

        $this->ParseEmail($email);

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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        $in = [
            //November 05, 2021 16:18 CET
            '#^(\w+)\s*(\d+)\,\s*(\d{4})\s*([\d\:]+)\s*[A-Z]+$#i',
        ];
        $out = [
            '$2 $1 $3, $4',
        ];
        $str = preg_replace($in, $out, $date);

        return strtotime($str);
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.'],
            'USD' => ['US$'],
            'CAD' => ['CA$'],
            'AUD' => ['A$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
