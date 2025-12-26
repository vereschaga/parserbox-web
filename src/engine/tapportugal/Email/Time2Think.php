<?php

namespace AwardWallet\Engine\tapportugal\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Time2Think extends \TAccountChecker
{
    public $mailFiles = "tapportugal/it-29830356.eml, tapportugal/it-29870973.eml, tapportugal/it-30000332.eml, tapportugal/it-30233456.eml, tapportugal/it-30270302.eml";

    public $reFrom = ["flytap.com"];
    public $reBody = [
        //        'en' => ['Fares secured', 'Your flights'],
        //        'pt' => ['Tarifas garantidas', 'Seus voos'],
        //        'it' => ['Tariffe bloccate', 'Tuoi voli'],
        //        'es' => ['Tarifas aseguradas', 'Sus vuelos'],
        'en' => ['If you don\'t finish booking your flights in the next 48 hours, your booking will be cancelled',
            'There was a problem with your payment.', 'You have purchased extras for some flights', ],
        'pt' => ['Caso não conclua a reserva dos seus voos nas próximas 48 horas, a sua reserva será cancelada', 'Comprou extras para alguns voos'],
        'it' => ['Se la prenotazione non viene conclusa entro 48 ore sarà cancellata'],
        'es' => ['Si no finaliza su reserva de vuelos en las próximas 48 horas, se cancelará'],
    ];
    public $reSubject = [
        'TAP Time To Think',
    ];
    public static $dict = [
        'en' => [
            'directionFlight' => ['Outbound flight', 'Return flight'],
        ],
        'pt' => [
            'directionFlight'                 => ['Voo de ida', 'Voo de Regresso'],
            'Layover'                         => 'Tempo de ligação',
            'Booking reference'               => ['Referência de reserva', 'Referência da reserva'],
            'will be cancelled'               => 'será cancelada',
            'in the next'                     => 'nas próximas',
            'Passenger name'                  => 'Nome do passageiro',
            'Type'                            => 'Tipo',
            'Total amount for all passengers' => [
                'Valor Total para todos os passageiros',
                'Montante total para todos os passageiros',
            ],
        ],
        'it' => [
            'directionFlight'                 => ['Volo di andata', 'Volo di ritorno'],
            'Layover'                         => 'Durata dello scalo',
            'Booking reference'               => 'Codice prenotazione',
            'will be cancelled'               => 'sarà cancellata',
            'in the next'                     => 'entro',
            'Passenger name'                  => 'Nome del passeggero',
            'Type'                            => 'Tipo',
            'Total amount for all passengers' => 'Costo totale per tutti i passeggeri',
        ],
        'es' => [
            'directionFlight'                 => ['Vuelo de ida', 'Vuelo de vuelta'],
            'Layover'                         => 'Escala',
            'Booking reference'               => 'Referencia de reserva',
            'will be cancelled'               => 'se cancelará',
            'in the next'                     => 'en las próximas',
            'Passenger name'                  => 'Nombre del pasajero',
            'Type'                            => 'Tipo',
            'Total amount for all passengers' => 'Cantidad total para todos los pasajeros',
        ],
    ];
    private $lang = '';
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
//        $this->date = strtotime($parser->getDate());
//        if (!$this->assignLang()) {
//            $this->logger->debug('can\'t determine a language');
//            return $email;
//        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $email->setIsJunk(true);
//        if (!$this->parseEmail($email)) {
//            return null;
//        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.flytap.com/')] | //a[contains(@href,'.flytap.com/')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false
                    && ($fromProv || strpos($headers["subject"], 'TAP') !== false)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference'))}]/following::text()[normalize-space()!=''][1]"))
            ->status($this->http->FindSingleNode("//text()[{$this->contains($this->t('will be cancelled'))}]", null,
                false, "/({$this->opt($this->t('in the next'))}.+)/"))
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Passenger name'))}]/ancestor::tr[{$this->contains($this->t('Type'))}][1]/following-sibling::tr/td[1]"));

        $sum = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total amount for all passengers'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"));
        $f->price()
            ->total($sum['Total'])
            ->currency($sum['Currency']);
        $xpath = "//text()[{$this->contains($this->t('directionFlight'))}]";
        $this->logger->debug("[XPATH]: " . $xpath);
        $nodes = $this->http->XPath->query($xpath);
        $xpathSeg = "following::table[1]/descendant::tr[1]/../tr[not({$this->contains($this->t('Layover'))})]";
        $this->logger->debug("[XPATH-seg]: " . $xpathSeg);

        foreach ($nodes as $i => $root) {
            $date = $this->normalizeDate($this->http->FindSingleNode("./ancestor::tr[1]", $root, false,
                "/(.+),\s*{$this->opt($this->t('directionFlight'))}/"));
            $nodesSeg = $this->http->XPath->query($xpathSeg, $root);

            foreach ($nodesSeg as $j => $rootSeg) {
                $s = $f->addSegment();
                $s->airline()
                    ->noNumber()
                    ->noName();

                $texts = $this->http->FindNodes("./td[2]//text()[normalize-space()!='']", $rootSeg);

                if (count($texts) !== 3) {
                    $this->logger->debug('other segment format - dep ' . $i . '-' . $j);

                    return false;
                }
                $s->departure()
                    ->date(strtotime($texts[0], $date))
                    ->code($texts[1])
                    ->name($texts[2]);
                $texts = $this->http->FindNodes("./td[4]//text()[normalize-space()!='']", $rootSeg);

                if (count($texts) !== 3) {
                    $this->logger->debug('other segment format - arr ' . $i . '-' . $j);

                    return false;
                }
                $s->arrival()
                    ->date(strtotime($texts[0], $date))
                    ->code($texts[1])
                    ->name($texts[2]);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //20 Oct | 17 jun.
            '#^(\d+)\s+(\w+)\.?$#u',
        ];
        $out = [
            '$1 $2 ' . $year,
        ];
        $outWeek = [
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $reBody) {
            if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            if (in_array($this->lang, ['it', 'pt', 'es'])) {
                $tot = PriceHelper::cost($m['t'], '.', ',');
            } else {
                $tot = PriceHelper::cost($m['t']);
            }
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
}
