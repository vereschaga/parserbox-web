<?php

namespace AwardWallet\Engine\itaairways\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "itaairways/it-171920377.eml, itaairways/it-172238444.eml, itaairways/it-202721870.eml, itaairways/it-249243446.eml";
    public $subjects = [
        // en
        'Summary of your booking',
        // it
        'Riepilogo della tua prenotazione',
        // fr
        'Récapitulatif de votre réservation',
        // pt
        'Resumo de sua reserva',
        // nl
        'Uw selectie',
    ];

    public $detectLang = [
        'en' => ['Your flights'],
        'it' => ['I tuoi voli', 'i tuoi voli'],
        'fr' => ['Vos vols'],
        'pt' => ['Seus voos'],
        'nl' => ['Uw vluchten'],
    ];

    public $lang = '';

    public static $dictionary = [
        "en" => [
            'operatedBy' => 'Operated by',
            'ticket'     => ['Ticket No.', 'ticket no.'],
        ],
        "it" => [
            'Your flights'        => ['I tuoi voli', 'i tuoi voli'],
            'Your booking number' => 'Il tuo codice di prenotazione (PNR) è:',
            'Departure'           => 'Partenza',
            'operatedBy'          => 'Operato da',
            'Total Paid'          => 'Totale Pagato',
            'Taxes'               => 'Tasse',
            'Fare'                => 'Tariffa',
            'ticket'              => ['N. biglietto', 'N° Biglietto'],
            'Seat'                => 'Posto',
            'Duration'            => 'Durata',
        ],
        "fr" => [
            'Your booking number'   => 'Votre numéro de réservation (PNR) est:',
            'Your flights'          => 'Vos vols',
            'Departure'             => 'Départ',
            'operatedBy'            => 'Opéré par',
            'Duration'              => 'Durée',
            'ticket'                => 'n° billet',
            //            'Seat'                  => 'Posto',
            'Taxes'                 => 'Taxes',
            'Fare'                  => 'Tarif',
            'Total Paid'            => 'Montant payé',
        ],
        "pt" => [
            'Your booking number'   => 'Seu número de reserva (PNR) é:',
            'Your flights'          => 'Seus voos',
            'Departure'             => 'Partida',
            'operatedBy'            => 'Operado por',
            'Duration'              => 'Duration',
            'ticket'                => 'nº do bilhete',
            'Seat'                  => 'Assento',
            'Taxes'                 => 'Impostos',
            'Fare'                  => 'Tarifa',
            'Total Paid'            => 'Total pago',
        ],
        "nl" => [
            'Your booking number'   => 'Uw boekingscode (PNR) is:',
            'Your flights'          => 'Uw vluchten',
            'Departure'             => 'Vertrek',
            'operatedBy'            => 'Uitgevoerd door',
            'Duration'              => 'Duur',
            'ticket'                => 'ticketnr.',
            'Seat'                  => 'Stoel',
            'Taxes'                 => 'Heffingen',
            'Fare'                  => 'Tarief',
            'Total Paid'            => 'In totaal betaald',
        ],
    ];

    private $patterns = [
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@ita-airways.com') !== false) {
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
        $this->assignLang();

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'ITALIA TRASPORTO AEREO S.P.A.')]")->length > 0
        || $this->http->XPath->query("//img[contains(@src, 'www.ita-airways.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('operatedBy'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]ita\-airways\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';

        $f = $email->add()->flight();

        $f->general()->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Your booking number'))}]/following::text()[normalize-space()][1]"));

        $segments = $this->http->XPath->query("//tr[ *[1][{$xpathTime}] and *[2][{$this->starts($this->t('Duration'))} or normalize-space()=''] and *[3][{$xpathTime}] ]");

        $segType = '';

        if ($segments->length > 0) {
            $segType = 'A';
            $this->parseSegmentsA($f, $segments, $parser);
        }

        if ($segments->length === 0) {
            $segments = $this->http->XPath->query("//tr[ descendant-or-self::tr[*[1][{$xpathTime}] and *[last()][{$xpathTime}]] and preceding-sibling::tr[normalize-space()][2]/descendant-or-self::tr[ *[normalize-space()][1][string-length(normalize-space())=3] and *[normalize-space()][last()][string-length(normalize-space())=3] ] ]/ancestor::table[1]");

            if ($segments->length > 0) {
                $segType = 'B';
                $this->parseSegmentsB($f, $segments);
            }
        }

        // it-202721870.eml
        $travellers = array_filter($this->http->FindNodes("//tr[ (count(*[normalize-space()])=2 or count(*[normalize-space()])=3) and *[normalize-space()][2][{$this->eq($this->t('ticket'))}] ]/*[normalize-space()][1]", null, "/^{$this->patterns['travellerName']}$/"));

        if (count($travellers) === 0 && empty($this->http->FindSingleNode("(//tr[*[{$this->eq($this->t('ticket'))}]])[1]"))) {
            // it-171920377.eml
            $travellers = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('ticket'))}]/preceding::text()[normalize-space()][not(contains(normalize-space(),'loyalty'))][2]", null, "/^{$this->patterns['travellerName']}$/"));
        }

        $f->general()->travellers(array_unique($travellers), true);

        // it-202721870.eml
        $tickets = array_filter($this->http->FindNodes("//tr[(count(*[normalize-space()])=2 or count(*[normalize-space()])=3) and *[normalize-space()][2][{$this->eq($this->t('ticket'))}] ]/following-sibling::tr[normalize-space()][1]/*[normalize-space()][2]", null, "/^{$this->patterns['eTicket']}$/"));

        if (count($tickets) === 0) {
            // it-171920377.eml
            $tickets = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('ticket'))}]", null, "/{$this->opt($this->t('ticket'))}[:\s]*({$this->patterns['eTicket']})$/"));
        }

        $f->setTicketNumbers(array_unique($tickets), false);

        $accountNumbers = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('programma.loyalty.tier.'))}]", null, "/{$this->opt($this->t('programma.loyalty.tier.'))}\s*([\d\s]{5,})$/"));
        $f->setAccountNumbers(array_unique($accountNumbers), false);

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Total Paid'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match("/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)/", $totalPrice, $matches)) {
            // USD 2 106,36
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $taxes = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Taxes'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)/', $taxes, $m)) {
                $f->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
            }
            $fNodes = $this->http->XPath->query("//tr[*[{$this->eq($this->t('Taxes'))}]]/following::tr[ count(*[normalize-space()])=2][not({$this->contains($this->t('Total Paid'))}) and following::tr[{$this->starts($this->t('Total Paid'))}]]");

            foreach ($fNodes as $fRoot) {
                $name = $this->http->FindSingleNode("*[normalize-space()][1]", $fRoot);
                $amount = $this->http->FindSingleNode("*[normalize-space()][2]", $fRoot, true, "/.*\d.*/");

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)/', $amount, $m)) {
                    if (preg_match("/^\s*\d.*\b{$this->opt($this->t('Fare'))}\b/", $name)) {
                        if (!$email->getPrice() || ($email->getPrice() && !$email->getPrice()->getCost())) {
                            $f->price()
                                ->cost(PriceHelper::parse($m['amount'], $currencyCode));
                        } else {
                            $f->price()
                                ->cost($email->getPrice()->getCost() + PriceHelper::parse($m['amount'], $currencyCode));
                        }
                    } else {
                        $f->price()->fee($name, PriceHelper::parse($m['amount'], $currencyCode));
                    }
                }
            }
        }

        $email->setType('YourBooking' . $segType . ucfirst($this->lang));

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

    private function parseSegmentsA(Flight $f, \DOMNodeList $segments, PlancakeEmailParser $parser): void
    {
        // examples: it-202721870.eml

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $operatedBy = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]", $root);

            if (preg_match("/^{$this->opt($this->t('operatedBy'))}\s*(?<operator>.{2,}?)\s*\|\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $operatedBy, $m)) {
                // Operated by ITA Airways | AZ718
                $s->airline()->operator($m['operator'])->name($m['name'])->number($m['number']);
            }

            $dateVal = $this->normalizeDate($this->http->FindSingleNode("preceding-sibling::tr[{$this->starts($this->t('Departure'))}][1]", $root, true, "/{$this->opt($this->t('Departure'))}\s+(.*\d.*)$/"));
            $date = EmailDateHelper::calculateDateRelative($dateVal, $this, $parser, '%D% %Y%');
            $timeDep = $this->http->FindSingleNode("*[normalize-space()][1]", $root, true, "/^{$this->patterns['time']}$/");
            $timeArr = $this->http->FindSingleNode("*[normalize-space()][last()]", $root, true, "/^{$this->patterns['time']}$/");
            $s->departure()->date(strtotime($timeDep, $date));
            $s->arrival()->date(strtotime($timeArr, $date));

            $duration = $this->http->FindSingleNode("*[normalize-space()][2]", $root, true, "/{$this->opt($this->t('Duration'))}\s+(\d[\d hm]+)$/i");
            $s->extra()->duration($duration);

            $nameDep = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1][count(*[normalize-space()])>1]/*[normalize-space()][1]", $root);

            if (preg_match("/^(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/", $nameDep, $m)) {
                $s->departure()->name($m['name'])->code($m['code']);
            } else {
                $s->departure()->name($nameDep);
            }

            $nameArr = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1][count(*[normalize-space()])>1]/*[normalize-space()][last()]", $root);

            if (preg_match("/^(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/", $nameArr, $m)) {
                $s->arrival()->name($m['name'])->code($m['code']);
            } else {
                $s->arrival()->name($nameArr);
            }
        }
    }

    private function parseSegmentsB(Flight $f, \DOMNodeList $segments): void
    {
        // examples: it-171920377.eml, it-172238444.eml

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("descendant::tr[{$this->contains($this->t('operatedBy'))}]/following::tr[contains(normalize-space(), ':')][1]/preceding::text()[normalize-space()][2]", $root);

            if (preg_match('/\b(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)\b/', $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $s->airline()->operator($this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('operatedBy'))}][1]/following::text()[normalize-space()][1]", $root));

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("descendant::tr[{$this->contains($this->t('operatedBy'))}]/following::tr[contains(normalize-space(), ':')][1]/descendant::text()[normalize-space()][2]", $root)));
            $depTime = $this->http->FindSingleNode("descendant::tr[{$this->contains($this->t('operatedBy'))}]/following::tr[contains(normalize-space(), ':')][1]/descendant::text()[normalize-space()][1]", $root);
            $arrTime = $this->http->FindSingleNode("descendant::tr[{$this->contains($this->t('operatedBy'))}]/following::tr[contains(normalize-space(), ':')][1]/descendant::text()[normalize-space()][2]/following::td[normalize-space()][1]", $root);

            $s->departure()
                ->code($this->http->FindSingleNode("descendant::tr[{$this->contains($this->t('operatedBy'))}]/following::tr[normalize-space()][1]/descendant::td[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root))
                ->date(strtotime($depTime, $date));

            $s->extra()->cabin($this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('operatedBy'))}][1]/preceding::text()[normalize-space()][1]", $root));

            $meal = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Meal'))}]/following::text()[normalize-space()][1]", $root);
            $s->extra()->meal($meal, false, true);

            $seats = $this->http->FindNodes("descendant::text()[{$this->eq($this->t('Seat'))}]/following::text()[normalize-space()][1]", $root);

            if (count($seats) > 0) {
                $s->extra()->seats($seats);
            }

            if (preg_match("/^([\d\:]+\s*A?P?M)\s*([+]\d+)\w+$/", $arrTime, $m)) {
                $s->arrival()->date(strtotime($m[2] . ' days', strtotime($m[1], $date)));
            } else {
                $s->arrival()->date(strtotime($arrTime, $date));
            }
            $s->arrival()->code($this->http->FindSingleNode("descendant::tr[{$this->contains($this->t('operatedBy'))}]/following::tr[normalize-space()][1]/descendant::td[normalize-space()][1]/descendant::text()[normalize-space()][2]", $root));
        }
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str): string
    {
        $in = [
            '/^(\d{1,2})[-.\s]*([[:alpha:]]+)[-.\s]*(\d{4})$/u', //03Lug2022
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match('/^\d{1,2}\s+([[:alpha:]]+)(?:\s+\d{4}|$)/u', $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
