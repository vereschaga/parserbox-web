<?php

namespace AwardWallet\Engine\kupibilet\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class YourTicketFlightPdf extends \TAccountChecker
{
    public $mailFiles = "kupibilet/it-38588021.eml, kupibilet/it-38602257.eml, kupibilet/it-38610279.eml, kupibilet/it-38654299.eml, kupibilet/it-38659604.eml, kupibilet/it-38696453.eml, kupibilet/it-38730691.eml, kupibilet/it-38747464.eml, kupibilet/it-38851815.eml, kupibilet/it-38984148.eml, kupibilet/it-38998467.eml, kupibilet/it-39171157.eml, kupibilet/it-39196062.eml";

    public $reFrom = ["@kupibilet.ru"];
    public $reBodyOrder = [
        'ru' => ['НОМЕР ЗАКАЗА KUPIBILET:'],
    ];
    public $reBodyReceipt = [
        'ru' => ['Маршрутная квитанция', 'Маршрутная кв итанция', 'Маршрутная'],
    ];
    public $reSubject = [
        'Ваш билет на самолет. Номер заказа',
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'ru' => [
            'РЕЙСЫ' => 'РЕЙСЫ',
            'ВЫЛЕТ' => 'ВЫЛЕТ',
            'fees'  => ['Сервисный сбор', 'Услуга онлайн-регистрации'],
            // Receipt
            // for detect lang
            'Отправление' => 'Отправление',
            'Прибытие'    => 'Прибытие',
            // format 1
            'Отправление / Departing'                  => ['Отправление / Departing', 'Departing / Отправление'],
            'Прибытие / Arriving'                      => ['Прибытие / Arriving', 'Arriving / Прибытие'],
            'Маршрутная квитанция'                     => ['Маршрутная квитанция', 'Маршрутная кв итанция'],
            'Номер бронирования / Reservation number:' => [
                'Номер бронирования / Reservation number:',
                'Номер бронирования / Reservation num ber:',
                'Reservation number / Номер бронирования:',
                'Reservation num ber / Номер бронирования:',
            ],
            'Информация о пассажирах / Passenger information' => [
                'Информация о пассажирах / Passenger information',
                'Информация о пассаж ирах / Passenger inform ation',
                'Passenger information / Информация о пассажирах',
                'Passenger inform ation / Информация о пассаж ирах',
            ],
            'Itinerary information / Информация о маршруте' => [
                'Itinerary information / Информация о маршруте',
                'Itinerary inform ation / Информация о маршруте',
            ],
            'Дата выпуска билета / Ticket issue date:' => [
                'Дата выпуска билета / Ticket issue date:',
                'Ticket issue date / Дата выпуска билета:',
            ],
            'Багаж / Baggage'                => ['Багаж / Baggage', 'Baggage / Багаж'],
            'Тариф / Fare:'                  => ['Тариф / Fare:', 'Fare / Тариф:'],
            'Таксы, сборы / Taxes, fees:'    => ['Таксы, сборы / Taxes, fees:', 'Taxes, fees/ Таксы, сборы:'],
            'Итого к оплате / Total to pay:' => [
                'Итого к оплате / Total to pay:',
                'Total am ount / Общая сумма:',
                'Total amount / Общая сумма:',
            ],
            // format 2
            // formtat 3
            //            'ЭЛЕКТРОННЫЙ БИЛЕТ'
        ],
    ];
    private $keywordProv = 'KUPIBILET';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $orders = [];
        $receipts = [];
        $etickets = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->detectBody($text, false) && $this->assignLang($text, false)) {
                        $receipts[] = $text;
                    } elseif ($this->stripos($text,
                            $this->t('ЭЛЕКТРОННЫЙ БИЛЕТ')) // it-38730691.eml - receipt like order = eticket
                        && $this->detectBody($text, true)
                        && $this->assignLang($text, true)
                    ) {
                        $etickets[] = $text;
                    } elseif ($this->detectBody($text, true) && $this->assignLang($text, true)) {
                        $orders[] = $text;
                    }
                }
            }
        } else {
            return null;
        }

        foreach ($orders as $order) {
            $this->parseEmail_Order($order, $email, $receipts);
        }

        if (empty($orders)) {
            if (!empty($receipts)) {
                foreach ($receipts as $receipt) {
                    $this->assignLang($receipt, false);

                    if ($this->stripos($receipt, $this->t('Отправление / Departing'))) {
                        $this->parseEmail_Receipt_1($receipt, $email);
                    } else {
                        $this->parseEmail_Receipt_2($receipt, $email);
                    }
                }
            } elseif (!empty($etickets)) {
                foreach ($receipts as $receipt) {
                    $this->assignLang($receipt, true);
                    $this->parseEmail_ETicket($receipt, $email);
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, $this->keywordProv) !== false)) {
                if ($this->detectBody($text, true) && $this->assignLang($text, true)) {
                    return true;
                }
            } else {
                if ($this->detectBody($text, false) && $this->assignLang($text, false)) {
                    return true;
                }
            }
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
                if (($fromProv || $this->stripos($headers["subject"], $this->keywordProv))
                    && stripos($headers["subject"], $reSubject) !== false
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
        $formats = 3; // order | 2 receipts | eticket - not described yet
        $cnt = $formats * count(self::$dict);

        return $cnt;
    }

    private function parseEmail_Order(string $textPdf, Email $email, array $receipts = [])
    {
        $this->logger->debug(__METHOD__);
        $r = $email->add()->flight();

        //FE: it-38654299.eml - cirilic in pnr
        $confNo = $this->re("#{$this->opt($this->t('НОМЕР БРОНИРОВАНИЯ (PNR)'))}[:\s]+([\w]{5,6}\b)#",
            $textPdf);

        if (!empty($confNo)) {
            $r->general()
                ->confirmation($confNo, $this->t('НОМЕР БРОНИРОВАНИЯ (PNR)'), true);
        }
        $r->general()
            ->status($this->re("#{$this->t('Статус бронирования')}[: ]+(.+)#u", $textPdf));

        if (!empty($addConfNo = $this->re("#{$this->opt($this->t('НОМЕР БРОНИРОВАНИЯ А/К'))}[:\s]+([A-Z\d]{5,})#u",
                $textPdf)) && $addConfNo !== $confNo // it-38659604.eml -> $addConfNo === $confNo
        ) {
            $r->general()
                ->confirmation($addConfNo, $this->t('НОМЕР БРОНИРОВАНИЯ А/К'));
        } elseif (!empty($addConfNo = $this->re("#{$this->opt($this->t('НОМЕР БРОНИРОВАНИЯ А/К'))}[:\s]+[A-Z\d]{5,},[ ]*([A-Z\d]{5,})#u",
                $textPdf)) && $addConfNo !== $confNo // it-38730691.eml           НОМЕР БРОНИРОВАНИЯ А/К: HCTFPM, BQWUGE
        ) {
            $r->general()
                ->confirmation($addConfNo, $this->t('НОМЕР БРОНИРОВАНИЯ А/К'));
        }
        $r->ota()
            ->confirmation($this->re("#{$this->t('НОМЕР ЗАКАЗА KUPIBILET')}[:\s]+(\d{5,})#u", $textPdf),
                $this->t('НОМЕР ЗАКАЗА KUPIBILET'))
            ->phone($this->re("#{$this->t('Телефон')}[\s:]+([\d\-\+\(\) ]+?)\s+{$this->t('Сайт: kupibilet.ru')}#u",
                $textPdf));

        $paxInfo = $this->findCutSection($textPdf, $this->t('Данные полёта'), $this->t('Информация о маршруте'));
        $rowsPax = $this->splitter("#(.+[ ]{3,}(?:[\d\-]{7,}|[A-Z\d]{5,6}))\n#u", $paxInfo . "\n\n\n", true);
        $pos = $this->colsPos($this->re("#(.+)#", $paxInfo));

        if (count($pos) !== 4) {
            $this->logger->debug('other format pax-table');

            return false;
        }

        foreach ($rowsPax as $rowPax) {
            $table = $this->splitCols($rowPax, $pos);
            $pax = $this->nice(preg_replace('#\d#', '', $table[0]));

            if (!empty(trim($pax))) {
                $r->general()
                    ->traveller($pax, true);
            }
            $ticket = $this->re("#^(\d[\d\- ]+\d)#u", trim($table[3]));

            if (!empty($ticket)) {
                $r->issued()
                    ->ticket($ticket, false);
            }
        }

        $itInfo = $this->findCutSection($textPdf, $this->t('Информация о маршруте'),
            $this->t('Время вылета и прилета указано местное'));
        $segments = $this->splitter("#\n([ ]*{$this->t('Рейс')}[ ]+(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])[\- ]*\d+)#u", $itInfo);

        foreach ($segments as $i => $segment) {
            $segment = str_replace("►", " ", $segment);

            if (!empty($str = strstr($segment, $this->t('Пересадка в'), true))) {
                $segment = $str;
            }
            $table = $this->splitCols($segment, $this->colsPos($segment));

            if (count($table) !== 4) {
                $this->logger->debug('other format segment-' . $i);

                return false;
            }
            $s = $r->addSegment();

            if (preg_match("#{$this->t('Рейс')}[ ]+([A-Z\d][A-Z]|[A-Z][A-Z\d])[\- ]*(\d+)\s+(.+?)\s+\(([A-Z]{1,2})\)#",
                $table[0], $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
                $s->extra()
                    ->cabin($m[3])
                    ->bookingCode($m[4]);
            }
            $s->extra()
                ->aircraft($this->re("#{$this->t('Самолёт')}\s+(.+)#", $table[0]), false, true)
                ->duration(trim($table[2]));

            if (preg_match("#(.+?)\s+(\d+:\d+.+?\s+\d{4})\s+(.+?)\s+([A-Z]{3})\s*(?:{$this->t('Терминал')}[:\s]*(.+)\s*)?$#us",
                $table[1], $m)) {
                $s->departure()
                    ->name($this->nice($m[1] . ', ' . $m[3]))
                    ->code($m[4])
                    ->date($this->normalizeDate($m[2]));

                if (isset($m[5]) && !empty($m[5])) {
                    $s->departure()->terminal($m[5]);
                }
            }

            if (preg_match("#(.+?)\s+(\d+:\d+.+?\s+\d{4})\s+(.+?)\s+([A-Z]{3})\s*(?:{$this->t('Терминал')}[:\s]*(.+)\s*)?$#us",
                $table[3], $m)) {
                $s->arrival()
                    ->name($this->nice($m[1] . ', ' . $m[3]))
                    ->code($m[4])
                    ->date($this->normalizeDate($m[2]));

                if (isset($m[5]) && !empty($m[5])) {
                    $s->arrival()->terminal($m[5]);
                }
            }
        }

        $sumInfo = $this->findCutSection($textPdf, $this->t('Сведения об оплате'), $this->t('Контакты'));

        if (preg_match("#(.+?)\n\n\n(.+)#s", $sumInfo, $m)) {
            $sumInfo = $m[1];
            $extSumInfo = $m[2];
            $node = $this->re("#{$this->t('Бонусные баллы')}[\s:]+\- (\d[\d\.\, ]+\D+?)(?:\n|$)#u", $extSumInfo);

            if ($node) {
                $discount = $this->getTotalCurrency($node);
                $r->price()
                    ->discount($discount['Total']);
            }
            $fees = (array) $this->t('fees');

            foreach ($fees as $fee) {
                $node = $this->re("#{$fee}[\s:]+\+ (\d[\d\.\, ]+\D+?)(?:\n|$)#u", $extSumInfo);

                if ($node) {
                    $sum = $this->getTotalCurrency($node);
                    $r->price()
                        ->fee($fee, $sum['Total']);
                }
            }
            $earned = $this->re("#{$this->t('С этой покупки вы получили')}[ ]*(\d[\d\.\, ]+\D+?)\.?(?:\n|$)#u",
                $extSumInfo);

            if ($earned) {
                $r->program()->earnedAwards($earned);
            }
        }

        $sum = $this->getTotalCurrency($this->re("#[ ]{5,}(\d[\d\.\, ]+\D+?)\s*$#u", $sumInfo));
        $r->price()
            ->total($sum['Total'])
            ->currency($sum['Currency']);

        if (!empty($receipts)) {
            foreach ($receipts as $receipt) {
                if ($this->stripos($receipt, $this->t('Отправление / Departing'))) {
                    // format 1: get ext-info
                    $confNo = $this->re("#{$this->opt($this->t('Номер бронирования / Reservation number:'))}[ ]+([A-Z\d]{5,})#u",
                        $receipt);

                    if ($r->getPrimaryConfirmationNumberKey() !== $confNo) {
                        continue;
                    }
                    $cost = 0.0;

                    if (preg_match_all("#\b{$this->opt($this->t('Тариф / Fare:'))}[ ]*(.+?)(?:[ ]{2,}|\n)#u", $receipt,
                        $m)) {
                        foreach ($m[1] as $value) {
                            $sum = $this->getTotalCurrency($value);

                            if ($r->getPrice()->getCurrencyCode() !== $sum['Currency']) {
                                $cost = 0;

                                break;
                            }
                            $cost += $sum['Total'];
                        }
                    }
                    $tax = 0.0;

                    if (preg_match_all("#{$this->opt($this->t('Таксы, сборы / Taxes, fees:'))}[ ]*(.+?)(?:[ ]{2,}|\n)#u",
                        $receipt, $m)) {
                        foreach ($m[1] as $value) {
                            $sum = $this->getTotalCurrency($value);

                            if ($r->getPrice()->getCurrencyCode() !== $sum['Currency']) {
                                $tax = 0;

                                break;
                            }
                            $tax += $sum['Total'];
                        }
                    }
                    $total = 0.0;

                    if (preg_match_all("#{$this->opt($this->t('Итого к оплате / Total to pay:'))}[ ]*(.+?)(?:[ ]{2,}|\n)#u",
                        $receipt, $m)) {
                        foreach ($m[1] as $value) {
                            $sum = $this->getTotalCurrency($value);

                            if ($r->getPrice()->getCurrencyCode() !== $sum['Currency']) {
                                $total = 0;

                                break;
                            }
                            $total += $sum['Total'];
                        }
                    }

                    if (!empty($tax)) {
                        $r->price()
                            ->tax($tax);
                    }

                    if (!empty($total) && !empty($cost)) {
                        if ($r->getPrice()->getTotal() === $total) {
                            $r->price()
                                ->cost($cost);
                        } else {
                            $r->price()
                                ->cost($total);
                        }
                    }
                } else {
                    // format 2: get ext-info
                    $confNo = $this->re("#{$this->opt($this->t('Номер бронирования в GDS'))}[ ]+([A-Z\d]{5,})#u",
                        $receipt);

                    if ($r->getPrimaryConfirmationNumberKey() !== $confNo) {
                        continue;
                    }
                    $sumInfo = $this->findCutSection($receipt, $this->t('Информация о тарифе'), null);
                    $sum = $this->getTotalCurrency($this->re("#\n[ ]*{$this->t('Таксы')}[ ]{5,}(.+)#u", $sumInfo));

                    if ($r->getPrice()->getCurrencyCode() === $sum['Currency']) { // it-39171157.eml different currency
                        $r->price()
                            ->tax($sum['Total']);
                        $sum = $this->getTotalCurrency($this->re("#\n[ ]*{$this->t('Итого')}[ ]{5,}(.+)#u", $sumInfo));

                        if ($r->getPrice()->getTotal() === $sum['Total']) {
                            $sum = $this->getTotalCurrency($this->re("#\n[ ]*{$this->t('Тариф')}[ ]{5,}(.+)#u",
                                $sumInfo));
                            $r->price()
                                ->cost($sum['Total']);
                        } else {
                            $r->price()
                                ->cost($sum['Total']);
                        }
                    }
                }
            }
        }

        return true;
    }

    private function parseEmail_ETicket(string $textPdf, Email $email)
    {
        $this->logger->debug(__METHOD__);
        // it-38730691.eml - receipt like order = eticket
        //TODO:
        return true;
    }

    private function parseEmail_Receipt_1(string $textPdf, Email $email)
    {
        $this->logger->debug(__METHOD__);
        $this->logger->critical($textPdf);
        $dateDeps = [];
        $receipts = $this->splitter("#(.*{$this->opt($this->t('Маршрутная квитанция'))})#u", "CtrStr\n" . $textPdf);

        foreach ($receipts as $receipt) {
            if (preg_match("#{$this->opt($this->t('Отправление / Departing'))}.+\n(.+?)[ ]{2,}.+\n[ ]{0,3}(\d+:\d+)#",
                    $receipt, $m)
                || preg_match("#{$this->opt($this->t('Отправление / Departing'))}.+\n(.+?)[ ]{2,}.+\n.+\n[ ]{0,3}(\d+:\d+)#",
                    $receipt, $m)
            ) {
                $dateDep = $this->normalizeDate($m[2] . ', ' . $m[1]);

                if (in_array($dateDep, $dateDeps)) {
                    $this->logger->debug("skip E-ticket (but pax added). already parsed on dateDep");
                    $res = $this->parsePax_Receipt_1($receipt);
                    $passengers = $res['passengers'];
                    $tickets = $res['tickets'];
                    $confNo = $this->re("#{$this->opt($this->t('Номер бронирования / Reservation number:'))}[ ]+([A-Z\d]{5,})#u",
                        $receipt);
                    $res = $this->searchReservation($email, $confNo, $dateDep);

                    if ($res) {
                        $res->general()
                            ->travellers($passengers, true);
                        $res->issued()->tickets($tickets, false);

                        if (preg_match("#\b{$this->opt($this->t('Тариф / Fare:'))}[ ]*(.+?)(?:[ ]{2,}|\n)#u", $receipt,
                            $m)) {
                            $cost = $this->getTotalCurrency($m[1]);
                        }

                        if (preg_match("#{$this->opt($this->t('Таксы, сборы / Taxes, fees:'))}[ ]*(.+?)(?:[ ]{2,}|\n)#u",
                            $receipt, $m)) {
                            $tax = $this->getTotalCurrency($m[1]);
                        }

                        if (preg_match("#{$this->opt($this->t('Итого к оплате / Total to pay:'))}[ ]*(\S.+?)(?:[ ]{2,}|\n|$)#u",
                            $receipt, $m)) {
                            $total = $this->getTotalCurrency($m[1]);
                        }

                        if ($res->getPrice()
                            && isset($cost, $tax, $total)
                            && !empty($total['Total'])
                            && !empty($cost['Total'])
                            && $res->getPrice()->getTotal()
                            && $res->getPrice()->getCost()
                            && $res->getPrice()->getCurrencyCode() === $total['Currency']
                        ) {
                            $res->price()
                                ->total($res->getPrice()->getTotal() + $total['Total'])
                                ->cost($res->getPrice()->getCost() + $cost['Total']);
                            $fees = $res->getPrice()->getFees();
                            $res->getPrice()->removeFee('Tax');

                            foreach ($fees as $fee) {
                                if ($fee[0] === 'Tax') {
                                    $res->price()
                                        ->tax($fee[1] + $tax['Total']);

                                    break;
                                }
                            }
                        } elseif ($res->getPrice()) {
                            $res->removePrice();
                        }
                    }

                    continue;
                } else {
                    $dateDeps[] = $dateDep;
                }
            } else {
                $this->logger->debug("other format Receipt_1");

                return false;
            }

            $r = $email->add()->flight();
            $confNo = $this->re("#{$this->opt($this->t('Номер бронирования / Reservation number:'))}[ ]+([A-Z\d]{5,})#u",
                $receipt);
            $descr = $this->re("#({$this->opt($this->t('Номер бронирования / Reservation number:'))})[ ]+[A-Z\d]{5,}#u",
                $receipt);
            $r->general()
                ->confirmation($confNo, $this->re("#(.+?)\s*(?:\/|$)#", trim($descr, ":")), true)
                ->date($this->normalizeDate($this->re("#{$this->opt($this->t('Дата выпуска билета / Ticket issue date:'))}[ ]+(.+)#u",
                    $receipt)));
            $res = $this->parsePax_Receipt_1($receipt);
            $passengers = $res['passengers'];
            $tickets = $res['tickets'];
            $r->general()
                ->travellers($passengers, true);
            $r->issued()->tickets($tickets, false);

            if (preg_match("#\b{$this->opt($this->t('Тариф / Fare:'))}[ ]*(.+?)(?:[ ]{2,}|\n)#u", $receipt,
                $m)) {
                $sum = $this->getTotalCurrency($m[1]);
                $r->price()
                    ->cost($sum['Total'])
                    ->currency($sum['Currency']);
            }

            if (preg_match("#{$this->opt($this->t('Таксы, сборы / Taxes, fees:'))}[ ]*(.+?)(?:[ ]{2,}|\n)#u",
                $receipt, $m)) {
                $sum = $this->getTotalCurrency($m[1]);
                $r->price()
                    ->tax($sum['Total'])
                    ->currency($sum['Currency']);
            }

            if (preg_match("#{$this->opt($this->t('Итого к оплате / Total to pay:'))}[ ]*(.+?)(?:[ ]{2,}|\n)#u",
                $receipt, $m)) {
                $sum = $this->getTotalCurrency($m[1]);
                $r->price()
                    ->total($sum['Total'])
                    ->currency($sum['Currency']);
            }

            $segments = $this->splitter("#([ ]*{$this->opt($this->t('Отправление / Departing'))})#u", $receipt);

            foreach ($segments as $segment) {
                $s = $r->addSegment();
                $itBlock = $this->re("#{$this->opt($this->t('Отправление / Departing'))}[^\n]+\n(.+?)\n[ ]*{$this->opt($this->t('Багаж / Baggage'))}#us",
                    $segment);
                $table = $this->splitCols($itBlock, $this->colsPos($itBlock));

                if (count($table) !== 6) {
                    $this->logger->debug('other format segments Receipt_1');

                    return false;
                }
                $this->logger->critical(var_export($table, true));
                $s->departure()
                    ->date($this->normalizeDate($this->nice($table[0])));
                $s->arrival()
                    ->date($this->normalizeDate($this->nice($table[2])));

                // Burgas, Bulgaria Burgas(BOJ)
                // Москва, Домодедово / Moscow, Domodedovo  Terminal:
                // departure
                if (preg_match("#(?:.*\s*\/)?\s*(.+?)\s*(?:\(([A-Z]{3})\))?\s*(?:Terminal:(.*))?$#u",
                    $this->nice($table[1]), $m)) {
                    $s->departure()
                        ->name($m[1]);

                    if (isset($m[2]) && !empty($m[2])) {
                        $s->departure()->code($m[2]);
                    } else {
                        $s->departure()->noCode();
                    }

                    if (isset($m[3]) && !empty($m[3])) {
                        $s->departure()->terminal($m[3]);
                    }
                }
                // arrival
                if (preg_match("#(?:.*\s*\/)?\s*(.+?)\s*(?:\(([A-Z]{3})\))?\s*(?:Terminal:(.*))?$#u",
                    $this->nice($table[3]), $m)) {
                    $s->arrival()
                        ->name($m[1]);

                    if (isset($m[2]) && !empty($m[2])) {
                        $s->arrival()->code($m[2]);
                    } else {
                        $s->arrival()->noCode();
                    }

                    if (isset($m[3]) && !empty($m[3])) {
                        $s->arrival()->terminal($m[3]);
                    }
                }
                // airline
                if (preg_match("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\-\s*(\d+)\s*(?:.*?)(?:\n([^\n]+))?$#su", $table[4],
                    $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);

                    if (isset($m[3]) && !empty($m[3])) {
                        $s->extra()->aircraft($m[3]);
                    }
                }
                //duration
                if (preg_match("#{$this->opt($this->t('Время в пути / Flight time:'))}[ ]+(.+?)[ ]{3,}#", $segment,
                    $m)) {
                    $s->extra()->duration($this->re("#(.+?)\s*(?:\/|$)#", $m[1]));
                }
                // cabin, bookingCode
                if (preg_match("#^([A-z]{1,2})\s+\((.+)\)$#", $this->nice($table[5]), $m)) {
                    $s->extra()
                        ->bookingCode($m[1])
                        ->cabin($m[2]);
                }
            }
        }

        return true;
    }

    private function parsePax_Receipt_1(string $text): array
    {
        $paxBlock = $this->re("#{$this->opt($this->t('Информация о пассажирах / Passenger information'))}\s+(.+?)\n\n\s*(?:[\w ]+\-[\w ]+\/[\w ]+\-[\w ]+|[ ]*{$this->opt($this->t('Itinerary information / Информация о маршруте'))})\n#su",
            $text);
        $rows = $this->splitter("#(.*\d[\d\- ]+\d[ ]*\n)#u", $paxBlock . "\n\n", true);
        $passengers = [];
        $tickets = [];

        foreach ($rows as $row) {
            $table = $this->splitCols($row);

            if (count($table) < 2 || count($table) > 3) {
                $this->logger->debug('other format parsePax_Receipt_1');
                $passengers = [];
                $tickets = [];

                break;
            }
            $name = $this->nice(preg_replace("#\d#", '', $table[0]));

            if (!empty($name) && preg_match("#^[\w \/,]+$#", $name)) {
                $passengers[] = $this->re("#(.+?)\s*(?:\/|$)#", $name);
            }

            if (preg_match("#^\d[\d\- ]+\d$#", trim(end($table)))) {
                $tickets[] = trim(end($table));
            }
        }

        return ['passengers' => $passengers, 'tickets' => $tickets];
    }

    private function searchReservation(Email $email, string $confNo, $depDate): ?Flight
    {
        foreach ($email->getItineraries() as $it) {
            if ($it->getType() == 'flight') {
                /** @var Flight $r */
                $r = $it;

                if ($r->getPrimaryConfirmationNumberKey() === $confNo) {
                    foreach ($r->getSegments() as $seg) {
                        if ($seg->getDepDate() === $depDate) {
                            return $r;
                        }
                    }
                }
            }
        }

        return null;
    }

    private function parseEmail_Receipt_2(string $textPdf, Email $email)
    {
        //TODO: remake. look at it-39171157.eml (attach 2) (parse maybe like receipt1)
        $this->logger->debug(__METHOD__);
        $r = $email->add()->flight();

        $confNo = $this->re("#{$this->opt($this->t('Номер бронирования в GDS'))}[ ]+([A-Z\d]{5,})#u", $textPdf);

        if (!empty($confNo)) {
            $r->general()
                ->confirmation($confNo, $this->t('Номер бронирования в GDS'), true);
        }

        $r->general()
            ->status($this->re("#{$this->t('Статус бронирования')}[ ]+(.+)#u", $textPdf))
            ->date($this->normalizeDate($this->re("#{$this->t('Дата оформления')}[ ]+(.+)#u", $textPdf)));

        if (!empty($addConfNo = $this->re("#{$this->opt($this->t('Номер подтверждения А/К'))}[ ]+([A-Z\d]{5,})#u",
            $textPdf))
        ) {
            $r->general()
                ->confirmation($addConfNo, $this->t('Номер подтверждения А/К'));
        }

        $pages = $this->splitter("#({$this->opt($this->t('Маршрутная квитанция'))})#u", "CtrlStr\n" . $textPdf);
        $segments = [];

        foreach ($pages as $page) {
            if (!empty($str = strstr($page, "+ BAGGAGE DISCOUNTS MAY APPLY", true))) {
                $page = $str;
            }

            if (!empty($str = strstr($page, "Информация о тарифе", true))) {
                $page = $str;
            }
            $segmentsOnPage = $this->splitter("#\n([ ]*{$this->opt($this->t('Информация о перелете ['))})#u", $page);
            $segments = array_merge($segments, $segmentsOnPage);
        }

        $r->general()
            ->traveller($this->re("#{$this->t('Пассажир')}[ ]+(.+)#u", $textPdf), true);
        $r->issued()
            ->ticket($this->re("#{$this->t('Номер электронного билета')}[ ]+([\d\-]+)#u", $textPdf), false);

        foreach ($segments as $i => $segment) {
            $s = $r->addSegment();

            if (preg_match("#{$this->t('Рейс')}[ ]+.+?[ ]+\(([A-Z\d][A-Z]|[A-Z][A-Z\d])\)[ ]*(\d+)[ ]+\*[ ]+{$this->t('Выполняет')}[ ].+?[ ]+\(([A-Z\d][A-Z]|[A-Z][A-Z\d])\)#u",
                $segment, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                    ->operator($m[3]);
            }

            if (preg_match("#{$this->t('Отправление')}[ ]+(.+)\s+\(([A-Z]{3})\),\s+(?:(?i){$this->t('Терминал')}\s+(.+?)\s*\*\s*)?(\d+.+?\s+\d{4})#u",
                $segment, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2])
                    ->date($this->normalizeDate($m[4]));

                if (isset($m[3]) && !empty($m[3])) {
                    $s->departure()->terminal($m[3]);
                }
            }

            if (preg_match("#{$this->t('Прибытие')}[ ]+(.+)\s+\(([A-Z]{3})\),\s+(?:(?i){$this->t('Терминал')}\s+(.+?)\s*\*\s*)?(\d+.+?\s+\d{4})#u",
                $segment, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2])
                    ->date($this->normalizeDate($m[4]));

                if (isset($m[3]) && !empty($m[3])) {
                    $s->arrival()->terminal($m[3]);
                }
            }

            if (preg_match("#{$this->t('Класс')}[ ]+(.+)\s+\(([A-Z]{1,2})\)#u", $segment, $m)) {
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2]);
            }
        }

        $sumInfo = $this->findCutSection($textPdf, $this->t('Информация о тарифе'), null);
        $sum = $this->getTotalCurrency($this->re("#\n[ ]*{$this->t('Тариф')}[ ]{5,}(.+)#u", $sumInfo));
        $r->price()
            ->cost($sum['Total']);
        $sum = $this->getTotalCurrency($this->re("#\n[ ]*{$this->t('Таксы')}[ ]{5,}(.+)#u", $sumInfo));
        $r->price()
            ->tax($sum['Total']);
        $sum = $this->getTotalCurrency($this->re("#\n[ ]*{$this->t('Итого')}[ ]{5,}(.+)#u", $sumInfo));
        $r->price()
            ->total($sum['Total'])
            ->currency($sum['Currency']);

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //23:30 16 апр. 2019   |   06:55 14 авг 2019
            '#^(\d+:\d+)\s+(\d+)\s+(\w+)\.?\s+(\d{4})$#u',
            //23:30 16 апр. 2019   |   06:55 14 авг 2019
            '#^(\d+)\s+(\w+)\.?\s+(\d{4})$#u',
            //25.05.2019 13:01
            '#^(\d+)\.(\d+)\.(\d{4})\s+(\d+:\d+)\s*$#u',
        ];
        $out = [
            '$2 $3 $4, $1',
            '$1 $2 $3',
            '$3-$2-$1 $4',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body, $isOrder = true)
    {
        if ($isOrder && isset($this->reBodyOrder)) {
            foreach ($this->reBodyOrder as $lang => $reBody) {
                if ($this->stripos($body, $reBody)) {
                    $this->lang = $lang;

                    return true;
                }
            }
        } elseif (!$isOrder && isset($this->reBodyReceipt)) {
            foreach ($this->reBodyReceipt as $lang => $reBody) {
                if ($this->stripos($body, $reBody)) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body, $isOrder = true)
    {
        foreach (self::$dict as $lang => $words) {
            if ($isOrder && isset($words['РЕЙСЫ'], $words['ВЫЛЕТ'])) {
                if ($this->stripos($body, $words['РЕЙСЫ']) && $this->stripos($body, $words['ВЫЛЕТ'])) {
                    $this->lang = $lang;

                    return true;
                }
            } elseif (isset($words['Отправление'], $words['Прибытие'])) {
                if ($this->stripos($body, $words['Отправление']) && $this->stripos($body, $words['Прибытие'])) {
                    $this->lang = $lang;

                    return true;
                }
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

    private function findCutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;

        if ($searchStart) {
            $left = mb_strstr($input, $searchStart);
        } else {
            $left = $input;
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } else {
            if (!empty($searchFinish)) {
                $inputResult = mb_strstr($left, $searchFinish, true);
            } else {
                $inputResult = $left;
            }
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace(["€", "£", "$", "₹", "р."], ["EUR", "GBP", "USD", "INR", "RUB"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text, $withEmpty = false)
    {
        $result = [];

        if ($withEmpty) {
            $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        } else {
            $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        }
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
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

    private function rowColsPos($row)
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

    private function colsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (!isset($prev) || $prev < 0) {
                $prev = $i - 1;
            }

            if (isset($pos[$i], $pos[$prev])) {
                if ($pos[$i] - $pos[$prev] < $correct) {
                    unset($pos[$i]);
                } else {
                    $prev = $i;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }
}
