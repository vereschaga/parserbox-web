<?php

namespace AwardWallet\Engine\s7\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationHtml2016 extends \TAccountChecker
{
    public $mailFiles = "s7/it-3985532.eml, s7/it-491775720.eml, s7/it-5023806.eml, s7/it-506390356.eml, s7/it-526904828.eml, s7/it-5313348.eml, s7/it-535304832.eml, s7/it-6604220.eml, s7/it-6722473.eml, s7/it-6972530.eml, s7/it-8806042.eml";

    private $detects = [
        'en' => [
            'All information relevant to your booking is available',
            'S7 Airlines mobile app keeps you up-to-date with your flight',
            'Accumulate miles for each flight to spend accumulated miles for premium tickets S7 Priority',
        ],
        'ru' => [
            'Скачайте приложение S7',
            'Вся информация о вашем перелете в приложении S7 Airlines',
            'Документы, подтверждающие оплату услуг, вы найдете во вложении',
            'Все необходимые для путешествия документы вы найдете во вложении',
            'Если этого не произойдет, пожалуйста, свяжитесь с Контактным центром S7 Airlines',
        ],
    ];

    private static $dict = [
        'en' => [
            'document'      => 'Document',
            'eTicket'       => ['E-ticket number', 'ETK number'],
            'ffNumber'      => 'FFP number',
            'paid'          => ['Paid', 'Sum to pay'],
            'locator'       => ['Order №', 'Reservation', 'Order'],
            'success'       => 'successfully',
            'tarif'         => 'Basic',
            'travellers'    => 'Travellers',
            'Your booking'  => ['Your booking', 'Your order'],
            'Бронирование:' => ['Pnr:'],
        ],
        'ru' => [
            'document'      => ['Документ', 'Паспорт'],
            'eTicket'       => 'Номер билета',
            'ffNumber'      => 'Номер ЧЛП',
            'paid'          => ['Оплачено', 'К оплате'],
            'locator'       => ['аказ №', 'Бронирование №', 'Бронь', 'Заказ'],
            'success'       => 'успешно',
            'tarif'         => 'Гибкий',
            'travellers'    => 'Путешественники',
            'Your booking'  => ['Состав брони', 'Состав заказа'],
            'Бронирование:' => ['Бронирование:'],
        ],
    ];

    private $reSubject = [
        'Booking confirmation on www.s7.ru',
        'Подтверждение оплаты заказа на сайте www.s7.ru.',
        'Подтверждение покупки на сайте www.s7.ru',
    ];

    private $lang = '';

    private $segmentsFromPdf = [];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectBody($parser);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $pdfs = $parser->searchAttachmentByName('.*pdf');
        $patterns = [
            'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $total = true;
        $tickets = $ffNumbers = [];

        foreach ($pdfs as $pdf) {
            $body = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (false !== stripos($body, 'Excess baggage allowance payment')) {
                $total = false;
            }

            $segmentText = '';

            if (false !== stripos($body, 'Seat assignment')) {
                $segmentText = $this->cutText('Seat assignment', ['Manage your booking'], $body);
            } elseif (false !== stripos($body, 'Route')) {
                $segmentText = $this->cutText('Route', ['Manage your booking'], $body);
            } elseif (false !== stripos($body, 'Excess baggage allowance payment')) {
                $segmentText = $this->cutText('Excess baggage allowance payment', ['Manage your booking'], $body);
            }

            $segments = $this->splitText($segmentText, '/((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+\s+\d{1,2}\s+\w+\.?\s+\d{1,2}\s+\w.+)/u', true);
            $psngInfo = $this->cutText('Passengers', ['Manage your booking'], $body);
            $psngInfoV2 = null;
            $priceInfo = stristr($body, 'Manage your booking');

            if (preg_match('/(?:^\s*|.{20,}[ ]{5,}|.{20,}\S \/ )PNR[: ]*\n(?:.*\n){0,2}?.{20,}[ ]{5,}([A-Z\d]{5,10})\n/', $body, $m)
                || preg_match('/(?:^\s*|.{20,}[ ]{5,}|.{20,}\S \/ )Order[: ]*\n(?:.*\n){0,2}?.{20,}[ ]{5,}([A-Z\d]{5,10})\n/', $body, $m)
                || preg_match('/\sPNR[ ]*:[ ]*([A-Z\d]{5,10})\n/', $body, $m)
            ) {
                $order = $m[1];
            } else {
                $order = null;
            }

            if (!empty($order)) {
                if (preg_match("/\sETK\s*:\s*({$patterns['eTicket']})(?:[ ]{2}|\n)/", $psngInfo, $m)) {
                    // it-6604220.eml
                    $tickets[$order][] = $m[1];
                } else {
                    // it-5313348.eml
                    $psngInfoV2 = preg_match("/^[ ]*(?:Пассажиры|(?:\S[^\/\n]*\S \/ )?Passengers)(?:[ ]{2}.+)?\n+([\s\S]+?)\n+[ ]*(?:Маршрут|(?:\S[^\/\n]*\S \/ )?Route)(?:[ ]{2}|$)/m", $body, $m) ? $m[1] : null;

                    $paxTablePos = [0];

                    if (preg_match("/^((([ ]{20,}) {$this->opt($this->t('document'))}[ ]+) {$this->opt($this->t('eTicket'))}[ ]+) {$this->opt($this->t('ffNumber'))}(?:[ ]{2}|\n)/m", $psngInfoV2, $matches)) {
                        $paxTablePos[] = mb_strlen($matches[3]);
                        $paxTablePos[] = mb_strlen($matches[2]);
                        $paxTablePos[] = mb_strlen($matches[1]);
                    } elseif (preg_match("/^(([ ]{20,}) {$this->opt($this->t('document'))}[ ]+) {$this->opt($this->t('eTicket'))}(?:[ ]{2}|\n)/m", $psngInfoV2, $matches)) {
                        $paxTablePos[] = mb_strlen($matches[2]);
                        $paxTablePos[] = mb_strlen($matches[1]);
                    }

                    if (preg_match("/^(.{50,}[ ]+) (?:Дата брони|Оформлено|(?:\S[^\/\n]*\S \/ )?Date|(?:\S[^\/\n]*\S \/ )?Issued)$/m", $psngInfoV2, $matches)) {
                        $paxTablePos[] = mb_strlen($matches[1]);
                    }

                    $paxTable = $this->splitCols($psngInfoV2, $paxTablePos);

                    if (count($paxTable) > 2
                        && preg_match("/(?:^\s*|\n[ ]*){$this->opt($this->t('eTicket'))}\n+[ ]*({$patterns['eTicket']})\s*(?:\n|$)/", $paxTable[2], $m)
                    ) {
                        $tickets[$order][] = $m[1];
                    }

                    if (count($paxTable) > 3
                        && preg_match("/(?:^\s*|\n[ ]*){$this->opt($this->t('ffNumber'))}\n+[ ]*([-A-Z\d]{4,})\s*$/", $paxTable[3], $m)
                    ) {
                        $ffNumbers[$order][] = $m[1];
                    }
                }

                if (preg_match('/.{20,}[ ]{5,}(?:Fare|Тариф)[ ]{5,}([\d, ]+)[ ]*(\w{3})[.\s]*\n/u', $priceInfo, $m)) {
                    $costs[$order][] = (float) str_replace([',', ' '], '', $m[1]);
                    $currency = str_ireplace(['Руб'], ['RUB'], $m[2]);
                }

                if (preg_match('/.{20,}[ ]{5,}(?:Taxes|Таксы)[ ]{5,}([\d, ]+)[ ]*(\w{3})[.\s]*\n/u', $priceInfo, $m)) {
                    $taxes[$order][] = (float) str_replace([',', ' '], '', $m[1]);
                    $currency = str_ireplace(['Руб'], ['RUB'], $m[2]);
                }
            }

            $year = preg_match($pattern = '/\s+\d{1,2} [[:alpha:]]+\.? (\d{2,4})\b/u', $psngInfo, $m)
            || preg_match($pattern, $psngInfoV2, $m) ? $m[1] : null;

            foreach ($segments as $segment) {
                $sTable = $this->splitCols($segment);

                if (!empty($year) && preg_match('/(\d{1,2}\s+[[:alpha:]]+\.?)\s+(\d{1,2}:\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)/u', $sTable[1], $m) && ($dDate = strtotime($this->normalizeDate($m[1] . ' ' . $year . ', ' . $m[2])))) {
                    $this->segmentsFromPdf[$dDate] = $sTable;
                }
            }
        }

        $nodes = $this->http->XPath->query('//text()[' . $this->eq($this->t('locator')) . ']/ancestor::*[.//text()[' . $this->eq($this->t('Your booking')) . ']][1]');

        foreach ($nodes as $root) {
            $schemaOrgPNRs = array_filter($this->http->FindNodes("//*[normalize-space(@itemprop)='reservationNumber']/@content", null, "/^\s*(.*?)\s*$/"));

            if (count(array_unique($schemaOrgPNRs)) === 1) {
                $order = array_shift($schemaOrgPNRs);
            } else {
                $order = $this->http->FindSingleNode("(.//text()[{$this->eq($this->t('locator'))}]/following::text()[normalize-space()][1])[1]", $root)
                    ?? $this->http->FindSingleNode("(.//text()[{$this->contains($this->t('locator'))}])[1]", $root, false, '/\b([A-Z\d]{5,7})\s+/');
            }

            $passengersRows = array_filter($this->http->FindNodes(".//text()[{$this->eq($this->t('travellers'))}]/ancestor::*[not({$this->eq($this->t('travellers'))})][1][not({$this->contains($this->t('Your booking'))})]//p[not({$this->contains($this->t('travellers'))})]"
                . "|//img[contains(@src, '/male.png')]/following::text()[normalize-space(.)][1]", $root));

            $pnr = 0;
            $passengers = [];

            foreach ($passengersRows as $row) {
                if (preg_match("/{$this->opt($this->t('Бронирование:'))}\s*([A-Z\d]{5,7})\s*$/", $row, $m)) {
                    $pnr = $m[1];
                } else {
                    $row = preg_replace("/^\s*(Mr|Mrs|Miss|Mstr|Ms|Dr) /", '', $row);
                    $passengers[$pnr][] = $row;
                }
            }

            if (!empty($this->http->FindSingleNode("(.//text()[{$this->starts($this->t('Бронирование:'))}])[1]", $root))) {
                $xpath = ".//text()[{$this->starts($this->t('Бронирование:'))}][following::tr[1]//img[contains(@src,'/arrow.jpg') or contains(@src, '/arrow-route.jpg')]]";
                $count = count($this->http->FindNodes($xpath, $root));

                if ($count == 0) {
                    return false;
                }

                $seatsVal = $this->http->FindNodes('descendant::tr/*[descendant::img[contains(@src,"seat.png")] and normalize-space()=""]/following-sibling::*[normalize-space()]', $root);

                if (count($seatsVal) !== $count) {
                    $seatsVal = null;
                }

                for ($i = 1; $i <= $count; $i++) {
                    $f = $email->add()->flight();

                    $f->general()
                        ->confirmation($order, $this->http->FindSingleNode('//text()[' . $this->eq($this->t('locator')) . ']') ?? '');

                    $pnr = $this->http->FindSingleNode('(' . $xpath . ")[{$i}]", $root, true, "/{$this->opt($this->t('Бронирование:'))}\s*([A-Z\d]{5,7})\s*$/");
                    $f->general()
                        ->confirmation($pnr, trim($this->http->FindSingleNode("(.//text()[{$this->starts($this->t('Бронирование:'))}])[1]", $root, null, "/^\s*({$this->opt($this->t('Бронирование:'))})/"), ':'));

                    $f->general()
                        ->travellers($passengers[$pnr] ?? [], true);

                    $seatsAll = $seatsVal !== null ? preg_split('/(\s*,\s*)+/', $seatsVal[$i - 1]) : [];

                    $this->parseSegments($f, $root, $this->http->FindSingleNode('(' . $xpath . ")[{$i}]", $root), $seatsAll);

                    if (
                        !empty($this->http->FindSingleNode("(.//td[starts-with(normalize-space(.), 'Бронирование места в салоне')]/following-sibling::td[descendant::img[contains(@src, 'seat')]][1])[1]", $root))
                        || !empty($this->http->FindSingleNode("(.//td[starts-with(normalize-space(.), 'Extra luggage')]/following-sibling::td[descendant::img[contains(@src, 'baggage')]][1])[1]", $root))
                    ) {
                        $total = false;
                    }

                    $text = $this->http->FindSingleNode(".//*[{$this->eq($this->t('paid'))}]/following-sibling::p[1]/b", $root);

                    if (!empty($total) && preg_match("/^(?<amount>\d[\d,. ]*)[ ]+(?<currency>[[:alpha:]\.]{3,})/u", $text, $matches)) {
                        // 16 557 Руб.    |    44,232 RUB
                        $crncy = strtoupper(str_ireplace(['Руб.', 'Руб'], 'RUB', $matches['currency']));
                        $currencyCode = preg_match('/^[A-Z]{3}$/', $crncy) ? $crncy : null;

                        if (isset($costs) && $crncy == $currency && !empty($costs[$pnr])) {
                            $f->price()
                                ->cost(array_sum($costs[$pnr]));
                        }

                        if (isset($taxes) && $crncy == $currency && !empty($taxes[$pnr])) {
                            $f->price()
                                ->tax(array_sum($costs[$pnr]));
                        }
                    }
                }
            } else {
                $f = $email->add()->flight();

                $f->general()
                    ->confirmation($order, $this->http->FindSingleNode('//text()[' . $this->eq($this->t('locator')) . ']') ?? '');

                $f->general()
                    ->travellers($passengers[0], true);

                $seatsVal = $this->http->FindSingleNode('descendant::tr/*[descendant::img[contains(@src,"seat.png")] and normalize-space()=""]/following-sibling::*[normalize-space()]', $root);
                $seatsAll = $seatsVal !== null ? preg_split('/(\s*,\s*)+/', $seatsVal) : [];

                $this->parseSegments($f, $root, null, $seatsAll);

                if (
                    !empty($this->http->FindSingleNode("(.//td[starts-with(normalize-space(.), 'Бронирование места в салоне')]/following-sibling::td[descendant::img[contains(@src, 'seat')]][1])[1]", $root))
                    || !empty($this->http->FindSingleNode("(.//td[starts-with(normalize-space(.), 'Extra luggage')]/following-sibling::td[descendant::img[contains(@src, 'baggage')]][1])[1]", $root))
                ) {
                    $total = false;
                }

                $text = $this->http->FindSingleNode(".//*[{$this->eq($this->t('paid'))}]/following-sibling::p[1]/b", $root);

                if (!empty($total) && preg_match("/^(?<amount>\d[\d,. ]*)[ ]+(?<currency>[[:alpha:]\.]{3,})/u", $text, $matches)) {
                    // 16 557 Руб.    |    44,232 RUB
                    $crncy = strtoupper(str_ireplace(['Руб.', 'Руб'], ['RUB'], $matches['currency']));
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $crncy) ? $crncy : null;

                    if (isset($costs) && $crncy == $currency && !empty($costs[$order])) {
                        $f->price()
                            ->cost(array_sum($costs[$order]));
                    }

                    if (isset($taxes) && $crncy == $currency && !empty($taxes[$order])) {
                        $f->price()
                            ->tax(array_sum($costs[$order]));
                    }
                }
            }

            $text = $this->http->FindSingleNode(".//*[{$this->eq($this->t('paid'))}]/following-sibling::p[1]/b", $root);

            if (!empty($total) && preg_match("/^(?<amount>\d[\d,. ]*)[ ]+(?<currency>[[:alpha:]]{3,})/u", $text, $matches)) {
                // 16 557 Руб.    |    44,232 RUB
                $crncy = strtoupper(str_ireplace(['Руб'], ['RUB'], $matches['currency']));
                $currencyCode = preg_match('/^[A-Z]{3}$/', $crncy) ? $crncy : null;
                $email->price()
                    ->total(PriceHelper::parse($matches['amount'], $currencyCode))
                    ->currency($crncy);
            }
        }

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'aero@s7.ru') === false) {
            return false;
        }

        foreach ($this->reSubject as $subj) {
            if (stripos($headers['subject'], $subj) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@s7.ru') !== false;
    }

    protected function parseSegments(Flight $flight, $root, $pnrBefore = null, $seatsAll = [])
    {
        if ($pnrBefore !== null) {
            $cond = "[preceding::text()[{$this->starts($this->t('Бронирование:'))}][1][{$this->eq($pnrBefore)}]]";
        } else {
            $cond = '';
        }

        $segmentNodes = $this->http->XPath->query('.//img[contains(@src,"/arrow.jpg") or contains(@src,"/arrow-route.jpg")]/ancestor::tr[1]' . $cond, $root);

        foreach ($segmentNodes as $i => $element) {
            $s = $flight->addSegment();

            if (preg_match($pattern = '/(.+?, \d+:\d+)\s*(.+?)\s*(?:\((\w+)\))?$/u', $this->http->FindSingleNode('td[2]', $element), $matches)) {
                $s->departure()
                    ->date(strtotime($this->normalizeDate($matches[1])))
                    ->strict()
                ;

                if (preg_match("/^(?<name>.{3,}?)[,\s]+(?:Terminal|Терминал)[:\s]*(?<terminal>[-\d\s[:alpha:]]*)$/iu", $matches[2], $m)) {
                    $s->departure()
                        ->name($m['name']);

                    if (!empty($m['terminal'])) {
                        $s->departure()
                            ->terminal($m['terminal']);
                    }
                } else {
                    $s->departure()
                        ->name($matches[2]);
                }
            }

            if (preg_match($pattern, $this->http->FindSingleNode('td[4]', $element), $matches)) {
                $s->arrival()
                    ->date(strtotime($this->normalizeDate($matches[1])))
                    ->strict()
                ;

                if (preg_match("/^(?<name>.{3,}?)[,\s]+(?:Terminal|Терминал)[:\s]*(?<terminal>[-\d\s[:alpha:]]*)$/iu", $matches[2], $m)) {
                    $s->arrival()
                        ->name($m['name']);

                    if (!empty($m['terminal'])) {
                        $s->arrival()
                            ->terminal($m['terminal']);
                    }
                } else {
                    $segment['ArrName'] = $matches[2];
                    $s->arrival()
                        ->name($matches[2]);
                }
            }

            if (preg_match("/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/u", $this->http->FindSingleNode('td[6]', $element), $matches)) {
                $s->airline()
                    ->name($matches[1])
                    ->number($matches[2]);
            } else {
                $s->airline()
                    ->noName()
                    ->noNumber();
            }

            $seats = array_filter(preg_split('/\s*,\s*/',
                $this->http->FindSingleNode("ancestor::tr[1]/following::table[1]//img[contains(@src,'seat.png')]/following::text()[normalize-space()][1]", $element, '/^\s*(\d{1,2}[A-Z]\s*$/')));

            if (!empty($seats)) {
                $s->extra()
                    ->seats($seats);
            }

            if (!empty($s->getDepDate())
                && isset($this->segmentsFromPdf[$s->getDepDate()])
                && preg_match('/\b([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\s+.+\s+(\w+\s+[A-Z\d]+)/u', $this->segmentsFromPdf[$s->getDepDate()][0], $m)
            ) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);

                $s->extra()
                    ->aircraft($m[3]);

                if (preg_match('/(?:Кабина\s+)?(\w+)(?:\s+cabin)?/iu', $this->segmentsFromPdf[$s->getDepDate()][3], $m)) {
                    $s->extra()
                        ->cabin($m[1]);
                }

                if (preg_match('/\b([A-Z]{3})\b/u', $this->segmentsFromPdf[$s->getDepDate()][1], $m)) {
                    $s->departure()
                        ->code($m[1]);
                }

                if (preg_match('/\b([A-Z]{3})\b/u', $this->segmentsFromPdf[$s->getDepDate()][2], $m)) {
                    $s->arrival()
                        ->code($m[1]);
                }

                if (empty($s->getSeats()) && preg_match('/\b([A-Z]{3})\b/u', $this->segmentsFromPdf[$s->getDepDate()][4], $m)) {
                    $s->extra()
                        ->seat($m[1]);
                }
                unset($this->segmentsFromPdf[$s->getDepDate()]);
            }

            if (!empty($s->getDepDate()) && !empty($s->getArrDate())) {
                $s->departure()
                    ->noCode();
                $s->arrival()
                    ->noCode();
            }

            if (empty($s->getSeats()) && !empty($seatsAll)) {
                if ($segmentNodes->length === count($seatsAll)) {
                    $s->extra()
                        ->seats([$seatsAll[$i]]);
                } elseif ($segmentNodes->length === 1 && count($seatsAll) > 1) {
                    $s->extra()
                        ->seats($seatsAll);
                }
            }
        }
    }

    private function cutText(string $start = '', array $ends = [], string $text = '')
    {
        if (!empty($start) && 0 < count($ends) && !empty($text)) {
            foreach ($ends as $end) {
                if (($cuttedText = stristr(stristr($text, $start), $end, true)) && is_string($cuttedText) && 0 < strlen($cuttedText)) {
                    break;
                }
            }

            return substr($cuttedText, 0);
        }

        return null;
    }

    private function t($s)
    {
        if (empty(self::$dict[$this->lang]) || empty(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate(?string $str)
    {
        $in = [
            // 29 мар. 2023, 05:30
            '/\b(\d{1,2})[.\s]+([[:alpha:]]+)[.\s]+(\d{2,4})\s*,\s*(\d{1,2}:\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)/u',
        ];
        $out = [
            '$1 $2 $3, $4',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $str, $m)) {
            if ('en' !== $this->lang && $en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function splitText($textSource = '', $pattern = '', $saveDelimiter = false)
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

    private function rowColsPos($row): array
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

    private function splitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);
        $r = array_values(array_filter($rows));

        if (!$pos) {
            $pos = $this->rowColsPos($r[0]);
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

    private function detectBody(PlancakeEmailParser $parser): bool
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, 's7') === false || $this->http->XPath->query('//img[contains(@src,"s7.ru") or contains(@src,"s7cdn.online")]')->length === 0) {
            return false;
        }

        foreach ($this->detects as $lang => $detects) {
            foreach ($detects as $detect) {
                if (stripos($body, $detect) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
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
}
