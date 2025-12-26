<?php

namespace AwardWallet\Engine\lanpass\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TicketInfo extends \TAccountChecker
{
    public $mailFiles = "lanpass/it-61985634.eml, lanpass/it-1.eml, lanpass/it-1976027.eml, lanpass/it-2029923.eml, lanpass/it-2125628.eml, lanpass/it-3839458.eml, lanpass/it-5108747.eml, lanpass/it-5108790.eml, lanpass/it-5108792.eml, lanpass/it-6833090.eml";

    public $reSubject = [
        'es' => ['Confirmacion\s+de\s+reserva', 'Recuerde pagar,\s+su\s+reserva\s+esta\s+por\s+vencer', 'Confirmacion\s+de\s+compra', 'Confirmacion de canje de pasajes'],
        'de' => ['Kaufbestatigung'],
        'en' => ['Reservation\s+confirmation'],
        'pt' => ['Confirmacao\s+de\s+compra', 'Sua compra foi realizada com sucesso'],
    ];

    protected $lang = '';

    protected static $dictionary = [
        'es' => [
            'Reservation code'   => ['Código de reserva', 'Código de Reserva', 'Tu código de reserva es'],
            'Passenger'          => 'Pasajero',
            'Name'               => 'Nombre',
            'Last name'          => 'Apellido',
            'Passengers'         => 'Pasajeros',
            'Total Ticket Price' => ['Total pagado', 'Precio'],
            'Itinerary'          => 'Itinerario',
            'Departure'          => 'Salida',
            'CaptionItinerary'   => 'Revisa el detalle de tus vuelos.',
            'Operated by'        => 'Operado por',
            'para'               => 'para',
            'Flight N°'          => 'Nº Vuelo',
        ],
        'de' => [
            'Reservation code' => 'Ihr Reservierungscode ist',
            'Passenger'        => 'Passagiere:',
            //			'Name' => '',
            //			'Last name' => '',
            'Passengers'         => 'Passagiere:',
            'Total Ticket Price' => 'Preis',
            'Itinerary'          => 'Reiseroute',
            //			'Departure' => '',
            //			'CaptionItinerary' => '',
            'Operated by' => 'Durchgeführt von',
            'Flight N°'   => 'Flug N°',
        ],
        'en' => [
            'Reservation code'   => ['Reservation code', 'Your reservation code is'],
            'Flight N°'          => 'Flight N°',
            'Total Ticket Price' => ['Total Ticket Price', 'Price'],
        ],
        'pt' => [
            'Reservation code'   => ['Seu código de reserva é', 'Código da reserva'],
            'Passenger'          => 'Passageiro',
            'Name'               => ['Name', 'Nome'],
            'Last name'          => ['Last name', 'Sobrenome'],
            'Passengers'         => 'Passageiros',
            'Total Ticket Price' => ['Tarifa'],
            'Itinerary'          => 'Itinerário',
            //			'Departure' => '',
            //			'CaptionItinerary' => '',
            'Operated by' => 'Operado por',
            //			'para' => '',
            'Flight N°' => 'N° Voo',
        ],
    ];

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'LAN.com') !== false
            || strpos($from, 'LATAM') !== false
            || stripos($from, '@bo.lan.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers['from'], 'LAN.com') === false && strpos($headers['from'], 'LATAM') === false && stripos($headers['from'], '@bo.lan.com') === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (preg_match("/{$phrase}/i", $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectPdf($parser)) {
            return false;
        }

        if ($this->http->XPath->query('//node()[contains(.,".lan.com") or contains(.,"LAN.com")] | //a[contains(@href,".lan.com")] | //a[contains(@href,".latam.com")]')->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectPdf($parser)) {
            $this->logger->notice('go to TicketInfoPDF...');

            return false;
        }

        if ($this->assignLang() === false) {
            $this->logger->notice("Can't determine a language!");

            return false;
        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    protected function parseHtml(Email $email)
    {
        $f = $email->add()->flight();
        $rule = $this->contains($this->t('Reservation code'));
        $confNumber = $this->http->FindSingleNode("//text()[{$rule}]/following::text()[normalize-space(.)][1]", null, true, '/([A-Z\d]{5,})/');

        if (empty($confNumber)) {
            $confNumber = $this->http->FindSingleNode("//text()[{$rule}]", null, true, '/([A-Z\d]{5,})/');
        }

        if (!empty($confNumber)) {
            $f->general()
                ->confirmation($confNumber);
        } else {
            $f->general()
                ->noConfirmation();
        }

        // Passengers
        // AccountNumbers
        // TicketNumbers
        $passengers = [];
        $accountNumbers = [];
        $passengersInfoNodes = $this->http->XPath->query("//tr[count(descendant::tr)=0 and {$this->contains($this->t('Name'))} and {$this->contains($this->t('Last name'))}]/following-sibling::tr");

        foreach ($passengersInfoNodes as $n) {
            $passengers[] = $this->http->FindSingleNode("./td[2]", $n) . ' ' . $this->http->FindSingleNode("./td[3]", $n);
            $accountNumbers[] = $this->http->FindSingleNode("./td[5]", $n);
        }
        $passengers = array_filter(array_unique($passengers));

        if (count($passengers) === 0) {
            $passengers = array_filter(array_unique($this->http->FindNodes("//*[normalize-space(.)='" . $this->t('Passenger') . "']/ancestor-or-self::tr[1]/following-sibling::tr/td[1]")));
        }

        if (empty($passengers[0])) {
            $passengers = $this->http->FindNodes('//text()[' . $this->starts($this->t('Passengers')) . ']/following::text()[normalize-space(.)][./following::text()[' . $this->eq($this->t('Total Ticket Price')) . ']]');
        }

        if (count($passengers) > 0) {
            $f->general()
                ->travellers(array_values($passengers));
        }

        $ticketNumbers = array_values(array_unique($this->http->FindNodes("//*[normalize-space(.)='" . $this->t('Passenger') . "']/ancestor-or-self::tr[1]/following-sibling::tr/td[3]")));

        if (count($ticketNumbers) > 0) {
            $f->setTicketNumbers($ticketNumbers, false);
        }

        $accountNumbers = array_values(array_filter(array_unique($accountNumbers)));

        if (count($accountNumbers) > 0) {
            $f->setAccountNumbers($accountNumbers, false);
        }

        // BaseFare
        // Tax
        // TotalCharge
        // Currency
        $nodes = $this->http->FindNodes("//td[normalize-space(.)='" . $this->t('Total') . "']/following-sibling::td[string-length(.)>1]");

        if (count($nodes) === 3) {
            $cost = cost($nodes[0]);
            $tax = cost($nodes[1]);
            $total = cost($nodes[2]);
            $currency = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'" . $this->t('Total') . " (')]", null, true, '/[A-Z]{3}/');
        } elseif (count($nodes) === 4) {
            $cost = cost($nodes[0]);
            $tax = cost($nodes[1]) + cost($nodes[2]);
            $total = cost($nodes[3]);
            $currency = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'" . $this->t('Total') . " (')]", null, true, '/[A-Z]{3}/');
        }

        if (empty($currency)) {
            $paymentTexts = $this->http->FindNodes('//text()[' . $this->eq($this->t('Total Ticket Price')) . ']/following::text()[normalize-space(.)][position()<4]');
            // ï¿½ 169.60 ï¿½ (GBP)
            $payment = trim(preg_replace('/[^\w.,\s()]+/', '', implode(' ', $paymentTexts)));
            // $this->logger->debug($payment);
            // $ 1,010.33 (USD)
            // R$223,24 (BRL)
            if (preg_match('/^([^\d]+)?(\d[,.\d\s]*)\(([A-Z]{3})\)/', $payment, $matches)) {
                $total = $this->normalizeAmount($matches[2]);
                $currency = trim($matches[3]);
            }
        }

        if (!empty($total) && !empty($currency)) {
            $f->price()
                ->total($total)
                ->currency($currency);
        }

        if (!empty($cost)) {
            $f->price()
                ->cost($cost);
        }

        if (!empty($tax)) {
            $f->price()
                ->tax($tax);
        }

        $xpath = "//*[normalize-space(.)='" . $this->t('Itinerary') . "']/following-sibling::table[1]//tr[not(contains(./*[2],'" . $this->t('Departure') . "'))]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $xpath = "//*[normalize-space(.)='" . $this->t('Itinerary') . "']/following-sibling::tr[normalize-space()]";
            $segments = $this->http->XPath->query($xpath);
        }

        if ($segments->length === 0) {
            $xpath = "//*[normalize-space(.)='" . $this->t('CaptionItinerary') . "']/ancestor-or-self::table[1]//tr";
            $segments = $this->http->XPath->query($xpath);
        }

        if ($segments->length === 0) {
            $xpath = "//*[{$this->eq($this->t('Itinerary'))}]/ancestor-or-self::*[ (self::table or self::div) and descendant::text()[{$this->eq($this->t('Departure'))}] ][1]//tr[not({$this->contains($this->t('Departure'))}) and not({$this->contains($this->t('Itinerary'))})]";
            $segments = $this->http->XPath->query($xpath);
        }

        if ($segments->length === 0) {
            $this->logger->debug("segments root not found: $xpath");
        } else {
            $this->logger->debug("segments successed found: $xpath");
        }

        foreach ($segments as $root) {
            $dateFly = $this->normalizeDate($this->http->FindSingleNode("./td[1]", $root));

            if (empty($dateFly)) {
                $dateFly = $this->normalizeDate($this->http->FindSingleNode("./preceding::tr[1]/td[1]", $root));
            }

            if (preg_match("/\d{4}/", $dateFly)) {
                $s = $f->addSegment();
            } elseif (!preg_match("/(\d{2})/", $root->nodeValue)) {
                continue;
            }

            if (!$this->http->FindSingleNode("./td[3]", $root, true, "#\d+:\d+#")) { // old format
                $node = $this->http->FindSingleNode("./td[2]", $root);

                if (preg_match("#(\d+:\d+)#", $node, $m)) {
                    $s->departure()
                        ->date(strtotime($dateFly . ' ' . $m[1]));
                }
                $node = $this->http->FindSingleNode("./td[3]", $root);

                if (preg_match("#(.+?)\s*\(([A-Z]{3})\)#", $node, $m)) {
                    $s->departure()
                        ->name($m[1])
                        ->code($m[2]);
                }
                $node = $this->http->FindSingleNode("./td[4]", $root);

                if (preg_match("#(\d+:\d+)(?:\s*\((\S+)\))?#", $node, $m)) {
                    $s->arrival()
                        ->date(strtotime($dateFly . ' ' . $m[1]));

                    if (isset($m[2]) && !empty($m[2])) {
                        $s->arrival()
                            ->date(strtotime("+1 day", $s->getArrDate()));
                    }
                }
                $node = $this->http->FindSingleNode("./td[5]", $root);

                if (preg_match("#(.+?)\s*\(([A-Z]{3})\)#", $node, $m)) {
                    $s->arrival()
                        ->name($m[1])
                        ->code($m[2]);
                }
                $next = 6;
            } else { // new format
                $node = $this->http->FindSingleNode("./td[normalize-space()][2]", $root);

                if (preg_match("#(\d+:\d+)\s*(.+?)\s*\(([A-Z]{3})\)#", $node, $m)) {
                    $s->departure()
                        ->name($m[2])
                        ->code($m[3])
                        ->date(strtotime($dateFly . ' ' . $m[1]));
                }
                $node = $this->http->FindSingleNode("./td[normalize-space()][3]", $root);

                if (preg_match("#(\d+:\d+)(?:\s*\((\S+)\))?\s*(.+?)\s*\(([A-Z]{3})\)#", $node, $m)) {
                    $s->arrival()
                        ->name($m[3])
                        ->code($m[4])
                        ->date(strtotime($dateFly . ' ' . $m[1]));

                    if (isset($m[2]) && !empty($m[2])) {
                        $s->arrival()
                            ->date(strtotime("+1 day", $s->getArrDate()));
                    }
                }
                $next = 1 + count($this->http->FindNodes("./td[normalize-space()][4]/preceding-sibling::td", $root));
            }
            $node = $this->http->FindSingleNode("./td[{$next}]", $root);

            if (preg_match("#([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)(?:\s*" . $this->t('Operated by') . "\s+(.*))?#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);

                if (isset($m[3]) && !empty($m[3])) {
                    $al = explode(' ' . $this->t("para") . ' ', $m[3]);

                    if (count($al) == 2 && strcasecmp($al[0], $al[1]) == 0) {
                        $s->airline()
                            ->operator($al[0]);
                    } else {
                        $s->airline()
                            ->operator($m[3]);
                    }
                }
            }
            $next++;
            $node = $this->http->FindSingleNode("./td[{$next}]", $root);

            if (empty($node) && count($this->http->FindNodes("./td[normalize-space() = '']", $root)) > 4) {
                $node = $this->http->FindSingleNode("./td[" . ($next + 1) . "]", $root);
            }

            if (preg_match("#(.+?)\s*-\s*([A-Z]{1,2})#", $node, $m)) {
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2]);
            } elseif (isset($s) && !empty($s->getAirlineName()) && preg_match("#^\s*[[:alpha:]]{2,}( [[:alpha:]]{2,})?\s*$#", $node, $m)) {
                $s->extra()
                    ->cabin($node);
            }
        }

        return true;
    }

    protected function assignLang()
    {
        $this->http->FilterHTML = true;
        $this->http->SetEmailBody($this->http->Response['body']);

        foreach (self::$dictionary as $lang => $words) {
            $phrases = (array) $words['Reservation code'];

            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    protected function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    protected function detectPdf(PlancakeEmailParser $parser)
    { // associated with TicketInfoPDF
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($textPdf, 'LATAM AIRLINE') === false && strpos($textPdf, 'LATAM Pass') === false && stripos($textPdf, 'www.latam.com') === false) {
                continue;
            }

            foreach (self::$dictionary as $words) {
                $phrases = (array) $words['Flight N°'];

                foreach ($phrases as $phrase) {
                    if (strpos($textPdf, $phrase) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    protected function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug($str);
        $in = [
            // Tuesday, 11 may 2021ï¿½
            '#^[\w\-]+, (\d+) ([[:alpha:]]+) (\d{4}).*?$#u',
            // Segunda-feira, 7 de setembro de 2020
            '#^[\w\-]+, (\d+) de (\w+) de (\d+)$#u',
            // Quinta-feira 03 dezembro 2020
            '#^[\w\-]+\s+(\d+)\s*(\w+)\s*(\d+)$#u',
            // Saturday 01 november 2014
            '#^\w+ (\d+ \w+ \d{4})$#',
        ];
        $out = [
            '$1 $2 $3',
            "$1 $2 $3",
            "$1 $2 $3",
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }
}
