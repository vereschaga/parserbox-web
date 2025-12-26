<?php

namespace AwardWallet\Engine\tcase\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\tcase\Email\It5045494 as MainParser;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class eTicketReceiptPdf extends \TAccountChecker
{
    public $mailFiles = "tcase/it-209643080.eml, tcase/it-303032348.eml, tcase/it-697163100.eml, tcase/it-744664113.eml";

    public $nameFilePDF = [
        'ru' => ['Квитанция электронного билета.*'],
        'es' => ['Recibo de pasaje electrónico.+'],
        'en' => [
            'Electronic ticket receipt.*',
        ],
    ];

    public $lang = 'en';

    public static $dict = [
        'en' => [
            //            "Prepared For" => "",
            "RESERVATION CODE" => ["RESERVATION CODE", "BOOKING REFERENCE"],
            "ISSUE DATE"       => "ISSUE DATE",
            //            "TICKET NUMBER" => "",
            //            "ISSUING AGENT" => "",
            "Itinerary Details" => "Itinerary Details",
            //            "Operated by:" => "",
            //            "Time" => "",
            //            "Terminal" => "",
            //            "Airline Reservation Code" => "",
            //            "Cabin" => "",
            //            "Seat Number" => "",
            //            "This is not a boarding pass" => "",
            "EndSegments" => ["Payment/Fare Details", "Allowances", "Receipt And Payment Details", 'Please contact your travel arranger for fare details.'],
            //            "Fare" => "",
            //            "Taxes/Fees/Carrier-Imposed Charges" => "",
            "Total Fare"   => ["Total", "Total Fare", 'Total/Transaction Currency'],
            "Prepared For" => ["Prepared For", "preparedFor"],
        ],
        'ru' => [
            "Prepared For"     => "Подготовлен для",
            "RESERVATION CODE" => "КОД БРОНИРОВАНИЯ",
            "ISSUE DATE"       => "ДАТА ВЫДАЧИ БИЛЕТА",
            "TICKET NUMBER"    => "НОМЕР БИЛЕТА",
            "ISSUING AGENT"    => "АГЕНТ",

            "Itinerary Details" => "Сведения О Маршруте",

            "TRAVEL DATE" => "ДАТА ПОЕЗДКИ",
            "DEPARTURE"   => "ОТПРАВЛЕНИЕ",

            "Terminal" => "Терминал",
            //            "Operated by:" => "",
            "Time" => "Время",

            //            "Airline Reservation Code" => "",
            "Cabin"       => "Класс",
            "Seat Number" => "Номер места",
            //            "This is not a boarding pass" => "",

            "EndSegments" => ["Нормы", "Подробности Платежа"],

            "Fare"                               => "Тариф",
            'Equivalent Amount Paid'             => 'Эквивалент',
            "Taxes/Fees/Carrier-Imposed Charges" => "Сборы",
            "Total Fare"                         => "Итого по тарифу/сборам",
        ],
        'es' => [
            "Prepared For"     => "Preparado para",
            "RESERVATION CODE" => "CÓDIGO DE RESERVACIÓN",
            "ISSUE DATE"       => "FECHA DE EMISIÓN",
            "TICKET NUMBER"    => "NÚMERO DE BOLETO",
            "ISSUING AGENT"    => "AGENTE EMISOR",

            "Itinerary Details" => "Información De Vuelo",

            "TRAVEL DATE" => "FECHA",
            "DEPARTURE"   => "SALIDA",

            //            "Terminal" => "",
            "Operated by:" => "Operado por:",
            "Time"         => "Hora",

            "Airline Reservation Code"    => "Código de reservación de la aero línea",
            "Cabin"                       => "Cabina",
            "Seat Number"                 => "Número de asiento",
            "This is not a boarding pass" => "Esta no es una tarjeta de embarque",

            "EndSegments" => ["Límites De Equipaje", "Detalles De Pago", 'Por favor contacte a su agente de viajes por mas detalles sobre la tarifa.'],

            "Fare"                               => "Tarifa",
            // 'Equivalent Amount Paid'             => 'Эквивалент',
            "Fare"                               => "Cantidad equivalente pagada",
            "Taxes/Fees/Carrier-Imposed Charges" => "Impuestos / comisiones / cargos",
            "Total Fare"                         => "Tarifa total",
        ],
    ];
    private $providerCode;
    private $flightCodes;
    private $date;
    private $travellerName;

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        $this->providerCode = $this->getProviderByEmailText($parser);

        $fileName = [];

        foreach ($this->nameFilePDF as $list) {
            $fileName[] = implode("|", $list);
        }
        $pdfNameRule = implode("|", $fileName);
        $pdfs = $parser->searchAttachmentByName("(?:{$pdfNameRule})(?:.*pdf|)");

        if (count($pdfs) == 0) {
            $pdfs = $parser->searchAttachmentByName(".*\.pdf");
        }

        $foundPdf = false;

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }

            if ($this->detectFormatPdf($text) !== true) {
                continue;
            }

            if ($f = $this->parsePdf($email, $text)) {
                $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
                $htmlPdf = str_replace(['&#160;', '&nbsp;', ' '], ' ', htmlspecialchars_decode($htmlPdf));

                $segmentsTable = $this->tablePdf($htmlPdf);

                if (!empty($segmentsTable)) {
                    $this->parseHtmlSegments($f, $segmentsTable);
                } else {
                    $f->addSegment();
                }
            }
            $foundPdf = true;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $email->setProviderCode($this->providerCode);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $fileName = [];

        foreach ($this->nameFilePDF as $list) {
            $fileName[] = implode("|", $list);
        }
        $pdfNameRule = implode("|", $fileName);

        $pdfs = $parser->searchAttachmentByName("(?:{$pdfNameRule})(?:.*pdf|)");

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->detectFormatPdf($textPdf) === true) {
                return true;
            }
        }

        $pdfs = $parser->searchAttachmentByName('.*\.pdf.*');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->detectFormatPdf($textPdf) === true) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['subject'])) {
            return false;
        }

        foreach (MainParser::$detectProviders as $code => $params) {
            if (!empty($params['uniqueSubject'])) {
                if ($this->striposAll($headers['subject'], $params['uniqueSubject']) !== false) {
                    $this->providerCode = $code;

                    return true;
                }
            }

            if (!empty($params['from'])) {
                if ($this->striposAll($headers['from'], $params['from']) !== false
                        && $this->striposAll($headers['subject'], MainParser::$commonSubject) !== false) {
                    $this->providerCode = $code;

                    return true;
                }
            }
        }

        return false;
    }

    public function parsePdf(Email $email, string $text)
    {
        // $this->logger->debug('$text = '.print_r( $text,true));
        // Provider and Travel Agency
        $code = $this->getProviderByPdf($text);

        if (!empty($code)) {
            $this->providerCode = $code;
        } elseif (empty($this->providerCode)) {
            $this->providerCode = 'tcase';
        }

        $reservationCode = $this->deleteSpaces($this->re("#\n\s*(?:.+ )?" . $this->sOpt($this->t("RESERVATION CODE")) . "[ ]+([A-Z\d ]{5,})\s*\n#", $text));

        if (isset(MainParser::$detectProviders[$this->providerCode]['isTravelAgency'])
            && MainParser::$detectProviders[$this->providerCode]['isTravelAgency'] === true
            && ($email->getTravelAgency() && !in_array($reservationCode, array_column($email->getTravelAgency()->getConfirmationNumbers(), 0))
                || !$email->getTravelAgency()
            )
        ) {
            $email->ota()
                ->code($this->providerCode)
                ->confirmation($reservationCode);
        }

        $foundIt = false;
        $ticket = $this->re("#\n\s*" . $this->sOpt($this->t("TICKET NUMBER")) . "\s+(\d.+?)(?:\[[A-Z]*\])?(?: {3,}.*)?\s*\n#u", $text);

        foreach ($email->getItineraries() as $it) {
            $tickets = array_column($it->getTicketNumbers(), 0);

            if (!empty($tickets[0]) && strncmp($ticket, $tickets[0], 3) === 0) {
                $f = $it;
                $foundIt = true;

                break;
            }
        }

        if ($foundIt === false) {
            $f = $email->add()->flight();
        }

        if (empty($email->getTravelAgency())) {
            if (!in_array($reservationCode, array_column($f->getConfirmationNumbers(), 0))) {
                $f->general()->confirmation($reservationCode);
            }
        } else {
            $f->general()->noConfirmation();
        }

        $f->general()
            ->date($this->normalizeDate($this->re("#\n\s*" . $this->sOpt($this->t("ISSUE DATE")) . "[ ]+(.+)\s*\n#u", $text)))
        ;
        $traveller = $this->re("#\n\s*" . $this->sOpt($this->t("Prepared For")) . "(?: .*)?\s+(.+?)(?:\[[A-Z]*\])?(?:\[\d+\])?\s*\n#", $text);
        $traveller = preg_replace("/^\s*(.{30,}) {5,}\S.*/", '$1', $traveller);
        $traveller = preg_replace("/\s+(MS|MR|MRS|MISS|MSTR|DR)\s*$/i", '', $traveller);
        $traveller = preg_replace("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", '$2 $1', $traveller);

        $this->travellerName = $traveller;

        if (empty($traveller) || !in_array($traveller, array_column($f->getTravellers(), 0))) {
            $f->general()
                ->traveller($traveller, true);
        }

        $this->date = $f->getReservationDate();

        if (empty($ticket) || !in_array($ticket, array_column($f->getTicketNumbers(), 0))) {
            $f->issued()
                ->ticket($ticket, false, $this->travellerName);
        }

        // Price
        $currencyRe = "[A-Z]{3}";

        if (preg_match("#\n[ ]{0,8}" . $this->sOpt($this->t("Total Fare")) . "[ ]{5,}([A-Z]{3}) *(\d[,. \d]*)\n#", $text, $m)) {
            $f->price()
                ->currency($m[1])
                ->total(PriceHelper::parse(str_replace(' ', '', $m[2]), $m[1]))
            ;
            $currencyRe = $m[1];
        }

        if (preg_match("/\n[ ]{0,8}" . $this->sOpt($this->t("Equivalent Amount Paid")) . "[ ]{5,}({$currencyRe}) *(\d[,. \d]*)\n/", $text, $m)
            || preg_match("/\n[ ]{0,8}" . $this->sOpt($this->t("Fare")) . "[ ]{5,}({$currencyRe}) *(\d[,. \d]*)\n/", $text, $m)
        ) {
            $f->price()
                ->currency($m[1])
                ->cost(PriceHelper::parse(str_replace(' ', '', $m[2]), $m[1]))
            ;
        }

        $taxesText = $this->re("#\n([ ]{0,8}" . $this->sOpt($this->t("Taxes/Fees/Carrier-Imposed Charges")) . "[ ]{5,}{$currencyRe} *\d[,. \d]*(?:.*\n+)+?)[ ]{0,8}\w#u", $text);
        $taxesRow = $this->split("#[ ]{5,}(" . $currencyRe . " *\d)#", $taxesText);
        $taxes = [];

        foreach ($taxesRow as $row) {
            if (preg_match("/(" . $currencyRe . ") *(\d[,. \d]*) (.+)/su", $row, $m)) {
                $taxes[] = ['name' =>$this->normalizeSpaces($m[3]), 'amount' => PriceHelper::parse(str_replace(' ', '', $m[2]), $m[1])];
            } else {
                $taxes = [];

                break;
            }
        }

        if (!empty($taxes)) {
            foreach ($taxes as $tax) {
                $f->price()->fee($tax['name'], $tax['amount']);
            }
        }

        // Codes
        $codesText = $this->re("/\n\s*{$this->sOpt($this->t('Carry On Allowances'))}\n\s*((?:[A-Z]{3}\s+{$this->sOpt($this->t('to'))}\s+[A-Z]{3}[\s,]+)+)/", $text);
        $routes = preg_split("/\s*,\s*/", $codesText);
        $this->flightCodes = [];
        // Carry On Allowances
        // MSY to DFW , DFW to MSN , MSN to DFW , DFW to MSY - 2 Pieces (AA - AMERICAN AIRLINES)
        foreach ($routes as $row) {
            if (preg_match("/^\s*([A-Z]{3})\s+{$this->sOpt($this->t('to'))}\s+([A-Z]{3})\s*$/", $row, $m)) {
                $this->flightCodes[] = ['d' => $m[1], 'a' => $m[2]];
            } else {
                $this->flightCodes = [];

                break;
            }
        }

        return $f;
    }

    public function parseHtmlSegments(Flight $f, $segments)
    {
        if (count($this->flightCodes) !== count($segments)) {
            $this->flightCodes = [];
        }

        // $this->logger->debug('parseHtmlSegments = '.print_r( $segments,true));
        foreach ($segments as $sKey => $table) {
            $s = $f->addSegment();

            if (count($table) !== 5) {
                $this->logger->debug('Incorrect parse segment table');

                return false;
            }

            $dateDep = $dateArr = null;

            if (preg_match("#^\s*(.+?)\s*-\s*(.+?)\s*$#su", $table[0], $m)) {
                $dateDep = $this->normalizeDate($this->deleteSpaces($m[1]));
                $dateArr = $this->normalizeDate($this->deleteSpaces($m[2]));
            } elseif (preg_match("#^\s*{$this->sOpt($this->t('Departure:'), true)}\s*(.+?)\s*\n*\s*{$this->sOpt($this->t('Arrival:'), true)}\s*(.+?)\s*$#su", $table[0], $m)) {
                $dateDep = $this->normalizeDate($this->deleteSpaces($m[1]));
                $dateArr = $this->normalizeDate($this->deleteSpaces($m[2]));
            } else {
                $dateDep = $dateArr = $this->normalizeDate($this->deleteSpaces($table[0]));
            }

            // Airlines
            if (preg_match("#\s+(?<al>[A-Z\d] ?[A-Z]|[A-Z] ?[A-Z\d])\s+(?<fn>(?:\d ?){1,5})(?:\n|\s*$)#s", $table[1], $m)) {
                $s->airline()
                    ->name($this->deleteSpaces($m['al']))
                    ->number($this->deleteSpaces($m['fn']))
                ;
            }

            if (preg_match("#(?:^|\n)\s*" . $this->sOpt($this->t("Airline Reservation Code"), true) . "\s+((?:[A-Z\d] ?){5,7})\s*\n#", $table[4], $m)) {
                $s->airline()
                    ->confirmation(str_replace(' ', '', $m[1]))
                ;
            }

            if (preg_match("#\n\s*" . $this->sOpt($this->t("Operated by:"), true) . "\s+(?<wl>/)?(?<oper>.+?)\s*(?:\s+(?:FOR|DBA)\s+\w.+|$)#us", $table[1], $m)) {
                $m['oper'] = $this->normalizeSpaces($m['oper']);

                if (strlen($m['oper']) > 50 && preg_match("/^(.+?)\s*\(.{8,}\)\s*$/", $m['oper'], $mat)) {
                    $m['oper'] = $mat[1];
                }
                $s->airline()
                    ->operator($m['oper'])
                ;

                if (!empty($m['wl'])) {
                    $s->airline()->wetlease();
                }
            }

            // Departure
            $regexp = "#(?<name>.+)\n\s*" . $this->sOpt($this->t("Time")) . "\s*\n(?<time>[\d: ]{4,}[^\n]*)(?:\s+" . $this->sOpt($this->t("Terminal")) . "\s+(?<terminal>.+))?\s*$#s";
            $regexpWithOutTime = "#(?<name>.+?)\n\s*(?:\s+" . $this->sOpt($this->t("Terminal")) . "\s+(?<terminal>.+))?\s*$#s";

            if (preg_match($regexp, $table[2], $m)
                || (!preg_match("/\b{$this->sOpt($this->t("Time"))}\b/", $table[2], $m) && preg_match($regexpWithOutTime, $table[2], $m))
            ) {
                if (isset($this->flightCodes[$sKey])) {
                    $s->departure()
                        ->code($this->flightCodes[$sKey]['d']);
                } else {
                    $s->departure()
                        ->noCode();
                }

                $s->departure()
                    ->terminal((!empty($m['terminal'])) ? $this->normalizeSpaces(preg_replace("#\b(Terminal|" . $this->sOpt($this->t('Terminal')) . ")\b#ui", '', $m['terminal']), ' -') : null, true, true)
                ;
                $m['name'] = $this->normalizeSpaces($m['name']);

                if (!empty($m['name'])) {
                    $s->departure()
                        ->name($m['name']);
                }

                if (isset($m['time'])) {
                    $s->departure()
                        ->strict()
                        ->date((!empty($dateDep)) ? strtotime($this->deleteSpaces($m['time']), $dateDep) : null);
                } else {
                    $s->departure()
                        ->noDate();
                }
            }

            // Arrival
            if (preg_match($regexp, $table[3], $m)
                || (!preg_match("/\b{$this->sOpt($this->t("Time"))}\b/", $table[3], $m) && preg_match($regexpWithOutTime, $table[3], $m))
            ) {
                if (isset($this->flightCodes[$sKey])) {
                    $s->arrival()
                        ->code($this->flightCodes[$sKey]['a']);
                } else {
                    $s->arrival()
                        ->noCode();
                }
                $s->arrival()
                    ->terminal((!empty($m['terminal'])) ? $this->normalizeSpaces(preg_replace("#\b(Terminal|" . $this->sOpt($this->t('Terminal')) . ")\b#ui", '', $m['terminal']), ' -') : null, true, true)
                ;
                $m['name'] = $this->normalizeSpaces($m['name']);

                if (!empty($m['name'])) {
                    $s->arrival()
                        ->name($m['name']);
                }

                if (isset($m['time'])) {
                    $s->arrival()
                        ->date((!empty($dateArr)) ? strtotime($this->deleteSpaces($m['time']), $dateArr) : null);
                } else {
                    $s->arrival()
                        ->noDate();
                }
            }

            // Extra
            if (preg_match("#(?:^|\n)\s*" . $this->sOpt($this->t("Cabin"), true) . "\s+([\w\s]+)\s*\n\s*" . $this->sOpt($this->t("Seat Number"), true) . "#u", $table[4], $m)) {
                $s->extra()
                    ->cabin($this->normalizeSpaces($m[1]));
            }

            if (preg_match("#(?:^|\n)\s*" . $this->sOpt($this->t("Seat Number"), true) . "\s+((?:\d ?){1,3}[A-Z])\s+#u", $table[4], $m)) {
                $s->extra()
                    ->seat($this->deleteSpaces($m[1]), true, true, $this->travellerName);
            }

            foreach ($f->getSegments() as $seg) {
                if ($seg->getId() !== $s->getId()) {
                    if (serialize(array_diff_key($seg->toArray(),
                            // ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))) {
                            ['seats' => [], 'assignedSeats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => [], 'assignedSeats' => []]))) {
                        if (!empty($s->getAssignedSeats())) {
                            foreach ($s->getAssignedSeats() as $seat) {
                                $seg->extra()
                                    ->seat($seat[0], false, false, $seat[1]);
                            }
                        } elseif (!empty($s->getSeats())) {
                            // foreach ($s->getSeats() as $seat) {
                            $seg->extra()->seats(array_unique(array_merge($seg->getSeats(),
                                    $s->getSeats())));
                            // }
                        }
                        $f->removeSegment($s);

                        break;
                    }
                }
            }
        }

        return true;
    }

    public function detectFormatPdf(string $textPdf): bool
    {
        $textPdf = mb_substr($textPdf, 0, 1500);

        foreach (self::$dict as $lang => $dict) {
            if (
                isset($dict["RESERVATION CODE"]) && isset($dict["ISSUE DATE"]) && isset($dict["Itinerary Details"])
                && preg_match("/\n\s*{$this->sOpt($dict['RESERVATION CODE'])}\s+/u", $textPdf)
                && preg_match("/\n\s*{$this->sOpt($dict['ISSUE DATE'])}\s+/u", $textPdf)
                && preg_match("/\n\s*{$this->sOpt($dict['Itinerary Details'])}\s+/u", $textPdf)
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@tripcase.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(MainParser::$detectProviders);
    }

    public function sOpt($fields, $addSpaceWord = false, $addSpace = true, $quote = true)
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        if ($quote == true) {
            $fields = array_map(function ($s) {
                return preg_quote($s, '#');
            }, $fields);
        }

        if ($addSpace == true) {
            $fields = array_map([$this, 'addSpace'], $fields);
        }

        if ($addSpaceWord == true) {
            $fields = array_map([$this, 'addSpacesWord'], $fields);
        }

        return '(?:' . implode('|', $fields) . ')';
    }

    /**
     * ["Przed", "wylotem"] -> "(?:P ?r ?z ?e ?d ?|w ?y ?l ?o ?t ?e ?m ?)"
     * "Przed" -> "P ?r ?z ?e ?d ?".
     */
    public function addSpace($text)
    {
        return preg_replace("#([^\s\\\])#u", "$1 ?", $text);
    }

    public function addSpacesWord($text)
    {
        return preg_replace("#\s(?!\?)#", '\s*', $text);
    }

    private function getProviderByEmailText(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response["body"];

        foreach (MainParser::$detectProviders as $code => $params) {
            if (isset($params['href']) && $this->http->XPath->query("//a[" . $this->contains($params['href'], '@href') . "]")->length > 0) {
                return $code;
            }

            if (isset($params['agency']) && $this->striposAll($body, $params['agency'])) {
                return $code;
            }
        }

        return null;
    }

    private function getProviderByPdf(string $text)
    {
        $issuingAgent = $this->re("#\n *" . $this->sOpt($this->t("ISSUING AGENT")) . "[ ]+(.+)#", $text);

        if (empty($issuingAgent)) {
            return null;
        }

        foreach (MainParser::$detectProviders as $code => $params) {
            if (isset($params['agency'])) {
                foreach ($params['agency'] as $fText) {
                    if (preg_match("#\b" . $this->sOpt($fText) . "\b#", $issuingAgent)) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function normalizeDate($instr)
    {
        // $this->logger->debug('$instr = '.print_r( $instr,true));
        if (
            preg_match('#^([^\d\W]\D+) (\d[\d\s]{0,2}) ([^\d\W]\D{2,})\.?$#u', $instr,
                $matches) // MO NDAY 1 1 AU G.    ->    MONDAY 11 AUG
            || preg_match('#^(\d[\d\s]{0,2}) ([^\d\W]\D{2,})\.? (\d[\d\s]{1,6})$#u', $instr,
                $matches) // 1 1 AU G. 201 8    ->    11 AUG 2018
        ) {
            $instr = $this->deleteSpaces($matches[1]) . ' ' . $this->deleteSpaces($matches[2]) . ' ' . $this->deleteSpaces($matches[3]);
        }

        $year = $this->date ? date("Y", $this->date) : 'XXXX';
        $in = [
            '#^\s*(\d{1,2})\s*([[:alpha:]]{3,})$#u', // 07Jan
            '#^\s*(\d{1,2})\s*([[:alpha:]]{3,})\s*(\d{2})$#u', // 16Jun14
            '#^\s*(\d{1,2})\s*([[:alpha:]]{3,})\s*(\d{4})$#u', // 14май2015
        ];
        $out = [
            "$1 $2 $year",
            "$1 $2 20$3",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $instr);
        // $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match('#\d+\s+([^\d\s]+)\s+\d{4}#u', $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);

                return strtotime($str);
            }
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $n) && !empty($n['week'])) {
                $dayOfWeekInt = \AwardWallet\Engine\WeekTranslate::number1($this->deleteSpaces($n['week']),
                    $this->lang);

                if (preg_match("#\s*([^,]+),\s+(\d+)\s+([^\d\s]+)(\s+\S.+)?$#u", $str, $n)) {
                    if (!($en = \AwardWallet\Engine\MonthTranslate::translate($n[3], $this->lang))) {
                        $en = $n[3];

                        return \AwardWallet\Common\Parser\Util\EmailDateHelper::parseDateUsingWeekDay($str,
                            $dayOfWeekInt);
                    }
                }
            }
        }

        return strtotime($str);
    }

    private function tablePdf($html)
    {
        $this->pdf = clone $this->http;
        $this->pdf->SetBody($html);
        $html = "";

        $pages = $this->pdf->XPath->query("//div[starts-with(@id, 'page')]");

        $start = false;
        $table = [];

        foreach ($pages as $page) {
            if ($snode = $this->pdf->FindSingleNode(".//p[" . $this->eqPdf($this->t("Itinerary Details"), true) . "]/@style", $page)) {
                $start = true;
            }

            if ($start === false) {
                continue;
            }

            if ($enode = $this->pdf->FindSingleNode("(.//p[" . $this->eqPdf($this->t("EndSegments")) . "]/@style)[1]", $page)) {
                $start = false;
            }

            $startTop = $this->re("#top:(\d+)px;#", $snode);
            $endTop = $this->re("#top:(\d+)px;#", $enode);

            $nodes = $this->pdf->XPath->query(".//p", $page);

            $grid = [];
            $prevTop = null;

            $lefts = [];

            foreach ($nodes as $node) {
                $text = html_entity_decode($this->pdf->FindHTMLByXpath(".", null, $node));
                $text = str_ireplace(["<br/>", "<br>"], "\n", $text);

                $text = strip_tags($text);

                if (preg_match("/^\s*{$this->sOpt($this->t('This is not a boarding pass'))}\s*$/", $text)) {
                    continue;
                }

                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");

                if ((!empty($startTop) && ($top < $startTop - 5)) || (!empty($endTop) && ($top >= $endTop))) {
                    continue;
                }

                if (isset($prevTop) && abs($prevTop - $top) < 3) {
                    $top = $prevTop;
                }
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");

                if ($left < 100) {
                    $fs = array_intersect_key($lefts, array_fill_keys(range(0, 100), ''));
                } else {
                    $fs = array_intersect_key($lefts, array_fill_keys(range($left - 25, $left + 25), ''));
                }

                if (empty($fs)) {
                    $lefts[$left] = null;
                } else {
                    $l = array_key_first($fs);

                    if (!empty($l)) {
                        $left = $l;
                    }
                }
                $grid[$top][$left] = $text;
                $prevTop = $top;
            }

            ksort($grid);
            $cols = array_keys($lefts);
            sort($cols);
            $cols = array_values($cols);

            $html .= "<table border='1'>";

            foreach ($grid as $row => $c) {
                ksort($c);
                $html .= "<tr>";
                $tr = [];

                foreach ($cols as $i => $col) {
                    $tr[$i] = $c[$col] ?? '';

                    if (isset($c[$col])) {
                        $html .= "<td>" . $c[$col] . "</td>";
                    } else {
                        $html .= "<td>" . "</td>";
                    }
                }
                $table[] = $tr;
                $html .= "</tr>";
            }
            $html .= "</table>";
        }

        $table = array_values($table);

        $segment = [];
        $segmentsTable = [];

        foreach ($table as $i => $tRow) {
            if ($i <= 3 && !preg_match("/\d/u", implode('', $tRow))) {
                continue;
            }

            if (preg_match("/^(.+) ({$this->sOpt($this->t("Airline Reservation Code"))})(\n[\s\S]*)/u", $tRow[3], $m)) {
                $tRow[3] = $m[1] . $m[3];
                $tRow[4] = $m[2] . ' ' . $tRow[4];
            }

            if (preg_match("/\d/", $tRow[0])
                && (preg_match("/{$this->sOpt($this->t("Time"))}/u", $segment[2] ?? '')
                    || preg_match("/{$this->sOpt($this->t("Time"))}/u", $segment[3] ?? '')
                )
            ) {
                if (!empty($segment)) {
                    $segmentsTable[] = $segment;
                }
                $segment = array_fill(0, 5, '');
            }

            for ($j = 0; $j < 5; $j++) {
                $segment[$j] = ($segment[$j] ?? '') . "\n" . ($tRow[$j] ?? '');
            }
        }

        $segmentsTable[] = $segment;
        // $this->logger->debug('$segmentsTable = '.print_r( $segmentsTable,true));

        $this->pdf->SetBody($html);

        return $segmentsTable;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        if (preg_match($re, $str, $m)) {
            if (isset($m[$c])) {
                return $m[$c];
            }
        }

        return null;
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

    private function rowColsPos($row, $delimiter = null)
    {
        if (empty($delimiter)) {
            $delimiter = '\s{2,}';
        }
        $head = array_filter(array_map('trim', explode("|", preg_replace("#" . $delimiter . "#", "|", $row))));

        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function ColsPos($table, $delta = 5, $delimiter = null)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row, $delimiter));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $delta) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }

        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $ds = 5; //back
        $ds2 = 5; // forward

        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $rIndex => $row) {
            foreach ($pos as $k => $p) {
                if ($rIndex == 0) {
                    $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                    $row = mb_substr($row, 0, $p, 'UTF-8');

                    continue;
                }
                $symbol = mb_substr($row, $p - 1, 1);
                $symbol2 = mb_substr($row, $p - 2, 1);

                if ($k != 0 && $symbol !== '' && $symbol2 !== '' && ($symbol !== ' ' || $symbol2 !== ' ')) {
                    $ds2r = mb_strlen(mb_substr($row, $p, $ds2, 'UTF-8'));
                    $str = mb_substr($row, $p - $ds, $ds + $ds2r, 'UTF-8');

                    if (preg_match("#(.*\s{2,})(.*?)$#", $str, $m)) {
                        $cols[$k][] = rtrim(mb_substr($row, $p - $ds + mb_strlen($m[1]), null, 'UTF-8'));
                        $row = mb_substr($row, 0, $p - mb_strlen($m[2]) + $ds2r, 'UTF-8');
                        $pos[$k] = $p - mb_strlen($m[2]) + $ds2r;

                        continue;
                    } elseif (preg_match("#(.*?\s)(.*?)$#", $str, $m)) {
                        $cols[$k][] = rtrim(mb_substr($m[2], 0, -$ds2r) . mb_substr($row, $p, null, 'UTF-8'));
                        $row = mb_substr($row, 0, $p - mb_strlen($m[2]) + $ds2r, 'UTF-8');
                        $pos[$k] = $p - mb_strlen($m[2]) + $ds2r;

                        continue;
                    }
                }

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

    private function opt($fields)
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '#');
        }, $fields)) . ')';
    }

    private function deleteSpaces($subject, $chars = null)
    {
        $subject = preg_replace("#\s+#", "", $subject);

        if (!empty($chars)) {
            $subject = trim($subject, $chars);
        }

        return $subject;
    }

    private function normalizeSpaces($subject)
    {
        $subject = preg_replace("#\s+#", ' ', $subject);

        if (is_array($subject)) {
            $subject = array_map('trim', $subject);
        } else {
            $subject = trim($subject);
        }

        return $subject;
    }

    private function eqPdf($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }
        $field = str_replace(' ', '', $field);

        return '(' . implode(" or ", array_map(function ($s) {
            return "translate(normalize-space(.), ' ', '')='{$s}'";
        }, $field)) . ')';
    }

    private function startsPdf($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }
        $field = str_replace(' ', '', $field);

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(translate(normalize-space(.), ' ', ''), '{$s}')";
        }, $field)) . ')';
    }

    private function containsPdf($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }
        $field = str_replace(' ', '', $field);

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(translate(normalize-space(.), ' ', ''), '{$s}')";
        }, $field)) . ')';
    }

    private function eq($field, $withoutSpace = false, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        if ($withoutSpace === true) {
            $field = str_replace(' ', '', $field);
            $text = "translate({$text}, ' ', '')";
        }

        return '(' . implode(" or ", array_map(function ($s) use ($text) {
            return $text . "='{$s}'";
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), '{$s}')";
        }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) use ($text) {
            return "contains(" . $text . ", '{$s}')";
        }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
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

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (strpos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && strpos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function inOneRow($text, $excludeSymbols = [])
    {
        if (is_string($text)) {
            $textRows = array_filter(explode("\n", $text));
        } elseif (is_array($text)) {
            $textRows = $text;
        }

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

                    if (in_array($sym, $excludeSymbols)) {
                        $oneRow[$l] = $sym;

                        continue;
                    }

                    if (!empty($oneRow[$l]) && in_array($oneRow[$l], $excludeSymbols)) {
                        continue;
                    }

                    $oneRow[$l] = chr(rand(97, 122));
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }
}
