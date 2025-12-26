<?php

namespace AwardWallet\Engine\lanpass\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TicketInfoPDF extends \TAccountChecker
{
    public $mailFiles = "lanpass/it-126301322.eml, lanpass/it-1903645.eml, lanpass/it-1918640.eml, lanpass/it-1965170.eml, lanpass/it-2.eml, lanpass/it-2101112.eml, lanpass/it-21828103.eml, lanpass/it-2190655.eml, lanpass/it-3395570.eml, lanpass/it-3992399.eml, lanpass/it-4639063.eml, lanpass/it-4677415.eml, lanpass/it-4685433.eml, lanpass/it-564495091.eml, lanpass/it-644243770.eml, lanpass/it-658869129.eml, lanpass/it-6842857.eml, lanpass/it-687610993.eml, lanpass/it-7072085.eml, lanpass/it-7072125.eml, lanpass/it-7091182.eml, lanpass/it-7159189.eml, lanpass/it-7228115.eml, lanpass/it-7340738.eml, lanpass/it-7353553.eml, lanpass/it-7432472.eml, lanpass/it-7672913.eml, lanpass/it-8564487.eml, lanpass/it-887051681.eml";

    protected $subjects = [
        'es' => ['Confirmacion de canje de pasajes', 'Confirmacion de compra'],
        'fr' => ["Confirmation d'achat"],
        'de' => ['Kaufbestatigung'],
        'en' => ['Information about your purchase', 'You purchased your trip to '],
        'pt' => ['Confirmacao de compra'],
    ];

    protected $flightsTH = [
        'es' => 'Itinerario',
        'fr' => 'Itinéraire',
        'de' => ['Flugplan', 'Reiseplan'],
        'en' => 'Itinerary',
        'pt' => 'Itinerário',
    ];

    protected $lang = '';

    protected static $dict = [
        'es' => [
            'Name'                                   => 'Nombre Pasajero',
            'Passenger type'                         => 'Tipo de pasajero',
            'Passenger list'                         => 'Lista de pasajeros',
            'ID'                                     => 'Documento de Identificación',
            'Itinerary'                              => 'Itinerario',
            'Reservation code'                       => 'Código de Reserva',
            'Order No.'                              => 'Nº de orden',
            'Total cost of ticket'                   => 'Total pasaje',
            'Frequent Flyer Nº'                      => 'Nº Pasajero Frecuente',
            'City and Issue date'                    => 'Ciudad y Fecha de emisión',
            'Total paid'                             => ['Total pagado', 'Total pagado *'],
            'Taxes and/or duties'                    => ['Taxes and/or duties', 'Tasas y/o impuestos'],
            'Type'                                   => 'Tipo',
            'Equivalent rate in currency of payment' => 'Equivalente tarifa en moneda de pago',
            'Date'                                   => 'Fecha',
            'Time'                                   => 'Horario',
            'Important information'                  => 'Información importante',
            'Operated by'                            => 'Operado por',
            'Airline details'                        => 'Detalle aerolíneas',
            'Seat'                                   => 'Asiento',
            'Ticket number'                          => 'Número de ticket',
            'Amount'                                 => 'Monto',
            'Detalhe do pagamento'                   => ['Desglose de tu pago', 'Detalle de tu pago'],
        ],
        'de' => [
            'Name'                                   => ['Name des Fluggastes', 'Name des Passagiers'],
            'Passenger type'                         => 'Passagiertyp',
            //'Passenger list' => '',
            'ID'                                     => 'Ausweisdokument',
            'Itinerary'                              => ['Flugplan', 'Reiseplan'],
            'Reservation code'                       => 'Reservierungscode',
            'Total cost of ticket'                   => 'Insgesamt Ticket',
            'Frequent Flyer Nº'                      => 'Vielfliegernummer',
            'City and Issue date'                    => ['Stadt und Ausgabedatum', 'Ort und Datum der Ausstellung'],
            'Total paid'                             => 'Bezahlter Gesamtbetrag',
            'Taxes and/or duties'                    => 'Abgaben bzw. Steuern',
            'Type'                                   => 'Typ',
            'Equivalent rate in currency of payment' => 'Gegenwert des Tarifs in der Zahlungswährung',
            'Date'                                   => 'Datum',
            'Time'                                   => 'Uhrzeit',
            //			'Important information' => '',
            'Operated by'                            => 'Durchgeführt von',
            'Airline details'                        => 'Airline - Details',
            'Seat'                                   => 'Sitzplätze',
            'Ticket number'                          => 'Ticketnummer',
            'Amount'                                 => 'Betrag',
            'Detalhe do pagamento'                   => ['Zahlungsinformation'],
        ],
        'fr' => [
            'Name'                                   => 'Prénom du passager',
            // 'Passenger type'                         => '',
            //'Passenger list' => '',
            // 'ID'                                     => '',
            'Itinerary'                              => 'Itinéraire',
            'Reservation code'                       => 'Code de réservation',
            'Total cost of ticket'                   => 'Total du billet',
            'Frequent Flyer Nº'                      => 'Numéro de programme de fidélisation',
            'City and Issue date'                    => "Ville et Date d'émission",
            'Total paid'                             => 'Total payé',
            //            'Taxes and/or duties'                    => '',
            'Type'                                   => 'Type',
            'Equivalent rate in currency of payment' => 'Equivalent du tarif en espèces',
            'Time'                                   => 'Horaire',
            'Important information'                  => 'Information utileS',
            'Operated by'                            => 'Opéré par',
            'Airline details'                        => 'Détails des compagnies aériennes',
            //'Seat' => '',
            // 'Ticket number' => '',
            // 'Amount' => '',
        ],
        'en' => [
            'Name'             => ['Name', 'Passenger name', 'Passeger Name'],
            'ID'               => ['ID', 'Identification document'],
            'Reservation code' => ['Reservation code', 'Reservation'],
            // 'Ticket number' => '',
            // 'Amount' => '',
            'Detalhe do pagamento' => ['Details of your payment'],
        ],
        'pt' => [
            'Name'                                   => 'Nome do Passageiro',
            'Passenger type'                         => 'Tipo de passageiro',
            //'Passenger list' => '',
            'ID'                                     => 'Documento de Identificação',
            'Itinerary'                              => 'Itinerário',
            'Reservation code'                       => ['Código da reserva', 'Código da'],
            'Order No.'                              => ['Nª de orden'],
            'Total cost of ticket'                   => 'Total passagem',
            'Frequent Flyer Nº'                      => 'N° Passageiro Frequente',
            'City and Issue date'                    => "Cidade e Data de emissão",
            'Total paid'                             => 'Total pago',
            'Taxes and/or duties'                    => 'Taxas e/ou impostos',
            'Type'                                   => 'Tipo',
            'Equivalent rate in currency of payment' => 'Tarifa equivalente em moeda de pagamento',
            'Time'                                   => 'Horário',
            'Important information'                  => 'Information utileS',
            'Operated by'                            => 'Operado por',
            'Airline details'                        => 'Detalhe companhias aéreas',
            'Date'                                   => 'Data',
            'Seat'                                   => 'Assento',
            'points'                                 => 'pontos',
            'Ticket number'                          => 'Número da passagem',
            'Amount'                                 => 'Valor',
        ],
    ];

    /** @var \HttpBrowser */
    protected $pdf;
    protected $YEAR = '';

    protected $date;
    protected $htmlTripSegment;
    private $pdfNamePattern = '.*\.pdf';

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($parser->getHTMLBody()) {
            $values = [];

            foreach ($this->flightsTH as $fTH) {
                $values = array_merge($values, (array) $fTH);
            }
            $this->htmlTripSegment = $this->http->XPath->query('//*[self::h2 or self::h4][' . $this->eq($values) . ']/following-sibling::table[1]/descendant::tr[ ./td[4] ]');
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        $this->pdf = clone $this->http;

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if (
                $this->assignLang($text)
                && !empty($this->flightsTH[$this->lang])
                && $this->containsText($text, $this->flightsTH[$this->lang]) !== false
            ) {
                if (($htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                    $htmlPdf = str_replace([' ', '&#160;', '&nbsp;', '  '], ' ', $htmlPdf);
                    $htmlPdf = str_replace(['<br/>'], ' ', $htmlPdf);
                    $this->pdf->SetEmailBody($htmlPdf);
                }
                $this->parseEmail($email, $text);
            } else {
                continue;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, 'LATAM') !== false)
                && $this->assignLang($text)
                && !empty($this->flightsTH[$this->lang])
                && $this->containsText($text, $this->flightsTH[$this->lang]) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers['from'], 'LAN') === false && stripos($headers['from'], '@bo.lan.com') === false) {
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

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'LAN') !== false
            || stripos($from, '@bo.lan.com') !== false;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function parseEmail(Email $email, string $textPdf)
    {
        if (preg_match_all("/\b(\d{2})\/(\d{2})\/(\d{2})\b/", $textPdf, $m)) {
            if (max(array_map('intval', $m[1])) > 12) {
                $dateFormat = 'FirstDay';
            } elseif (max(array_map('intval', $m[2])) > 12) {
                $dateFormat = 'FirstMonth';
            } else {
                $htmlDates = array_filter($this->http->FindNodes("//text()[contains(., ' 20')]", null,
                    "/^\s*\w+[.,de ]+\w+[.,de ]+20\d{2}\s*$/u"));
                $htmlDates = array_map('strtotime', array_map([$this, 'getEngDate'], $htmlDates));

                $datesMDY = $datesDMY = [];

                foreach ($m[0] as $i => $v) {
                    $datesMDY[] = strtotime($m[2][$i] . '.' . $m[1][$i] . '.20' . $m[3][$i]);
                    $datesDMY[] = strtotime($m[1][$i] . '.' . $m[2][$i] . '.20' . $m[3][$i]);
                }

                $dDatesMDY = count(array_intersect($htmlDates, $datesMDY));
                $dDatesDMY = count(array_intersect($htmlDates, $datesDMY));

                if ($dDatesMDY >= 2 && $dDatesMDY > $dDatesDMY) {
                    $dateFormat = 'FirstMonth';
                } elseif ($dDatesDMY >= 2 && $dDatesDMY > $dDatesMDY) {
                    $dateFormat = 'FirstDay';
                }
            }
        }

        if (empty($dateFormat)) {
            $this->logger->info('check date format');
        }

        // not 100% accurate
        if (empty($dateFormat) && in_array($this->lang, ['en'])) {
            $dateFormat = 'FirstMonth';
        } elseif (empty($dateFormat) && in_array($this->lang, ['pt'])) {
            $dateFormat = 'FirstDay';
        }

        $f = $email->add()->flight();

        // General
        $nodes = array_values(array_unique(array_filter($this->pdf->FindNodes('//b[' . $this->eq($this->t('Reservation code')) . ']/ancestor-or-self::p/following-sibling::p[1]', null, '/^\s*([A-Z\d]{5,})\s*( {3,}|$)/'))));

        foreach ($nodes as $value) {
            $f->general()
                ->confirmation($value, $this->pdf->FindSingleNode('(//b[' . $this->eq($this->t('Reservation code')) . '])[1]'));
        }

        if (empty($nodes)
            && preg_match_all("/\n[ ]{0,3}{$this->opt($this->t('Reservation code'))} +([A-Z\d]{5,7})(?: {2,}|\n)/u", $textPdf, $m)
        ) {
            $nodes = $m[1];

            foreach ($nodes as $value) {
                $f->general()
                    ->confirmation($value, $this->re("/\n[ ]{0,10}({$this->opt($this->t('Reservation code'))}) +[A-Z\d]{5,7}(?: {2,}|\n)/u", $textPdf));
            }
        }

        if (empty($nodes)) {
            $f->general()
                ->confirmation(null);
        }
        $nodes = array_values(array_unique(array_filter($this->pdf->FindNodes('//b[' . $this->eq($this->t('Order No.')) . ']/ancestor-or-self::p/following-sibling::p[1]', null, '/^\s*([A-Z\d]{5,})\s*( {3,}|$)/'))));

        foreach ($nodes as $value) {
            $f->general()
                ->confirmation($value, $this->pdf->FindSingleNode('(//b[' . $this->eq($this->t('Order No.')) . '])[1]'));
        }

        if (empty($nodes)
            && preg_match_all("/[ ]{0,3}{$this->opt($this->t('Order No.'))} +([A-Z\d]{5,})(?: {2,}|\n)/u", $textPdf, $m)
        ) {
            $nodes = $m[1];

            foreach ($nodes as $value) {
                $f->general()
                    ->confirmation($value, $this->re("/[ ]{0,3}({$this->opt($this->t('Order No.'))}) +[A-Z\d]{5,}(?: {2,}|\n)/u", $textPdf));
            }
        }

        $travellers = [];

        $travellersNodes = $this->nextNodes($this->t('Name'));

        if (empty($travellersNodes) || preg_match("/{$this->opt($this->t('Passenger type'))}/u", $travellersNodes[0] ?? '')) {
            $paxText = $this->re("/\n([ ]{0,3}{$this->opt($this->t('Name'))} {2,}.* {2,}{$this->opt($this->t('ID'))}\n*.+)\n+[ ]{0,3}{$this->opt($this->t('Itinerary'))}\n/su", $textPdf);
            $paxTable = $this->splitCols($paxText);
            $travellers = array_filter(explode("\n", preg_replace("/^\s*{$this->opt($this->t('Name'))}\s+/u", "", $paxTable[0])));
            $travellers = array_map('trim', $travellers);
        } else {
            $travellers = $travellersNodes;
        }

        if (count(array_filter($travellers)) === 0) {
            $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Passenger list'))}]/ancestor::table[1]/descendant::text()[normalize-space()][not({$this->contains($this->t('Passenger list'))})]");
        }
        $f->general()
            ->travellers($travellers, true);

        if ($resD = $this->pdf->FindSingleNode('//b[' . $this->eq($this->t('City and Issue date')) . ']/following::text()[normalize-space(.)][1]', null, true, '/(\d{1,2}-[A-Z]{3,}\.?-\d{2,4})/')) {
            $f->general()
                ->date(strtotime($this->getEngDate($resD)));
        }

        // Issued
        $tickets = array_filter(array_unique($this->pdf->FindNodes("//b[normalize-space(.)='" . $this->t('Total cost of ticket') . "']/ancestor-or-self::p/following::p[./b][1]", null, "#^([\d\-]+)$#")));

        if (empty($tickets)) {
            $ticketText = $this->re("/\n([ ]{0,3}{$this->opt($this->t('Ticket number'), false, true)}(?: {3,}.*)?\n\s*[\s\S]*)\n *{$this->opt($this->t('Total paid'))}/u", $textPdf);
            $ticketTable = $this->splitCols($ticketText);
            $ticketCol = $ticketTable[0] ?? '';
            $ticketCol = trim(preg_replace("/^\D*$/um", "", $ticketCol));
            $ticketCol = preg_replace("/^(.*-)$\s*(\d+)$/mu", "$1$2\n", $ticketCol);
            $ticketCol = preg_replace("/(\d+)(\D+)$/mu", "$1\n", $ticketCol);
            $tickets = array_filter(preg_replace("/(\s+)/", "", explode("\n", $ticketCol)));
        }

        $f->issued()
            ->tickets($tickets, false);

        // Program
        $accounts = array_filter($this->nextNodes($this->t('Frequent Flyer Nº'), "/^\w+$/"));

        if (!empty($accounts)) {
            $f->program()
                ->accounts($nodes, false);
        }

        $currency = null;
        $totalCharge = 0.0;

        $totTexts = $this->pdf->FindNodes('(//b[' . $this->eq($this->t('Type')) . ']/ancestor-or-self::p/preceding::p[1])', null, '/[A-Z]{3}\s+[,.\d]+/');

        if (empty($totTexts)) {
            $totTexts = $this->pdf->FindNodes('(//b[' . $this->eq($this->t('Total paid')) . ']/ancestor-or-self::p/following::p[1])[position()<' . (count($tickets) + 1) . ']', null, '/[A-Z]{3}\s+[,.\d]+/');
        }

        if (empty(array_filter($totTexts))) {
            $totTexts = [$this->pdf->FindSingleNode("//b[normalize-space()='Total pago']/following::text()[normalize-space()][1]")];
        }

        if (empty(array_filter($totTexts))) {
            $totTexts = [$this->re("/{$this->opt($this->t('Total paid'))}\s*(\S{1,3}\s*[\d\,\.]+)/", $textPdf)];
        }

        $totValues = array_values(array_filter($totTexts));

        foreach ($totValues as $tot) {
            if (preg_match("#^\s*(?<currency>" . ($currency ?? '\D{1,3}') . ")\s*(?<amount>\d[\d\., ]*)\s*$#", $tot, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>" . ($currency ?? '[A-Z]{3}') . ")\s*$#", $tot, $m)
                || preg_match("#^(?<points>[\d\.]+\s*pontos)\s+[+]\s*(?<currency>\D{1,3})\s+(?<amount>[\d\.\,]+)$#", $tot, $m)
            ) {
                $currency = $this->normalizeCurrency($m['currency']);
                $totalCharge += $this->getSum($m['amount'], $currency);

                if (isset($m['points']) && !empty($m['points'])) {
                    $f->price()
                        ->spentAwards($m['points']);
                }
            } else {
                $totalCharge = 0.0;
            }
        }

        if (!empty($currency)) {
            $f->price()
                ->currency($currency)
                ->total($totalCharge);

            if ($currency === '$' && !empty($this->http->FindSingleNode("//text()[contains(translate(normalize-space(), ',.', ''), '" . preg_replace('/\D+/', '', $totalCharge) . "')]",
                    null, true, "/\bCOP\b/"))) {
                $f->price()
                    ->currency('COP');
            }
        }

        $cost = $this->pdf->FindSingleNode("//p[normalize-space(.)='" . $this->t('Equivalent rate in currency of payment') . "']/ancestor-or-self::p/following-sibling::p[3]");

        if (preg_match("#^\s*(?<currency>" . ($currency ?? '[A-Z]{3}') . ")\s*(?<amount>\d[\d\., ]*)\s*$#", $cost, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>" . ($currency ?? '[A-Z]{3}') . ")\s*$#", $cost, $m)) {
            $currency = $m['currency'];
            $f->price()
                ->cost($this->getSum($m['amount'], $currency));
        } elseif (preg_match("/^\s*(\d[\d\., ]*\s*Pontos)\s*$/i", $cost, $m)) {
            $f->price()
                ->spentAwards($m[1]);
        }

        if ($cost) {
            $cost = $this->pdf->FindSingleNode("//p[normalize-space(.)='" . $this->t('Equivalent rate in currency of payment') . "']/ancestor-or-self::p/following-sibling::p[2]");

            if (preg_match("/^\s*(?<currency>" . ($currency ?? '[A-Z]{3}') . ")\s*(?<amount>\d[\d\., ]*)\s*$/", $cost, $m)
                || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>" . ($currency ?? '[A-Z]{3}') . ")\s*$/", $cost, $m)) {
                $currency = $m['currency'];
                $f->price()
                    ->cost($this->getSum($m['amount'], $currency));
            } elseif (preg_match("#^\s*(\d[\d\., ]*\s*Pontos)\s*$#i", $cost, $m)) {
                $f->price()
                    ->spentAwards($m[1]);
            }
        }

        $tax = $this->pdf->FindSingleNode("//p[{$this->starts($this->t('Taxes and/or duties'))}]/ancestor-or-self::p/following-sibling::p[3]", null, true, "/^(?:\D{1,3})?\s*[\d\.\,]+\s*(?:\D{1,3})?$/");

        if ($tax === null) {
            $tax = $this->pdf->FindSingleNode("//p[{$this->starts($this->t('Taxes and/or duties'))}]/ancestor-or-self::p/following-sibling::p[2]");
        }

        if (preg_match("#^\s*(?<currency>\D{1,3})\s*(?<amount>\d[\d\., ]*)\s*$#", $tax, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>" . ($currency ?? '[A-Z]{3}') . ")\s*$#", $tax, $m)) {
            $currency = $m['currency'];
            $f->price()
                ->tax($this->getSum($m['amount'], $currency));
        }

        $roots = $this->pdf->FindNodes("(//b[normalize-space(.)='" . $this->t('Date') . "'])[last()]/../following-sibling::p[normalize-space(.)='" . $this->t('Time') . "'][1]/following-sibling::p");

        if (!$roots) {
            $roots = $this->pdf->FindNodes("(//b[normalize-space(.)='" . ($this->t('Date') . ' ' . $this->t('Time')) . "'])[last()]/../following-sibling::p");
        }

        if (!$roots) {
            $roots = $this->pdf->FindNodes("(//p[normalize-space(.)='" . $this->t('Date') . "'])[last()]/following-sibling::p[normalize-space(.)='" . $this->t('Time') . "'][1]/following-sibling::p");
        }

        if (!$roots) {
            $this->logger->alert('Segments root not found!');
        }

        // TODO: This is to record the last segment
        $roots[] = "DD 1234";

        $rootsText = implode("\n", $roots);

        $styles = $this->pdf->FindNodes("(//b[normalize-space(.)='" . $this->t('Date') . "'])[last()]/../following-sibling::p[normalize-space(.)='" . $this->t('Time') . "'][1]/following-sibling::p/@style");
        $segs = [];
        $seg = ['DepCode' => TRIP_CODE_UNKNOWN, 'ArrCode' => TRIP_CODE_UNKNOWN];
        $accumulatorStr = '';
        $lastVal = '';
        $tp = 0;

        foreach ($roots as $i => $p) {
            if (strpos($p, $this->t('Important information')) !== false || $this->containsText($p, $this->t('Detalhe do pagamento')) !== false
                || strpos($p, $this->t('Airline details')) !== false
                || strlen($p) > 100) {
                $segs[] = $seg;

                break;
            }

            if ($this->getShift($styles, $i) > 40) {
                switch ($lastVal) {
                    case 'fl':
                        if (preg_match('/' . $this->opt($this->t('Operated by')) . '\s*(.+)/i', $accumulatorStr, $m)) {
                            $s->airline()
                                ->operator(trim($m[1]));
                        }
                        $accumulatorStr = '';
                        $lastVal = 'oper';

                        break;

                    case 'oper':
                        if (empty($s->getDepName())) {
                            $s->departure()
                                ->name(trim($accumulatorStr));
                        }
                        $accumulatorStr = '';
                        $lastVal = 'DepName';

                        break;

                    case 'DepName':
                        if (empty($s->getArrName())) {
                            $s->arrival()
                                ->name(trim($accumulatorStr));
                        }
                        $accumulatorStr = '';
                        $lastVal = 'ArrName';

                        break;
                }
            }

            if (preg_match('/^([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d{1,5})\b/u', $p, $m)) {
                if ($p === "DD 1234") {
                    break;
                }
                $this->logger->debug('New seg.');
                $s = $f->addSegment();

                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
                $lastVal = 'fl';
                $accumulatorStr = '';

                if (preg_match('/' . $m[1] . '\s*' . $m[2] . '(.+?\d{1,2}:\d{2}.+?)(?:\s+[A-Z\d]{2}\s*\d+\s+|$)/s', $rootsText, $airportMatches)) {
                    // DepName
                    // ArrName
                    if (preg_match('/AIRLINES\s+(?<depName>.+?\s+INTL)\s+(?<arrName>.+?\s+INTL)\s+/is', $airportMatches[1], $matches)) { // it-2190655.eml
                        $s->departure()
                            ->name(preg_replace('/\s+/', ' ', $matches['depName']));
                        $s->arrival()
                            ->name(preg_replace('/\s+/', ' ', $matches['arrName']));
                    }
                    // Cabin
                    // BookingClass
                    $cabinVariants = ['Premium\s*Business', 'Premium\s*Economy', 'Business', 'Economy', 'Économique'];

                    if (preg_match('/\s+(?<cabin>' . implode('|', $cabinVariants) . ')\s+-\s+(?<bookingClass>[A-Z]{1,2})\s+/i', $airportMatches[1], $matches)) { // it-2190655.eml
                        $s->extra()
                            ->cabin(preg_replace('/\s+/', ' ', $matches['cabin']))
                            ->bookingCode($matches['bookingClass']);
                    } elseif (preg_match('/\s+(?<cabin>' . implode('|', $cabinVariants) . ')/i', $airportMatches[1], $matches)) { // it-2190655.eml
                        $s->extra()
                            ->cabin(preg_replace('/\s+/', ' ', $matches['cabin']));
                    }
                }

                continue;
            }

            if ($lastVal === 'fare') {
                $lastVal = 'seats';

                continue;
            }

            if ($lastVal === 'cabin' && preg_match('/[A-Z\d]+/', $p)) {
                $lastVal = 'fare';

                continue;
            }

            if ($lastVal === 'arrTime' && preg_match('/(\w+)\s+-\s+([A-Z])/', $p, $m)) {
                if (empty($s->getCabin())) {
                    $s->extra()
                        ->cabin($m[1]);
                }

                if (empty($s->getBookingCode())) {
                    $s->extra()
                        ->bookingCode($m[2]);
                }
                $lastVal = 'cabin';

                continue;
            }

            if (preg_match('/(\d{1,2}:\d{2}(?:\s*[AP]M|\s*[ap]\. ?m\.)?)/i', $p, $m) && (!isset($seg['DepDate']) || !isset($seg['ArrDate']))) {
                $m[1] = preg_replace("/([ap])\. ?\m\.\s*$/i", '$1m', $m[1]);

                if ($lastVal === 'depDate') {
                    $s->departure()
                        ->date(strtotime($this->getEngDate($this->date) . ' ' . $m[1]));
                    $lastVal = 'depTime';
                } else {
                    $s->arrival()
                        ->date(strtotime($this->getEngDate($this->date) . ' ' . $m[1]));
                    $lastVal = 'arrTime';

                    if (!empty($this->htmlTripSegment) && !empty($s->getDepDate()) && !empty($s->getArrDate())) {
                        foreach ($this->htmlTripSegment as $subroot) {
                            if ($s && $this->http->XPath->query("./td[contains(.,'" . date("H:i", $s->getDepDate()) . "')]", $subroot)->length > 0
                                && $this->http->XPath->query("./td[contains(.,'" . date("H:i", $s->getArrDate()) . "')]", $subroot)->length > 0
                            ) {
                                $htmlDataArr = array_values(array_filter($this->http->FindNodes('./td', $subroot, "#(.+\([A-Z]{3}\))#")));

                                if (isset($htmlDataArr[0]) && isset($htmlDataArr[1])) {
                                    if (preg_match("#.+\(([A-Z]{3})\)#", $htmlDataArr[0], $m)) {
                                        $s->departure()
                                            ->code($m[1]);
                                    }

                                    if (preg_match("#.+\(([A-Z]{3})\)#", $htmlDataArr[1], $m)) {
                                        $s->arrival()
                                            ->code($m[1]);
                                    }
                                }
                            }
                        }
                    }
                }

                continue;
            }

            if (preg_match('/\s*(\d{1,2}-[A-Z]{3,}\.?-\d{2,4})/', $p, $m)) {
                if ($lastVal === 'depTime') {
                    $this->date = $m[1];
                    $lastVal = 'arrDate';
                } else {
                    $this->date = $m[1];
                    $lastVal = 'depDate';
                }

                continue;
            } elseif (preg_match('/\s*\b(\d{1,2})\s*[\/\-\.]\s*(\d{2})\s*[\/\-\.]\s*(\d{2})\b/', $p, $m)) {
                if ($dateFormat === 'FirstMonth') {
                    if ($lastVal === 'depTime') {
                        $this->date = $m[2] . '.' . $m[1] . '.20' . $m[3];
                        $lastVal = 'arrDate';
                    } else {
                        $this->date = $m[2] . '.' . $m[1] . '.20' . $m[3];
                        $lastVal = 'depDate';
                    }
                } else {
                    if ($lastVal === 'depTime') {
                        $this->date = $m[1] . '.' . $m[2] . '.20' . $m[3];
                        $lastVal = 'arrDate';
                    } else {
                        $this->date = $m[1] . '.' . $m[2] . '.20' . $m[3];
                        $lastVal = 'depDate';
                    }
                }

                continue;
            }

            if (isset($s) && !empty($s->getAirlineName()) && !empty($s->getFlightNumber())
                && empty($s->getDepCode())
                && empty($s->getArrCode())
                && !empty($node = $this->http->FindSingleNode("//text()[contains(.,'{$s->getAirlineName()} {$s->getFlightNumber()}')]/preceding::text()[normalize-space()!=''][2]",
                    null, false, "#^\s*([A-Z]{3} *\- *[A-Z]{3})\s*$#"))
            ) {
                $codes = array_map("trim", explode('-', $node));
                $s->departure()
                    ->code($codes[0]);
                $s->arrival()
                    ->code($codes[1]);
            }
            $accumulatorStr .= ' ' . trim($p);
        }

        // Seats
        foreach ($f->getSegments() as $i => $s) {
            if (!empty($s->getAirlineName()) && !empty($s->getFlightNumber())
                && empty($s->getDepCode())
                && empty($s->getArrCode())
                && !empty($s->getDepName())
                && !empty($s->getArrName())
            ) {
                $s->departure()
                    ->noCode();
                $s->arrival()
                    ->noCode();
            }

            $seatArray = [];
            $seats = [];

            if (!empty($s->getAirlineName()) && !empty($s->getFlightNumber())) {
                $rule = $this->eq([$s->getAirlineName() . $s->getFlightNumber(), $s->getAirlineName() . ' ' . $s->getFlightNumber()]);
                $seats = $this->pdf->FindNodes('//text()[' . $rule . ']/following::text()[normalize-space(.)][position()>6 and position()<10]', null, '/^(\d{1,2}[A-Z].*)(?:\s*\-|$)/');

                if (count(array_filter($seats)) == 0) {
                    $seats = $this->pdf->FindNodes('//text()[' . $rule . ']/following::text()[normalize-space(.)][position()>6 and position()<25]', null, '/^(\d{1,2}[A-Z].*)(?:\s*\-|$)/');
                }
            }

            foreach ($seats as $seat) {
                if (stripos($seat, ' - ') !== false) {
                    $array = explode(" - ", $seat);
                    $seatArray = array_merge($array, $seatArray);
                } else {
                    $seatArray[] = $seat;
                }
            }

            $seatsValues = array_unique(array_values(array_filter($seatArray)));

            foreach ($seatsValues as $i => $v) {
                if (!preg_match("/^\s*(\d{1,3}[A-Z])\s*$/", $v)) {
                    unset($seatsValues[$i]);
                }
            }

            if (!empty($seatsValues[0])) {
                $s->extra()
                    ->seats($seatsValues);
            }
        }

        return $email;
    }

    protected function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    protected function nextNodes($s, $r = null)
    {
        return array_unique($this->pdf->FindNodes("//b[" . $this->eq($s) . "]/ancestor-or-self::p/following::p[1]", null, $r));
    }

    protected function getShift($styles, $i)
    {
        //position:absolute;top:680px;left:71px;white-space:nowrap
        if (isset($styles[$i]) && preg_match("#left:(\d+)px#", $styles[$i], $m)) {
            if (isset($styles[$i - 1]) && preg_match("#left:(\d+)px#", $styles[$i - 1], $m2)) {
                return abs($m[1] - $m2[1]);
            }
        }

        return 1000;
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    protected function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function getEngDate($dateStr)
    {
        if ($this->lang === 'en') {
            return $dateStr;
        }

        if (preg_match('/(?<day>\b\d+)-(?<mon>[[:alpha:]]{3,})\.?-(?<year>\d{2,4}\b)/iu', $dateStr, $m)
            || preg_match('/^\s*(?<day>\d+)\s*( de |\s)\s*(?<mon>[[:alpha:]]{3,})\.?\s*( de |\s)\s*(?<year>\d+)\s*$/iu', $dateStr, $m)
            // Jun 02, 2025
            || preg_match('/^\s*(?<mon>[[:alpha:]]{3,})\s+(?<day>\d{1,2})\s*,\s*(?<year>\d{4})\s*$/i', $dateStr, $m)
        ) {
            $month = $m['mon'];

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $m['day'] . ' ' . $month . ((strlen($m['year']) === 2) ? ' 20' . $m['year'] : ' ' . $m['year']);
        } else {
            return $dateStr;
        }
    }

    private function getSum($total, $currency)
    {
        $total = PriceHelper::parse($total, $currency);

        if (is_numeric($total)) {
            return (float) $total;
        }

        return null;
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $phrases) {
            if (!empty($phrases['Name']) && $this->containsText($body, $phrases['Name']) !== false
                && (!empty($phrases['Reservation code']) && $this->containsText($body, $phrases['Reservation code']) !== false
                    || !empty($phrases['City and Issue date']) && $this->containsText($body, $phrases['City and Issue date']) !== false
                    || !empty($phrases['Reservation code']) && preg_match("/\n[ ]{0,3}{$this->opt($phrases['Reservation code'], false, true)}/u", $body)
                    || !empty($phrases['City and Issue date']) && preg_match("/\n[ ]{0,3}{$this->opt($phrases['City and Issue date'], false, true)}/u", $body)
                )
            ) {
                $this->lang = $lang;

                return true;
            }
        }
        // $this->logger->debug('$body = ' . print_r($body, true));

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeCurrency($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $currences = [
            'BRL' => ['R$'],
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
        ];
        $string = trim($string);

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function opt($field, $space = false, $nextLineWord = false)
    {
        $field = (array) $field;

        $result = array_map(function ($s) use ($space, $nextLineWord) {
            $s = preg_quote($s);

            if ($nextLineWord) {
                $s = str_replace(' ', '(?: |(?: {2,}.*)?\n(?: {20}.*\n){0,1} {0,10})', $s);
            } elseif ($space) {
                $s = str_replace(' ', '\s+', $s);
            }

            return $s;
        }, $field);

        return '(?:' . implode("|", $result) . ')';
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($this->inOneRow($rows[0] . "\n" . ($rows[1] ?? '') . "\n" . ($rows[2] ?? '')));
        }

        if (isset($pos[0]) && $pos[0] !== 0) {
            $pos[0] = 0;
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
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
