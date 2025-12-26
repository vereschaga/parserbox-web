<?php

namespace AwardWallet\Engine\perimeter\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationPDF extends \TAccountChecker
{
    public $mailFiles = "perimeter/it-274948594.eml, perimeter/it-280503883.eml, perimeter/it-282762053.eml, perimeter/it-413927819.eml, perimeter/it-414230136.eml, perimeter/it-418063264.eml, perimeter/it-420944877.eml, perimeter/it-429189713.eml, perimeter/it-461049558.eml, perimeter/it-654636830.eml";

    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public $code;
    public $text;

    public $detectLang = [
        "fr" => ["Itinéraire"],
        "en" => ["Itinerary"],
    ];

    public static $dictionary = [
        "en" => [
            'passengersStart'          => ['Passengers', 'Passenger Details', 'Main Passenger'],
            'passengersEnd'            => ['Flight Itinerary', 'Agency', 'Itinerary', 'Passenger(s)'],
            'headerName'               => ['Name', 'Name(s)'],
            'headerInfant'             => ['Infant', 'Infant(s)'],
            'headerFFNumber'           => ['Isaruuk Number'],
            'headerTicket'             => ['Ticket'],
            'Reservation Number'       => ['Reservation Number', 'Reservation #', 'Booking Reference:'],
            'priceStart'               => ['Purchase Summary', 'Fare Details', 'Charges', 'Charge Summary'],
            'priceEnd'                 => ['Payment Information', 'Payment Details', 'Payments', 'Reservation Info', 'Payment Summary'],

            // 'Passenger' => '', // заголовок колонки в таблице с ценой
            'baseFare-single'          => 'Total Charges',
            'baseFareStart'            => ['Fare Amount', 'Amount'],
            'baseFareEnd'              => ['Surcharges', 'Tax', 'Total'],
            'surchargesStart'          => ['Surcharges'],
            'surchargesEnd'            => ['Tax', 'Total'],

            'taxStart'                 => ['Taxes', 'Tax'],
            'taxEnd'                   => ['Total'],
            'segmentEnd'               => ['All charges and payments appear in:', 'flight numbers operated by'],
            'Total'                    => ['Total Amount', 'Total'],
        ],
        "fr" => [
            'passengersStart' => ['Passagers'],
            'passengersEnd'   => ['Agence', 'Itinéraire'],
            'headerName'      => ['Nom'],
            'headerInfant'    => ['Bébé'],
            // 'headerFFNumber' => [''],
            'headerTicket'                        => ['Billet'],
            'Itinerary'                           => 'Itinéraire',
            'Leg'                                 => ['Étape', 'Etape'],
            'Date'                                => 'Date',
            'Aircraft'                            => 'Avion',
            'From'                                => 'De',
            'To'                                  => 'À',
            'Flight'                              => 'Vol',
            'Seat'                                => 'Siège',
            'Passenger'                           => 'Passager',
            'Reservation Number'                  => 'Réservation #',
            'Name:'                               => 'Nom:',
            'Email:'                              => 'Courriel:',
            'All charges and payments appear in:' => ['Tous les charges et paiements apparaissent en:', 'Tous les frais et paiements apparaissent en:'],
            'priceStart'                          => ['Frais'],
            'priceEnd'                            => ['Paiements', 'Paiment(s)', 'Paiement(s)'],
            'Price'                               => 'Frais',
            'Total'                               => 'Total',
            'baseFare-single'                     => 'Montant',
            // 'baseFareStart'            => '',
            // 'baseFareEnd'              => '',
            // 'surchargesStart'          => '',
            // 'surchargesEnd'            => '',
            'taxStart'                            => ['Montant'],
            'taxEnd'                              => ['Total'],
            'segmentEnd'                          => ['Tous les charges et paiements apparaissent en:', 'Tous les frais et paiements apparaissent en:'],
        ],
    ];

    private static $providers = [
        'calmair' => [
            'from' => ['@calmair.com'],
            'subj' => [
                'en'  => 'Calm Air Itinerary',
                'en2' => 'Calm Air Ticketless Itinerary',
            ],
            'body' => [
                'www.calmair.com',
            ],
        ],
        'pascan' => [
            'from' => ['@pascan.com'],
            'subj' => [
                'en' => 'Pascan',
            ],
            'body' => [
                'choisi Pascan',
                'pascan.com',
            ],
        ],
        'perimeter' => [
            'from' => ['@perimeter.ca'],
            'subj' => [
                'en' => 'Perimeter - RESERVATION #',
            ],
            'body' => [
                'Perimeter Aviation',
            ],
        ],
        'islandairx' => [
            'from' => ['@IslandAirX.com'],
            'subj' => [
                'en' => 'Island Air Express - RESERVATION #',
            ],
            'body' => [
                'Island Air Express',
            ],
        ],
        'wasaya' => [
            'from' => ['@wasaya.com'],
            'subj' => [
                'en' => 'Wasaya Airways - Reservation #',
            ],
            'body' => [
                'Wasaya',
            ],
        ],
    ];

    private static $companies = [
        'Pacific Coastal Airlines' => [
            'from' => ['@pacificcoastal.com'],
            'subj' => [
                'en'  => 'Pacific Coastal Airlines - Reservation',
            ],
            'body' => [
                'Pacific Coastal customers have the option',
            ],
        ],
        'PAL Airlines' => [
            'from' => ['@palairlines.ca'],
            'subj' => [
                'en'  => 'PAL Airlines - Reservation',
            ],
            'body' => [
                'For PAL Airlines checked baggage rules',
                'customer.service@PALairlines.ca',
            ],
        ],
        'Air Creebec' => [
            'from' => ['@aircreebec.ca'],
            'subj' => [
                'en'  => 'Air Creebec - RESERVATION',
            ],
            'body' => [
                'www.aircreebec.ca',
                'Thank you for booking with AirCreebec',
            ],
        ],
        'Wilderness Seaplanes' => [
            'from' => ['@flywildnerness.ca'],
            'subj' => [
                'en'  => 'Wilderness - RESERVATION',
            ],
            'body' => [
                'www.wildernessseaplanes.com',
            ],
        ],
        'Air Liaison' => [
            'from' => ['@airliaison.ca'],
            'subj' => [
                'en'  => 'Air Liaison - ',
            ],
            'body' => [
                'www.airliaison.ca',
            ],
        ],
        'Air Inuit' => [
            'from' => ['@airinuit.com'],
            'subj' => [
                'en'  => 'Air Inuit - ',
            ],
            'body' => [
                'www.airinuit.com',
            ],
        ],
        'Air Saint-Pierre' => [
            'from' => ['@airsaintpierre.com'],
            'subj' => [
                'en'  => 'AirSaintPierre - RESERVATION #',
            ],
            'body' => [
                'WWW.AIRSAINTPIERRE.COM',
            ],
        ],
        'Central Mountain Air' => [
            'from' => ['@flycma.com'],
            'subj' => [
                'en'  => 'Central Mountain Air - RESERVATION #',
            ],
            'body' => [
                'Thank you for choosing Central Mountain Air',
            ],
        ],
    ];

    private $patterns = [
        'time'           => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'date'           => '\b[-[:alpha:]]+\s*\d{1,2}\s*[[:alpha:]]+\s*\d{4}\b', // Friday 21 July 2023
        'travellerName'  => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'travellerName2' => '[[:upper:]][-,\/\'’[:upper:] ]*[[:upper:]]', // BUCHANAN,PATRICIA ANNE
        'eTicket'        => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$providers as $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                return true;
            }
        }

        foreach (self::$companies as $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (!isset($pdfs[0])) {
            $text = $parser->getBodyStr();
            $this->assignLang($text);

            if ($this->http->XPath->query("//text()[normalize-space()='Itinerary']/ancestor::tr[1]/following::tr[starts-with(normalize-space(), 'LegDateFlightAircraftFromToStatus')]/following-sibling::tr")->length > 0) {
                return true;
            }

            if ($this->http->XPath->query("//text()[normalize-space()='Itinerary']/ancestor::tr[1]/following::tr[starts-with(normalize-space(), 'Leg') and contains(normalize-space(), 'Date') and contains(normalize-space(), 'Flight')]/following-sibling::tr")->length > 0) {
                return true;
            }

            return false;
        }
        $pdf = $pdfs[0];

        if (empty($text = \PDF::convertToText($parser->getAttachmentBody($pdf)))) {
            return false;
        }

        $this->assignLang($text);

        if (null === $this->getProvider($parser, $text) && null === $this->getCompanies($parser, $text)) {
            return false;
        } elseif (strpos($text, $this->t('Itinerary')) !== false && preg_match("/\n[ ]*{$this->opt($this->t('Leg'))}[ ]+\S/", $text)) {
            return true;
        } elseif (strpos($text, $this->t('Flight Itinerary')) !== false && preg_match("/\n[ ]*{$this->opt($this->t('Flight'))}[ ]*{$this->opt($this->t('From'))}[ ]*{$this->opt($this->t('To'))}[ ]*/", $text)) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $detects) {
            foreach ($detects['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParseFlightHTML(Email $email): void
    {
        $this->logger->debug(__METHOD__);
        $f = $email->add()->flight();

        $conf = $this->http->FindSIngleNode("//text()[starts-with(normalize-space(), 'Reservation') and contains(normalize-space(), '#')]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{6})$/");

        if (empty($conf)) {
            $conf = $this->http->FindSIngleNode("//text()[{$this->starts($this->t('Reservation Number'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{6})$/");
        }

        $travellers = $this->http->FindNodes("//text()[normalize-space()='Passengers']/ancestor::tr[1]/following-sibling::tr[not(contains(normalize-space(), 'Leg'))]/descendant::td[2][not(contains(normalize-space(), 'Name'))]");
        $f->general()
            ->travellers(array_unique(str_replace(['/YKT'], '', $travellers)))
            ->confirmation($conf);

        $tickets = $this->http->FindNodes("//text()[{$this->starts($this->t('Ticket Number'))}]/preceding::text()[normalize-space()][1]", null, "/^(\d{9,})$/");

        if (count($tickets) > 0) {
            $f->setTicketNumbers(array_unique(array_filter($tickets)), false);
        }

        $currency = '';

        if ($this->http->XPath->query("//tr[contains(normalize-space(), 'All charges and payments appear in: CAD')]/ancestor::tr[1]")->length > 0) {
            $currency = 'CAD';
        }
        $total = $this->http->FindSingleNode("//text()[normalize-space()='Total Amount']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Total Amount'))}\s*\D*\s*([\d\.\,]+)/");

        if (!empty($total) && !empty($currency)) {
            $f->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);

            $tax = $this->http->FindSingleNode("//tr[starts-with(normalize-space(), 'Total')]/descendant::td[1][normalize-space()='Total']/following::td[2]", null, true, "/^\D*\s*([\d\.\,]+)/");

            if (!empty($tax)) {
                $f->price()
                    ->tax(PriceHelper::parse($tax, $currency));
            }

            $cost = [];
            $feeNodes = $this->http->XPath->query("//text()[normalize-space()='Charges']/ancestor::tr[1]/following-sibling::tr[not(contains(normalize-space(), 'Leg') or contains(normalize-space(), 'Total'))]");

            foreach ($feeNodes as $feeRoot) {
                $feeName = $this->http->FindSingleNode("./descendant::td[3]", $feeRoot);
                $feeSum = $this->http->FindSingleNode("./descendant::td[4]", $feeRoot, true, "/^\D*([\d\.\,]+)$/");

                if (preg_match("/^[A-Z\d\s]+\s+\-\s+[A-Z\d\s]+$/", $feeName) || preg_match("/^[A-Z]\d+\s+\-\s+/", $feeName)) {
                    $cost[] = str_replace(',', '', $feeSum);
                } else {
                    $f->price()
                        ->fee($feeName, PriceHelper::parse($feeSum, $currency));
                }
            }

            if (count($cost) > 0) {
                $f->price()
                    ->cost(array_sum($cost));
            }
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Itinerary']/ancestor::tr[1]/following::tr[starts-with(normalize-space(), 'LegDateFlightAircraftFromToStatus')]/following-sibling::tr");

        if ($nodes->length === 0) {
            $nodes = $this->http->XPath->query("//text()[normalize-space()='Itinerary']/ancestor::tr[1]/following::tr[starts-with(normalize-space(), 'Leg') and contains(normalize-space(), 'Date')]/following-sibling::tr");
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->http->FindSingleNode("./descendant::td[2]", $root, true, "/^\s*([\d\/]+)\s*$/u");

            $airlineInfo = $this->http->FindSingleNode("./descendant::td[3]", $root);

            if (preg_match("/^(?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<flightNumber>\d{1,5})\s*Operated By\s*(?<operator>.+)$/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['airlineName'])
                    ->number($m['flightNumber']);

                if (!empty($m['operator'])) {
                    $m['operator'] = trim($m['operator']);

                    if (strcasecmp('Perimeter', $m['operator']) == 0) {
                        $s->airline()->carrierName('Perimeter Aviation');
                    } else {
                        $s->airline()->operator($m['operator']);
                    }
                }
            }

            $aircraft = $this->http->FindSingleNode("./descendant::td[4]", $root);

            if (!empty($aircraft)) {
                $s->extra()
                    ->aircraft($aircraft);
            }

            $depInfo = $this->http->FindSingleNode("./descendant::td[5]", $root);

            if (preg_match("/^(?<depTime>[\d\:]+)[\s\-]+(?<depName>.+)$/", $depInfo, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($date . ', ' . $m['depTime']))
                    ->name($m['depName'])
                    ->noCode();
            }

            $arrInfo = $this->http->FindSingleNode("./descendant::td[6]", $root);

            if (preg_match("/^(?<arrTime>[\d\:]+)[\s\-]+(?<arrName>.+)$/", $arrInfo, $m)) {
                $s->arrival()
                    ->date($this->normalizeDate($date . ', ' . $m['arrTime']))
                    ->name($m['arrName'])
                    ->noCode();
            }

            $status = $this->http->FindSingleNode("./descendant::td[7]", $root, true, "/((?:CONFIRMED))/");

            if (!empty($status)) {
                $s->extra()
                    ->status($status);
            }
        }
    }

    public function ParseFlightPDF(Email $email, $text): void
    {
        $f = $email->add()->flight();

        // Confirmation
        if (preg_match("/(?:^\s*|\n[ ]*|[ ]{2})({$this->opt($this->t('Reservation Number'), true)})[*:\s]*\b([A-Z\d]{5,})(?:[ ]*\/|[ ]{2}|\n)/", $text, $m)) {
            $f->general()->confirmation($m[2], preg_replace('/\s+/', ' ', rtrim($m[1], ': ')));
        } else {
            $f->general()
                ->noConfirmation();
        }

        $travellersFromPrice = [];

        // *********** Price ***********
        // it-420944877.eml
        $currencyCode = $this->re("/{$this->opt($this->t('All charges and payments appear in:'))}\s*([A-Z]{3})\n/", $text);

        $priceText = $this->re("/\n[ ]*{$this->opt($this->t('priceStart'))}(?: {5,}[[:alpha:] ]+)?\n+(.+?)\n[ ]*{$this->opt($this->t('priceEnd'))}(?:\n|$)/su", $text);
        //$this->logger->debug('$priceText = '. "\n" . print_r( $priceText,true));

        // Price (Type 1 - В виде таблицы с именем пассажира)
        // Leg      Passenger             Description                              Amount          GST        Total
        // 1        BLACK, REBECCA        B - CLASSIC FARE                        $290.00       $14.50      $304.50
        // 1        BLACK, REBECCA        Fuel Surcharge                           $25.00        $1.25       $26.25
        // 1        BLACK, REBECCA        Nav Canada Fee                           $17.00        $0.85       $17.85
        // 1        BLACK, REBECCA        Carbon Surcharge                         $18.40        $0.92       $19.32
        //                                                        Total           $350.40       $17.52      $367.92
        // Как собирать: из всех строк найти строки относящиеся к cost и собрать значение из столбца Amount: cost = 290.00(строка B - CLASSIC FARE),
        // остальные строки собрать в fee и так же использовать цены из Amount: ['Fuel Surcharge', 25.00], ['Nav Canada Fee', 17.00], ['Carbon Surcharge', 18.40],
        // Из последней строки собрать последнее значение в тотал: total = 367.92,
        // и так же из последней строки собрать налоги из стоблцов между Amount и Total: tax = 17.52 (если между ними несколько колонок собирать их сумму)

        if (preg_match("/^(?<head>.* {2,}(?:{$this->opt($this->t('Passenger'))}|{$this->opt($this->t('Amount'))}|{$this->opt($this->t('Total'))})(?: {2,}.+)?)\n(?<body>[\s\S]+?)\n+ {30,}{$this->opt($this->t('Total'))}/mu", $priceText, $m)) {
            // нормализация случаев когда таблица продолжается на следующей странице
            $bodyRowsNew = [];
            $bodyRows = explode("\n", $m['body']);
            $maxLineLength = mb_strlen($m['head']);

            foreach ($bodyRows as $bRow) {
                if (mb_strlen($bRow) < $maxLineLength / 2) {
                    $bodyRowsNew[] = $bRow;

                    continue;
                }

                $attemptsCount = 100;
                $patternCurrencyAmount = "(?:[^,.‘\'\-\d)(]{1,5} ?)?\d[,.‘\'\d]*"; // $1,321.76

                for ($i = 0; $i < $attemptsCount; $i++) {
                    if (mb_strlen($bRow) < $maxLineLength) {
                        // растягиваем строку из середины вправо до величины $maxLineLength
                        $bRow = preg_replace("/^(.+?\S [ ]*?)((?:[ ]{1,14}{$patternCurrencyAmount}){1,7})$/u", '$1 $2', $bRow);
                    } else {
                        break;
                    }
                }

                for ($i = 0; $i < $attemptsCount; $i++) {
                    if (mb_strlen($bRow) > $maxLineLength) {
                        // стягиваем строку от правого края к середине до величины $maxLineLength
                        $bRow = preg_replace("/^(.+?\S [ ]*?) ((?:[ ]{1,14}{$patternCurrencyAmount}){1,7})$/u", '$1$2', $bRow);
                    } else {
                        break;
                    }
                }

                $bodyRowsNew[] = $bRow;
            }

            if (count($bodyRowsNew) > 0) {
                $m['body'] = implode("\n", $bodyRowsNew);
            }
            // конец нормализации

            $priceTableText = $m['body'];
            $tableColumnPosition = $this->rowColumnPositions($this->inOneRow($m['head'] . "\n" . $m['body']));

            $tableHeaders = $this->createTable($m['head'], $tableColumnPosition);

            foreach ($tableHeaders as $i => $col) {
                if ((preg_match("/^(\S.+ )?(?:{$this->opt($this->t('Passenger'))}|{$this->opt($this->t('Description'))})(\S.+ )?$/", $col, $mat)
                    && (!empty($mat[1]) || !empty($mat[2])))
                ) {
                    // правка разбиения на колонки, если между колонками один пробел, а не два
                    $tableHeaderPositionTemp = $this->rowColumnPositions($this->inOneRow($col), '\s+');
                    $tableColumnPositionTemp = $this->rowColumnPositions($this->inOneRow($m['head'] . "\n" . $m['body']), '\s+');

                    if (count($tableHeaderPositionTemp) == 2) {
                        for ($j = 0; $j < 15; $j++) {
                            if (($tableColumnPosition[$i] + $tableHeaderPositionTemp[1] - $j) <= $tableColumnPosition[$i]
                                || isset($tableColumnPosition[$i + 1]) && ($tableColumnPosition[$i] + $tableHeaderPositionTemp[1] + $j) >= $tableColumnPosition[$i + 1]
                            ) {
                                break;
                            }

                            if (in_array($tableColumnPosition[$i] + $tableHeaderPositionTemp[1] - $j, $tableColumnPositionTemp)) {
                                $tableColumnPosition[] = $tableColumnPosition[$i] + $tableHeaderPositionTemp[1] - $j;
                                sort($tableColumnPosition);
                                $tableHeaders = $this->createTable($m['head'], $tableColumnPosition);

                                break;
                            } elseif (in_array($tableHeaderPositionTemp[1] + $j, $tableColumnPositionTemp)) {
                                $tableColumnPosition[] = $tableColumnPosition[$i] + $tableHeaderPositionTemp[1] + $j;
                                sort($tableColumnPosition);
                                $tableHeaders = $this->createTable($m['head'], $tableColumnPosition);

                                break;
                            }
                        }
                    }
                }
            }

            $table = [];
            $rows = $this->split("/\n( {0,5}\S.+)/", "\n\n" . $priceTableText);

            foreach ($rows as $row) {
                $table[] = array_map('trim', $this->createTable($row, $tableColumnPosition));
            }
            //$this->logger->debug('$table = ' . "\n" . print_r($table, true));

            $passengerPos = $descriptionPos = null;

            foreach ($tableHeaders as $i => $col) {
                if (preg_match("/^{$this->opt($this->t('Passenger'))}$/", $col)) {
                    $passengerPos = $i;
                }

                if (preg_match("/^{$this->opt($this->t('Description'))}$/", $col)) {
                    $descriptionPos = $i;
                }
            }

            if ($passengerPos === null || $descriptionPos === null) {
                // Для случаев колонок без заголовков
                $pp = $dp = [];

                for ($i = 0; $i <= count($tableHeaders); $i++) {
                    $column = array_column($table, $i);

                    if (count($table) == count(array_filter($column, function ($v) {
                        return preg_match("/^\s*[A-Z][A-Z\s\-]+,\s*[A-Z][A-Z\s\-]+$/", $v) ? true : false;
                    }))) {
                        $pp[] = $i;

                        continue;
                    }

                    if (preg_match("/\b(?:Fee|Surcharge|Tax)\b/mi", implode("\n", $column))) {
                        $dp[] = $i;

                        continue;
                    }
                }

                if ($passengerPos == null && count($pp) == 1) {
                    $passengerPos = $pp[0];
                }

                if ($descriptionPos == null && count($dp) == 1) {
                    $descriptionPos = $dp[0];
                }
            }

            if ($passengerPos !== null && !empty($descriptionPos) && count($tableHeaders) >= ($descriptionPos + 1)) {
                $travellersFromPrice = array_column($table, $passengerPos);
                $allFees = [];
                $feeTravellers = [];
                $allCost = null;

                foreach ($table as $row) {
                    $feeName = preg_replace('/\s+/', ' ', $row[$descriptionPos]);
                    $feeValue = PriceHelper::parse($this->re("/^\D{0,5}(\d[\d\.\,]+)\D{0,5}?(?: {2,}|$)/", $row[$descriptionPos + 1]), $currencyCode);
                    $uniqTrav = implode('', array_slice($row, 0, $passengerPos + 1));
                    $feeTravellers[$uniqTrav] = $feeTravellers[$uniqTrav] ?? [];
                    // Условие для выбора cost срели fee
                    if (preg_match("/^[A-Z\d]+[A-Z\d\- \+]*\-(?:[A-Z\d\- \+]+| Refundable)$/", $feeName)) {
                        $feeTravellers[$uniqTrav][] = $feeName;
                        $allCost = ($allCost ?? 0.0) + $feeValue;

                        if (count($feeTravellers[$uniqTrav]) > 1 && preg_match("/^\s+$/", $row[0])) {
                            $allFees = [];
                            $allCost = null;

                            break;
                        }
                    } else {
                        $allFees[$feeName] = ($allFees[$feeName] ?? 0.0) + $feeValue;
                    }
                }

                if (!empty($allFees) && !empty($allCost)) {
                    $f->price()->cost($allCost);

                    foreach ($allFees as $name => $value) {
                        $f->price()
                            ->fee($name, $value);
                    }
                } elseif ($allCost !== null) {
                    $f->price()->cost($allCost);
                }

                if (preg_match("/\n[ ]{5,}{$this->opt($this->t('Total'))}[ ]{2,}(([^\-\d)(]{0,5})[ ]?\d[\d.,]*([ ]{2,}(?:\\2)?[ ]?\d[\d.,]*)*)(?:\n|$)/", $priceText, $matches)
                ) {
                    // первое значение пропускаем, последнее Total, между ними суммируем в налог
                    $values = preg_split("/\s{2,}/", $matches[1]);

                    if (count($values) > 2) {
                        array_shift($values);
                        $values = array_values($values);

                        $tax = null;

                        foreach ($values as $i => $value) {
                            if (preg_match("/^\s*(?<currency>[^\-\d)(]+?)?[ ]*(?<total>\d[\d.,]*)\s*$/", $value, $matches)) {
                                if ($i === count($values) - 1) {
                                    $f->price()
                                        ->currency($currencyCode ?? $matches['currency'])
                                        ->total(PriceHelper::parse($matches['total'], $currencyCode));
                                } else {
                                    $tax = ($tax ?? 0.0) + PriceHelper::parse($matches['total'], $currencyCode);
                                }
                            } else {
                                $tax = null;
                            }
                        }

                        if ($tax !== null) {
                            $f->price()->tax($tax);
                        }
                    }
                }
            }
        } elseif (preg_match("/^[ ]*{$this->opt($this->t('Total'))}[ ]+(?<currency>\D{1,3}?)[ ]*(?<amount>\d[\d.,]*)$/m", $priceText, $matches)) {
            // Price (Type 2 - В виде отдельных строк)
            // it-429189713.eml
            $f->price()
                ->currency($currencyCode ?? $matches['currency'])
                ->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $cost = $this->re("/^[ ]*{$this->opt($this->t('baseFare-single'))}[ ]{2,}(.*\d.*)$/mu", $priceText)
                ?? $this->re("/^[ ]*{$this->opt($this->t('baseFareStart'), true)}\n+.+[ ]{4}(.*\d.*)\n+[ ]*{$this->opt($this->t('baseFareEnd'))}/mu", $priceText)
            ;

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[\d.,]*)$/', $cost, $m)) {
                $f->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feeRows = [];

            $surchargesText = $this->re("/^[ ]*{$this->opt($this->t('surchargesStart'))}(?:[ ]{2}.+)?\n+(.+[ ]{2,}[ ]{2,}[\s\S]+?)\n+[ ]*{$this->opt($this->t('surchargesEnd'))}(?:[ ]{2}|$)/mu", $priceText);
            $surchargesRows = preg_split('/\n+/', $surchargesText, -1, PREG_SPLIT_NO_EMPTY);

            if (count($surchargesRows) > 0) {
                $feeRows = array_merge($feeRows, $surchargesRows);
            }

            $taxText = $this->re("/^[ ]*{$this->opt($this->t('taxStart'))}(?:[ ]{2}.+)?\n+(.+[ ]{2,}[ ]{2,}[\s\S]+?)\n+[ ]*{$this->opt($this->t('taxEnd'))}(?:[ ]{2}|$)/mu", $priceText);

            if (empty($taxText)) {
                $taxText = $this->re("/[ ]*{$this->opt($this->t('taxStart'))}((?:.+\n){1,5}){$this->opt($this->t('taxEnd'))}/mu", $priceText);
                $taxText = preg_replace("/(\n)(\s+\D[\d\.\,]+)/", "$2", $taxText);
            }

            $taxRows = preg_split('/\n+/', $taxText, -1, PREG_SPLIT_NO_EMPTY);

            if (count($taxRows) > 0) {
                $feeRows = array_merge($feeRows, $taxRows);
            }

            foreach ($feeRows as $fRow) {
                if (preg_match("/^[ ]*(?<name>\S.*?\S)[ ]{2,}(?<charge>.*\d.*)$/", $fRow, $m)) {
                    if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[\d.,]*)$/', $m['charge'], $m2)) {
                        $f->price()->fee($m['name'], PriceHelper::parse($m2['amount'], $currencyCode));
                    }
                }
            }
        }

        // *********** Travellers ***********
        /* Travellers (v1 - таблица до сегментов перелетов): it-461049558.eml, it-282762053.eml, it-420944877.eml */
        $travellers = $infants = $ffNumbers = $tickets = [];
        $passengerDetails = $this->re("/\n[ ]*{$this->opt($this->t('passengersStart'))}(?:[ ]{2}[^\n]+)?\n+(.+?)\n+[ ]*{$this->opt($this->t('passengersEnd'))}(?:[ ]{2}|\n)/s", $text);

        if (preg_match("/^(?<thead>.+)\n+(?<tbody>[\s\S]+)$/", $passengerDetails, $matches)) {
            $tablePos = $this->rowColsPos($matches['thead']);

            if (count($tablePos) === 4 && preg_match("/^(.+\S)([ ]{2,}){$this->opt($this->t('headerTicket'))}$/", $matches['thead'], $m)) {
                // it-461049558.eml
                $tablePos[3] = mb_strlen($m[1]) + ceil(strlen($m[2]) / 2);
            }

            $headers = $this->splitCols($matches['thead'], $tablePos);
            $passengerRows = preg_split('/\n+/', $matches['tbody']);

            foreach ($passengerRows as $pRow) {
                $table = $this->splitCols($pRow, $tablePos);

                if (!preg_match("/^\d+$/", $table[0])) {
                    if (!empty($table[0]) && preg_match("/^\s*{$this->opt($this->t('headerName'))}\s*$/", $headers[0])) {
                        $travellers[] = preg_replace('/([ ]*,[ ]*)+/', ', ', $table[0]);
                    }

                    if (!empty($table[1]) && preg_match("/^\s*{$this->opt($this->t('headerInfant'))}\s*$/", $headers[1])) {
                        $infants[] = preg_replace('/([ ]*,[ ]*)+/', ', ', $table[1]);
                    }

                    if (!empty($table[2]) && preg_match("/^\s*{$this->opt($this->t('headerFFNumber'))}\s*$/", $headers[2])) {
                        $ffNumbers[] = $table[2];
                    }

                    if (!empty($table[3]) && preg_match("/^\s*{$this->opt($this->t('headerTicket'))}\s*$/", $headers[3])) {
                        $tickets[] = $table[3];
                    }
                } else {
                    if (!empty($table[1]) && preg_match("/^\s*{$this->opt($this->t('headerName'))}\s*$/", $headers[1])) {
                        $travellers[] = preg_replace('/([ ]*,[ ]*)+/', ', ', $table[1]);
                    }

                    if (!empty($table[2]) && preg_match("/^\s*{$this->opt($this->t('headerInfant'))}\s*$/", $headers[2])) {
                        $infants[] = preg_replace('/([ ]*,[ ]*)+/', ', ', $table[2]);
                    }
                }
            }
        }

        /* Travellers (v2 - из таблицы с ценами): it-274948594.eml, it-280503883.eml, it-414230136.eml, it-418063264.eml, it-420944877.eml */
        if (count($travellers) === 0 && !empty($travellersFromPrice)) {
            $travellers = $travellersFromPrice;
        }

        /* Travellers (v3 - информация только о главном пассажире): it-429189713.eml, it-503778317.eml */
        if (count($travellers) === 0) {
            if (preg_match_all("/\n[ ]*{$this->opt($this->t('Name:'))}[ ]*({$this->patterns['travellerName']})\b(?:[ ]{8}.+)?\n+[ ]*{$this->opt($this->t('Email:'))}/u", $text, $m)) {
                $travellers = $m[1];
            }

            if (preg_match("/\n[ ]*Passenger Information\n[ ]*Name\n+((?:.+\n)+)\n+[* ]All charges and payments appear in[ ]*:/", $text, $match)) {
                $pax = array_filter(explode("\n", $match[1]));

                if (count($travellers) > 0) {
                    $travellers = array_merge($travellers, $pax);
                } else {
                    $travellers = $pax;
                }
            }
        }

        if (count($tickets) === 0
            && (preg_match_all("/(?:^\s*|\n[ ]*|[ ]{2}){$this->opt($this->t('Ticket Number'), true)}[:\s]*\b({$this->patterns['eTicket']})(?:[ ]{2}|\n)/", $text, $ticketMatches)
                || preg_match_all("/\s[A-Z]{3}\s*-\s*[A-Z]{3}\s*(\d{12,})\n/", $text, $ticketMatches)
            )
        ) {
            $tickets = $ticketMatches[1];
        }

        $travellers = array_unique(str_replace(['/YKT'], '', $travellers));

        $travellers = preg_replace("/^\s*([A-Z][A-Z\- ]+?)\s*,\s*([A-Z][A-Z\- ]+?)\s*$/", '$2 $1', $travellers);
        $travellers = preg_replace("/\s+/", ' ', $travellers);

        $f->general()->travellers(preg_replace("/(?:\s+MRS|\s+MS)$/", "", $travellers), true);

        if (count($infants) > 0) {
            $infants = preg_replace("/^\s*([A-Z][A-Z\-\s]+?)\s*,\s*([A-Z][A-Z\-\s]+?)\s*$/", '$2 $1', $infants);
            $infants = preg_replace("/\s+/", ' ', $infants);
            $f->general()->infants(array_unique($infants));
        }

        if (count($ffNumbers) > 0) {
            $f->program()->accounts(array_unique($ffNumbers), false);
        }

        if (count($tickets) > 0) {
            $f->issued()->tickets(array_unique($tickets), false);
        }

        $this->text = $text;
        $flightText = $this->re("/(?:Flight Itinerary|Itinerary)\n[ ]{0,10}Leg\s+Flight\D+\n(.+?)\n+[ ]*(?:Special Requests|All charges and payments appear in:|Travel Restrictions|Fare Rules Summary)/s", $text);

        if (!empty($flightText)) {
            $this->ParseSegment($f, $flightText);
        } elseif ($flightText = $this->re("/{$this->opt($this->t('Itinerary'))}\n{$this->opt($this->t('Leg'))}\s+{$this->opt($this->t('Date'))}\D+\n+((?:\d+.*\n+(?:\s*Operated By.*\n+|\s+.*\d{4}\n+)?){1,})/", $text)) {
            //remove junk
            $flightText = preg_replace("/\n{2,}[#].+/s", "", $flightText);
            $this->ParseSegment2($f, $flightText);
        } elseif ($flightText = $this->re("/(?:Flight Itinerary|{$this->t('Itinerary')})\n{$this->opt($this->t('Leg'))}\s+{$this->opt($this->t('Flight'))}\D+\n(.+)\n+(?:{$this->opt($this->t('All charges and payments appear in:'))}|\s*\S*\s*{$this->opt($this->t('segmentEnd'))})/s", $text)) {
            $this->ParseSegment3($f, $flightText);
        } elseif ($flightText = $this->re("/(?:Flight Itinerary|Itinerary)\nFlight\D+\n(.+)\n+(?:Passenger Information|Name).*(?:[*]All charges and payments appear in\:)/s", $text)) {
            //removeJunk
            $flightText = preg_replace("/\s*Passenger Information/", "", $flightText);
            $this->ParseSegment4($f, $flightText);
        }
    }

    public function ParseSegment(\AwardWallet\Schema\Parser\Common\Flight $f, $flightText): void
    {
        $this->logger->debug(__METHOD__);

        /*Itinerary
        Leg  Flight  From  To  Aircraft  Status*/

        $flightParts = array_filter(preg_split("/^\s*(\d+\s)/mu", $flightText));
        $spaceCount = strlen($this->re("/^\s*(\d+\s)/mu", $flightText));

        foreach ($flightParts as $flightPart) {
            $s = $f->addSegment();

            $flightTable = $this->splitCols(str_repeat(' ', $spaceCount) . $flightPart);

            if (preg_match("/^(?:(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<number>\d{1,5})\s*(?:Operated\s*By\s*(?<operator>\S.*?\S)\s*)?$/s", $flightTable[0], $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);

                switch ($m['name']) {
                    case 'DU': $s->airline()->name('Air Liaison');
                }

                if (!empty($m['operator'])) {
                    $m['operator'] = preg_replace('/\s+/', ' ', $m['operator']);

                    if (strcasecmp('Perimeter', $m['operator']) == 0) {
                        $s->airline()->carrierName('Perimeter Aviation');
                    } else {
                        $s->airline()->operator($m['operator']);
                    }
                }
            }

            $re = "/^(?<time>{$this->patterns['time']})[-\s]+(?<name>[\s\S]{2,}?)(?:\s*-\s*(?<terminal>.+)\s*Terminal)?\s*\n\s*(?<date>{$this->patterns['date']})\s*$/";

            if (count($flightTable) > 1 && preg_match($re, $flightTable[1], $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['time']))
                    ->name(preg_replace('/\s+/', ' ', trim($m['name'])))
                    ->noCode();

                if (isset($m['terminal']) && !empty($m['terminal'])) {
                    $s->departure()
                        ->terminal($m['terminal']);
                }
            }

            if (count($flightTable) > 2 && preg_match($re, $flightTable[2], $m)) {
                $s->arrival()
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['time']))
                    ->name(preg_replace('/\s+/', ' ', trim($m['name'])))
                    ->noCode();

                if (isset($m['terminal']) && !empty($m['terminal'])) {
                    $s->arrival()
                        ->terminal($m['terminal']);
                }
            }

            if (count($flightTable) > 3 && !empty(trim($flightTable[3]))) {
                $s->extra()
                    ->aircraft(preg_replace("/\s*\n\s*/", " ", trim($flightTable[3])));
            }

            if (count($flightTable) > 4 && !empty(trim($flightTable[4]))) {
                $s->extra()
                    ->status(trim($flightTable[4]));
            }
        }
    }

    public function ParseSegment2(\AwardWallet\Schema\Parser\Common\Flight $f, $flightText): void
    {
        $this->logger->debug(__METHOD__);

        $flightParts = array_filter(preg_split("/^[ ]{0,10}\d{1,3} /m", $flightText));

        foreach ($flightParts as $flightPart) {
            $flightPart = preg_replace("/({$this->opt($this->t('# of Passengers'))}.+)/s", "", $flightPart);
            $s = $f->addSegment();

            $date = '';
            $flightTable = $this->splitCols($flightPart);

            /*Itinerary
            Leg  Date  Flight  Aircraft  From  To  Status*/

            if ((count($flightTable) == 6 or count($flightTable) == 5)
                && (preg_match("/^\s*(?<date>(?:\d+\/\w+\/\d{4}|\d+\s*\w+\s*\d{4}))\s*$/su", $flightTable[0]))
                && (preg_match("/{$this->opt($this->t('Aircraft'))}\s*{$this->opt($this->t('From'))}\s*{$this->opt($this->t('To'))}/su", $this->text))
            ) {
                if (preg_match("/^\s*(?<date>(?:\d+\/\w+\/\d{4}|\d+\s*\w+\s*\d{4}))\s*/su", $flightTable[0], $m)) {
                    $date = $m['date'];
                }

                if (preg_match("/^\s*(?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<flightNumber>\d{1,5})\s*(?:{$this->opt($this->t('Operated By'))}\s*(?<operator>.+))?$/su", $flightTable[1], $m)) {
                    $s->airline()
                        ->name($m['airlineName'])
                        ->number($m['flightNumber']);

                    if (isset($m['operator']) && !empty($m['operator'])) {
                        $m['operator'] = trim($m['operator']);

                        if (strcasecmp('Perimeter', $m['operator']) == 0) {
                            $s->airline()->carrierName('Perimeter Aviation');
                        } else {
                            $s->airline()->operator($m['operator']);
                        }
                    }
                }

                if (count($flightTable) > 1 && preg_match("/^\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,5}(?:\s|$)/", $flightTable[1])
                    && count($flightTable) > 2 && preg_match("/^\s*{$this->patterns['time']}\s*-/", $flightTable[2])
                ) {
                    // insert empty `Aircraft` field
                    array_splice($flightTable, 1, 1, [$flightTable[1], '']);
                }

                if (count($flightTable) > 2 && !empty(trim($flightTable[2]))) {
                    $s->extra()
                        ->aircraft(trim($flightTable[2]));
                }

                if (preg_match("/^\s*(?<depTime>{$this->patterns['time']})\s*-\s*(?<depName>\D+)\n+$/", $flightTable[3], $m)) {
                    $s->departure()
                        ->date($this->normalizeDate($date . ', ' . $m['depTime']))
                        ->name(str_replace("\n", "", $m['depName']))
                        ->noCode();
                }

                if (preg_match("/^\s*(?<arrTime>{$this->patterns['time']})\s*-\s*(?<arrName>\D+)\n+$/", $flightTable[4], $m)) {
                    $s->arrival()
                        ->date($this->normalizeDate($date . ', ' . $m['arrTime']))
                        ->name(str_replace("\n", "", $m['arrName']))
                        ->noCode();
                }

                if (isset($flightTable[5]) && !empty(trim($flightTable[5]))) {
                    $s->extra()
                        ->status(trim($flightTable[5]));
                }
            } elseif ((count($flightTable) == 6 or count($flightTable) == 5) && (preg_match("/(?<date>(?:\d+\/\w+\/\d{4}|\d+\s*\w+\s*\d{4}))\s(?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<flightNumber>\d+)/u", $flightPart))) {
                if (preg_match("/^(?<date>(?:\d+\/\w+\/\d{4}|\d+\s*\w+\s*\d{4}))\s*(?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<flightNumber>\d{1,5})\s*$/su", $flightTable[0], $m)) {
                    $s->airline()
                        ->name($m['airlineName'])
                        ->number($m['flightNumber']);

                    $date = $m['date'];
                }

                if (preg_match("/^(?<depTime>[\d\:]+)\s*\-\s*(?<depName>\D+)\n+$/", $flightTable[1], $m)) {
                    $s->departure()
                        ->date($this->normalizeDate($date . ', ' . $m['depTime']))
                        ->name($m['depName'])
                        ->noCode();
                }

                if (preg_match("/^(?<arrTime>[\d\:]+)\s*\-\s*(?<arrName>\D+)\n+$/", $flightTable[2], $m)) {
                    $s->arrival()
                        ->date($this->normalizeDate($date . ', ' . $m['arrTime']))
                        ->name($m['arrName'])
                        ->noCode();
                }

                if (isset($flightTable[3]) && !empty(trim($flightTable[3]))) {
                    $s->extra()
                        ->aircraft(trim($flightTable[3]));
                }

                if (isset($flightTable[4]) && !empty(trim($flightTable[4]))) {
                    $s->extra()
                        ->status(trim($flightTable[4]));
                }

                if (isset($flightTable[5]) && !empty(trim($flightTable[5]))) {
                    $s->extra()
                        ->seats(explode(',', trim($flightTable[5])));
                }
            } elseif ((count($flightTable) == 6 or count($flightTable) == 5) && (preg_match("/(?<date>(?:\d+\/\w+\/\d{4}|\d+\s*\w+\s*\d{4}))[ ]{2,}(?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])?(?<flightNumber>\d+)/u", $flightPart))) {
                /*Itinerary
                Leg  Date  Flight  From  To  Aircraft  Status*/

                if (preg_match("/^(?<date>(?:\d+\/\w+\/\d{4}|\d+\s*\w+\s*\d{4}))/", $flightTable[0], $m)) {
                    $date = str_replace('/', ' ', $m['date']);
                }

                if (preg_match("/(?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])?(?<flightNumber>\d{1,5})\s*/su", $flightTable[1], $m)) {
                    if (isset($m['airlineName']) && !empty($m['airlineName'])) {
                        $s->airline()
                            ->name($m['airlineName'])
                            ->number($m['flightNumber']);
                    } elseif (stripos($this->text, 'wildernessseaplanes.com') !== false) {
                        $s->airline()
                            ->name('8P')
                            ->number($m['flightNumber']);
                    }
                }

                if (preg_match("/^(?<depTime>[\d\:]+)\s*\-\s*(?<depName>.+)\n+/", $flightTable[2], $m)) {
                    $s->departure()
                        ->date($this->normalizeDate($date . ', ' . $m['depTime']))
                        ->name($m['depName'])
                        ->noCode();
                }

                if (preg_match("/^(?<arrTime>[\d\:]+)\s*\-\s*(?<arrName>.+)\n+/", $flightTable[3], $m)) {
                    $s->arrival()
                        ->date($this->normalizeDate($date . ', ' . $m['arrTime']))
                        ->name($m['arrName'])
                        ->noCode();
                }

                if (isset($flightTable[4]) && !empty(trim($flightTable[4]))) {
                    $s->extra()
                        ->aircraft(trim($flightTable[4]));
                }

                if (isset($flightTable[5]) && !empty(trim($flightTable[5]))) {
                    $s->extra()
                        ->status(trim($flightTable[5]));
                }

                if (preg_match_all("/\b{$s->getAirlineName()}{$s->getFlightNumber()}\s*\-\s*(\d+[A-Z]),?\n/", $this->text, $sMatch)) {
                    $s->extra()
                        ->seats($sMatch[1]);
                }
            } elseif (count($flightTable) == 7) {
                /*Itinerary
                Leg  Date  Flight  From  To  Aircraft  Status  Seat*/

                if (preg_match("/^(?<date>(?:\d+\/\w+\/\d{4}|\d+\s*\w+\s*\d{4}))\s*$/su", $flightTable[0], $m)) {
                    $date = str_replace('/', ' ', $m['date']);
                }

                if (preg_match("/^\s*(?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<flightNumber>\d{1,5})\s*$/s", $flightTable[1], $m)) {
                    $s->airline()
                        ->name($m['airlineName'])
                        ->number($m['flightNumber']);
                }

                if (preg_match("/^(?<depTime>[\d\:]+)\s*\-\s*(?<depName>\D+)\n+$/", $flightTable[2], $m)) {
                    $s->departure()
                        ->date($this->normalizeDate($date . ', ' . $m['depTime']))
                        ->name($m['depName'])
                        ->noCode();
                }

                if (preg_match("/^(?<arrTime>[\d\:]+)\s*\-\s*(?<arrName>\D+)\n+$/", $flightTable[3], $m)) {
                    $s->arrival()
                        ->date($this->normalizeDate($date . ', ' . $m['arrTime']))
                        ->name($m['arrName'])
                        ->noCode();
                }

                if (isset($flightTable[4]) && !empty(trim($flightTable[4]))) {
                    $s->extra()
                        ->aircraft(trim($flightTable[4]));
                }

                if (isset($flightTable[5]) && !empty(trim($flightTable[5]))) {
                    $s->extra()
                        ->status(trim($flightTable[5]));
                }

                if (isset($flightTable[6]) && !empty(trim($flightTable[6]))) {
                    $s->extra()
                        ->seats(explode(',', trim($flightTable[6])));
                }
            }
        }
    }

    public function ParseSegment3(\AwardWallet\Schema\Parser\Common\Flight $f, $flightText): void
    {
        $this->logger->debug(__METHOD__);

        $flightParts = array_filter(preg_split("/^(\d)/mu", $flightText));

        foreach ($flightParts as $flightPart) {
            $s = $f->addSegment();

            $date = '';

            $flightTable = $this->splitCols($flightPart);

            if ((count($flightTable) == 6 or count($flightTable) == 5) && (preg_match("/(?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<flightNumber>\d{1,5})\s*(?<date>(?:\d+\/\w+\/\d{4}|\d+\s*\w+\s*\d{4}))\s/u", $flightPart))) {
                if (preg_match("/^\s*(?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<flightNumber>\d{1,5})\s*$/su", $flightTable[0], $m)) {
                    $s->airline()
                        ->name($m['airlineName'])
                        ->number($m['flightNumber']);
                }

                if (preg_match("/^\s*\D*\s*(?<date>(?:\d+\/\w+\/\d{4}|\d+\s*\w+\s*\d{4}))\s*$/msu", $flightTable[1], $m)) {
                    $date = $m['date'];
                }

                if (preg_match("/^\s*(?<depTime>[\d\:]+)\s*\-\s*(?<depName>\D+)\s*$/mu", $flightTable[2], $m)) {
                    $s->departure()
                        ->date($this->normalizeDate($date . ', ' . $m['depTime']))
                        ->name($m['depName'])
                        ->noCode();
                }

                if (preg_match("/^\s*(?<arrTime>[\d\:]+)\s*\-\s*(?<arrName>\D+)\s*$/mu", $flightTable[3], $m)) {
                    $s->arrival()
                        ->date($this->normalizeDate($date . ', ' . $m['arrTime']))
                        ->name($m['arrName'])
                        ->noCode();
                }

                if (isset($flightTable[4]) && !empty(trim($flightTable[4]))) {
                    $s->extra()
                        ->aircraft(trim($flightTable[4]));
                }

                if (isset($flightTable[5]) && !empty(trim($flightTable[5]))) {
                    $s->extra()
                        ->status(trim($flightTable[5]));
                }
            } elseif ((count($flightTable) == 6 or count($flightTable) == 5) && (preg_match("/(?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<flightNumber>\d{1,5})\s*(?<time>(?:\d+\:\d+))\s/u", $flightPart))) {
                if (preg_match("/^\s*(?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<flightNumber>\d{1,5})\s*$/su", $flightTable[0], $m)) {
                    $s->airline()
                        ->name($m['airlineName'])
                        ->number($m['flightNumber']);
                }

                if (preg_match("/^\s*(?<depTime>[\d\:]+)\s*\-\s*(?<depName>\D+)\s+\w+\s+(?<depDate>\d+\s*\w+\s*\d{4})$/msu", $flightTable[1], $m)) {
                    $s->departure()
                        ->date($this->normalizeDate($m['depDate'] . ', ' . $m['depTime']))
                        ->name($m['depName'])
                        ->noCode();
                }

                if (preg_match("/^\s*(?<arrTime>[\d\:]+)\s*\-\s*(?<arrName>\D+)\s+\w+\s+(?<arrDate>\d+\s*\w+\s*\d{4})$/msu", $flightTable[2], $m)) {
                    $s->arrival()
                        ->date($this->normalizeDate($m['arrDate'] . ', ' . $m['arrTime']))
                        ->name($m['arrName'])
                        ->noCode();
                }

                if (isset($flightTable[4]) && !empty(trim($flightTable[4]))) {
                    $s->extra()
                        ->aircraft(trim($flightTable[4]));
                }

                if (isset($flightTable[5]) && !empty(trim($flightTable[5]))) {
                    $s->extra()
                        ->status(trim($flightTable[5]));
                }
            }
        }
    }

    //it-503778317.eml
    public function ParseSegment4(\AwardWallet\Schema\Parser\Common\Flight $f, $flightText): void
    {
        $this->logger->debug(__METHOD__);

        $flightParts = array_filter(preg_split("/\n\n/mu", $flightText));

        foreach ($flightParts as $flightPart) {
            if (stripos($flightPart, ':') === false) {
                continue;
            }

            $s = $f->addSegment();

            $flightTable = $this->splitCols($flightPart);

            if (count($flightTable) >= 4) {
                if (preg_match("/^\s*(?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<flightNumber>\d{1,5})\s*$/su", $flightTable[0], $m)) {
                    $s->airline()
                        ->name($m['airlineName'])
                        ->number($m['flightNumber']);
                }

                if (preg_match("/(?<depTime>[\d\:]+)[\s\-]*(?<depName>.+)\n(?<depDate>\d+\s*\w+\s*\d{4})/u", $flightTable[1], $m)) {
                    $s->departure()
                        ->name($m['depName'])
                        ->date(strtotime($m['depDate'] . ', ' . $m['depTime']))
                        ->noCode();
                }

                if (preg_match("/(?<arrTime>[\d\:]+)[\s\-]*(?<arrName>.+)\n(?<arrDate>\d+\s*\w+\s*\d{4})/u", $flightTable[2], $m)) {
                    $s->arrival()
                        ->name($m['arrName'])
                        ->date(strtotime($m['arrDate'] . ', ' . $m['arrTime']))
                        ->noCode();
                }

                if (count($flightTable) === 5) {
                    $s->setAircraft($flightTable[3]);
                    $s->setStatus($flightTable[4]);
                } elseif (count($flightTable) === 4) {
                    $s->setStatus($flightTable[3]);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (!empty($this->code = $this->getProvider($parser, $text))) {
                    $email->setProviderCode($this->code);
                }

                $this->assignLang($text);

                $this->ParseFlightPDF($email, $text);
            }
        } else {
            $this->ParseFlightHTML($email);
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

    public static function getEmailCompanies()
    {
        return [
            'Pacific Coastal Airlines' => 1,
            'PAL Airlines'             => 1,
            'Air Creebec'              => 1,
            'Wilderness Seaplanes'     => 1,
            'Air Liaison'              => 1,
            'Air Inuit'                => 1,
            'Air Saint-Pierre'         => 1,
            'Central Mountain Air'     => 1,
        ];
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function opt($field, bool $expandSpaces = false)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) use ($expandSpaces) {
            return $expandSpaces ? preg_replace('/[ ]+/', '[ ]+', preg_quote($s, '/')) : preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/u', $row, -1, PREG_SPLIT_NO_EMPTY);
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
            $pos = $this->rowColsPos($rows[0]);
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

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    private function getProvider(PlancakeEmailParser $parser, $textBody): ?string
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        foreach (self::$providers as $code => $arr) {
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (strpos($textBody, $search) !== false) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function getCompanies(PlancakeEmailParser $parser, $textBody): ?string
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        foreach (self::$companies as $company => $arr) {
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (strpos($textBody, $search) !== false) {
                        return $company;
                    }
                }
            }
        }

        return null;
    }

    private function assignLang($text): bool
    {
        foreach ($this->detectLang as $key => $words) {
            foreach ($words as $word) {
                if (strpos($text, $word) !== false) {
                    $this->lang = $key;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\/(\D+)\/(\d{4})\,\s*([\d\:]+)$#u", //20/Dec/2022, 15:45
            "#^\w*\s+(\d+)\s*(\w+)\s*(\d{4})\,\s*([\d\:]+)$#u", //Monday 19 June 2023, 07:40
            "#^(\d+)\/(\d+)\/(\d{4})\,\s*([\d\:]+)$#u", //20/08/2023, 12:30
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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

    private function inOneRow($text)
    {
        if (empty(trim($text))) {
            return '';
        }
        $textRows = array_filter(explode("\n", $text));
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

    private function rowColumnPositions(?string $row, $delimiter = '\s{2,}'): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/{$delimiter}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function createTable(?string $text, $pos = []): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
