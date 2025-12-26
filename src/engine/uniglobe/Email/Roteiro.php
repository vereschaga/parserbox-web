<?php

namespace AwardWallet\Engine\uniglobe\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Roteiro extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "uniglobe/it-4891059.eml, uniglobe/it-5022979.eml, uniglobe/it-658170955.eml, uniglobe/it-659214721.eml";

    public $reBody = [
        'pt' => ['Pagamento:', 'ROTEIRO DA VIAGEM'],
        'es' => ['Observación:', 'ITINERARIO DEL VIAJE'],
    ];
    public $reSubject = [
        ['Solicitação', 'Emitida'],
    ];
    public $lang = 'pt';
    public static $dict = [
        'pt' => [
            'Localizador:' => ['Localizador:', 'Número de Ordem:'],
        ],
        'es' => [
            'Emissão'          => 'Emisión',
            'Taxas + *Repasse' => 'Tasas + Gastos',
            'Nº Voo'           => 'Nº Vuelo',
            'Observação:'      => 'Observación:',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['subject'])) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers['subject'], $reSubject[0]) !== false && stripos($headers['subject'], $reSubject[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (!$body) {
            $body = text($parser->getHTMLBody());
        }

        if (stripos($body, '/uniglobe/default.aspx') !== false || stripos($body, 'https://www.argoit.com.br') !== false) {
            foreach ($this->reBody as $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'uniglobeviajex.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        if ($this->http->XPath->query('//img[contains(@src,"/icone_aviao.gif")]/ancestor::table[1]')->length > 0) {
            $this->ParseFlightEmail($email);
        }

        if ($nodes = $this->http->XPath->query('//img[contains(@src,"/icone_hospedagem.gif")]/ancestor::table[1]')) {
            foreach ($nodes as $root) {
                $this->ParseHotelEmail($email, $root);
            }
        }

        if ($nodes = $this->http->XPath->query('//img[contains(@src,"/icone_locacao.gif")]/ancestor::table[1]')) {
            foreach ($nodes as $root) {
                $this->ParseCarEmail($email, $root);
            }
        }

        if ($nodes = $this->http->XPath->query('//img[contains(@src,"/icone_Rodoviario.gif")]/ancestor::table[1]')) {
            foreach ($nodes as $root) {
                $this->ParseBusEmail($email, $root);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function ParseFlightEmail(Email $email)
    {
        foreach ($this->http->XPath->query('//img[contains(@src,"/icone_aviao.gif")]/ancestor::table[1]') as $itDOM) {
            $f = $email->add()->flight();

            $f->general()
                ->travellers($this->http->FindNodes('//img[contains(@src,"/icone.gif")]/ancestor::td[1]/following-sibling::td[1]/strong'))
                ->confirmation($this->http->FindSingleNode("./descendant-or-self::strong[{$this->contains($this->t('Localizador:'))}]/../following-sibling::td[1]", $itDOM, false))
                ->date($this->NormalizeDate($this->http->FindSingleNode('(./following-sibling::table/descendant-or-self::strong[contains(.,"' . $this->t('Emissão') . ':")]/../following-sibling::td[1])[1]', $itDOM)));

            $total = $this->http->FindSingleNode('(./following-sibling::table/descendant-or-self::strong[contains(.,"' . $this->t('Total') . ':")]/../following-sibling::td[1])[1]', $itDOM);

            if (preg_match("/^(?<currency>\D{1,3})\s+(?<total>[\d\.\,]+)$/", $total, $m)) {
                $currency = $this->currency($m['currency']);
                $f->price()
                    ->total(PriceHelper::parse($m['total'], $currency))
                    ->currency($currency);

                $cost = $this->http->FindSingleNode('(./following-sibling::table/descendant-or-self::strong[contains(.,"' . $this->t('Valor') . ':")]/../following-sibling::td[1])[1]', $itDOM, true, "/\D+\s+([\d\.\,]+)/");

                if ($cost !== null) {
                    $f->price()
                        ->cost(PriceHelper::parse($cost, $currency));
                }

                $tax = $this->http->FindSingleNode('(./following-sibling::table/descendant-or-self::strong[contains(.,"' . $this->t('Taxas + *Repasse') . ':")]/../following-sibling::td[1])[1]', $itDOM, true, '/\D+\s+([\d\.\,]+)/');

                if ($tax !== null) {
                    $f->price()
                        ->tax(PriceHelper::parse($tax, $currency));
                }

                $this->ParseFlightSegment($itDOM, $f);
            }
        }
    }

    private function ParseFlightSegment($itDOM, Flight $f)
    {
        foreach ($this->http->XPath->query('./following-sibling::table[./descendant::strong[starts-with(.,"' . $this->t('Nº Voo') . ':") or starts-with(.,"' . $this->t('Emissão') . ':")]]', $itDOM)
                 as $tsDom) {
            if ($this->http->FindSingleNode('./descendant::strong[starts-with(.,"' . $this->t('Emissão') . ':")]', $tsDom)) {
                return;
            }

            $s = $f->addSegment();

            foreach ($this->http->XPath->query('./descendant-or-self::tr[not(contains(.,"' . $this->t('Nº Voo') . ':"))]', $tsDom) as $tsTr) {
                $tds = $this->http->FindNodes('./td', $tsTr);

                switch (count($tds)) {
                    case 7:
                        $s->airline()
                            ->number(trim($tds[1]));

                        $s->extra()
                            ->cabin(trim($tds[4]))
                            ->bookingCode(trim($tds[2]));

                        if (preg_match('#(?<Name>.+)\((?<Code>[A-Z]{3})\)#', $tds[5], $m)) {
                            $s->departure()
                                ->code($m['Code'])
                                ->name(trim($m['Name']));
                        }
                        //03/11 06:10
                        if (preg_match('#(?<DateMon>\d{1,2}\/\d{2})\s(?<Time>\d{1,2}:\d{2})\s*$#', $tds[6], $m)) {
                            $s->departure()
                                ->date(strtotime($m['Time'], $this->NormalizeDate($m['DateMon'], $f->getReservationDate())));
                        }

                        break;

                    case 5:
                        if ($tds[1] !== $this->t('Observação:')) {
                            //AD  - Azul
                            if (preg_match('#(?<AirlineName>[\dA-Z]{2})\s+\-\s+#', $tds[0], $m)) {
                                $s->airline()
                                    ->name($m['AirlineName']);
                            }

                            if (preg_match('#(?<Name>.+)\((?<Code>[A-Z]{3})\)#', $tds[3], $m)) {
                                $s->arrival()
                                    ->code($m['Code'])
                                    ->name(trim($m['Name']));
                            }

                            if (preg_match('#(?<DateMon>\d{1,2}\/\d{2})\s(?<Time>\d{1,2}:\d{2})\s*$#', $tds[4], $m)) {
                                $s->arrival()
                                    ->date(strtotime($m['Time'], $this->NormalizeDate($m['DateMon'], $f->getReservationDate())));
                            }
                        }

                        break;
                }
            }
        }
    }

    private function ParseHotelEmail(Email $email, $root)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->travellers($this->http->FindNodes('//img[contains(@src,"/icone.gif")]/ancestor::td[1]/following-sibling::td[1]/strong'))
            ->confirmation($this->http->FindSingleNode(".", $root, true, "/{$this->opt($this->t('Voucher:'))}\s*([A-z\d\-]{5,})/"));

        $h->hotel()
            ->name($this->http->FindSingleNode(".", $root, true, "/^(\D+)\:\s*{$this->opt($this->t('Voucher:'))}/"));

        $headArray = $this->http->FindNodes("./following::text()[starts-with(normalize-space(), 'Endereço:')][1]/ancestor::tr[1]/descendant::td", $root);
        $dataArray = [];
        $hotelNodes = $this->http->XPath->query("./following::text()[starts-with(normalize-space(), 'Endereço:')][1]/ancestor::tr[1]/following-sibling::tr", $root);

        foreach ($hotelNodes as $hotelNode) {
            $dataArray[] = $this->http->FindNodes("./descendant::td", $hotelNode);
        }

        if (count($headArray) === count($dataArray[0])) {
            foreach ($headArray as $headKey => $headColumnValue) {
                $address = '';

                if (stripos($headColumnValue, $this->t('Endereço:')) !== false && !empty($dataArray[$headKey])) {
                    for ($i = 0; $i < count($dataArray); $i++) {
                        $address = $address . ' ' . $dataArray[$i][$headKey];
                    }
                    $h->setAddress($address);
                }

                $dateInOut = '';

                if (stripos($headColumnValue, $this->t('Check-In')) !== false) {
                    for ($i = 0; $i < count($dataArray); $i++) {
                        $dateInOut = $dateInOut . ' ' . $dataArray[$i][$headKey];
                    }

                    if (preg_match("/^\s*(?<inDay>\d+)\/(?<inMonth>\w+)\/(?<inYear>\d{4})\s*(?<inTime>[\d\:]+)\s*(?<outDay>\d+)\/(?<outMonth>\w+)\/(?<outYear>\d{4})\s*(?<outTime>[\d\:]+)\s*$/u", $dateInOut, $m)) {
                        $h->booked()
                            ->checkIn($this->NormalizeDate($m['inDay'] . ' ' . $m['inMonth'] . ' ' . $m['inYear'] . ', ' . $m['inTime']))
                            ->checkOut($this->NormalizeDate($m['outDay'] . ' ' . $m['outMonth'] . ' ' . $m['outYear'] . ', ' . $m['outTime']));
                    }
                }
            }
        }

        $total = $this->http->FindSingleNode("./following::table[3]/descendant::text()[starts-with(normalize-space(), 'Total:')]/following::text()[normalize-space()][1]", $root);

        if (preg_match("/^(?<currency>\D{1,3})\s+(?<total>[\d\.\,]+)$/", $total, $m)) {
            $currency = $this->currency($m['currency']);
            $h->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        $rate = $this->http->FindSingleNode("./following::table[3]/descendant::text()[starts-with(normalize-space(), 'Diária:')]/following::text()[normalize-space()][1]", $root, true, "/^(\D{1,3}\s+[\d\.\,']+)$/");
        $description = $this->http->FindSingleNode("./following::table[2]", $root, true, "/{$this->opt($this->t('Observação:'))}\s*(.+)/");

        if (!empty($rate) || !empty($description)) {
            $room = $h->addRoom();

            if (!empty($rate)) {
                $room->setRate($rate . ' / night');
            }

            if (preg_match("/(?<description>.+)\s+(?<cancellation>Permite alterações e cancelamentos até.*)/u", $description, $m)) {
                $room->setDescription($m['description']);
                $h->general()
                    ->cancellation($m['cancellation']);
            } else {
                $room->setDescription($description);
            }
        }
    }

    private function ParseBusEmail(Email $email, $root)
    {
        foreach ($this->http->XPath->query('//img[contains(@src,"/icone_Rodoviario.gif")]/ancestor::table[1]') as $itDOM) {
            $b = $email->add()->bus();

            $b->general()
                ->confirmation($this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Voucher:')]/ancestor::td[1]/following-sibling::td[1]", $itDOM, true, "/^(\d+)(?:\s|$)/"))
                ->travellers($this->http->FindNodes("//img[contains(@src,'/icone.gif')]/ancestor::table[1]/descendant::text()[normalize-space()][1]", null, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/"));

            $price = $this->http->FindSingleNode("./following-sibling::table[5]/descendant::tr[1]/td[4]", $itDOM, true, "/{$this->opt($this->t('Total'))}\:?\s+(.+)/");

            if (preg_match("/^([\d\.\,\']+)$/", $price, $m)) {
                $b->price()
                    ->total(PriceHelper::parse($m[1]));
            }

            $s = $b->addSegment();

            $depArrPoint = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $itDOM);

            if (
                preg_match("/^(?<depName>.+)\((?<depCode>[A-Z]{3})\)\s+\-\s+(?<arrName>.+)\s+\((?<arrCode>[A-Z]{3})\)$/", $depArrPoint, $m)
                || preg_match("/^(?<depName>.+)\((?<depCode>[A-Z]{3})\)\s+\-\s+(?<arrName>.+)$/", $depArrPoint, $m)
                || preg_match("/^(?<depName>.+\(.+\))\s+\-\s+(?<arrName>.+\s+\(.+\))$/", $depArrPoint, $m)
            ) {
                $s->departure()
                    ->name($m['depName']);

                if (isset($m['depCode'])) {
                    $s->departure()
                        ->code($m['depCode']);
                }

                $s->arrival()
                    ->name($m['arrName']);

                if (isset($m['arrCode']) && !empty($m['arrCode'])) {
                    $s->arrival()
                        ->code($m['arrCode']);
                }
            }

            $depArrDate = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::td[last()]", $itDOM);

            if (preg_match("/^(?<depDate>\d+\/\d+\/\d{2}\s+[\d\:]+)\s+\/\s+(?<arrDate>\d+\/\d+\/\d{2}\s+[\d\:]+)$/", $depArrDate, $m)) {
                $s->departure()
                    ->date($this->NormalizeDate(str_replace('/', '.', $m['depDate'])));

                $s->arrival()
                    ->date($this->NormalizeDate(str_replace('/', '.', $m['arrDate'])));
            }

            $seat = $this->http->FindSingleNode("./following-sibling::table[2]/descendant::tr[2]/td[2]", $itDOM, true, "/^(\d+)$/");

            if (!empty($seat)) {
                $s->addSeat($seat);
            }

            $ticket = $this->http->FindSingleNode("./following-sibling::table[3]/descendant::tr[2]/td[2]", $itDOM, true, "/^(\d+)(?:\s|$)/");

            if (!empty($ticket)) {
                $b->addTicketNumber($ticket, false);
            }
        }
    }

    private function ParseCarEmail(Email $email, $root)
    {
        $c = $email->add()->rental();

        $c->general()
            ->travellers($this->http->FindNodes('//img[contains(@src,"/icone.gif")]/ancestor::td[1]/following-sibling::td[1]/strong'))
            ->confirmation($this->http->FindSingleNode(".", $root, true, "/{$this->opt($this->t('Voucher:'))}\s*([A-Z\d]{5,})$/"));

        $price = $this->http->FindSingleNode("./following::table[3]", $root, true, "/{$this->opt($this->t('Total:'))}\s*(\D{1,3}\s*[\d\.\,\']+)/");

        if (preg_match("/^(?<currency>\D{1,3})\s+(?<total>[\d\.\,\']+)$/", $price, $m)) {
            $currency = $this->currency($m['currency']);
            $c->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        $c->setCarType($this->http->FindSingleNode("./following::table[2]", $root, true, "/{$this->opt($this->t('Descrição:'))}\s*(.+)/"));

        $pickUpLocation = $this->http->FindSingleNode("./following::table[1]/descendant::tr[2]/td[1]", $root);
        $pickUpDate = str_replace("/", " ", $this->http->FindSingleNode("./following::table[1]/descendant::tr[2]/td[4]", $root));
        $dropOffLocation = $this->http->FindSingleNode("./following::table[1]/descendant::tr[3]/td[1]", $root);
        $dropOffDate = str_replace("/", " ", $this->http->FindSingleNode("./following::table[1]/descendant::tr[3]/td[4]", $root));

        $c->pickup()
            ->location($pickUpLocation)
            ->date($this->NormalizeDate($pickUpDate));

        $c->dropoff()
            ->location($dropOffLocation)
            ->date($this->NormalizeDate($dropOffDate));
    }

    private function NormalizeDate($date, $relativeDate = null)
    {
        //28/out/2016
        //3/11/2016

        if (preg_match('#^\s*(?<Day>\d+)\/(?<Month>\w+)\.?\/(?<Year>\d{4})\s*$#', $date, $m)) {
            $divider = " ";

            if ((int) $m['Month'] > 0) {
                $divider = ".";
            } else {
                if ($translatedMonthName = MonthTranslate::translate($m['Month'], $this->lang)) {
                    $m['Month'] = $translatedMonthName;
                }
            }
            $date = strtotime($m['Day'] . $divider . $m['Month'] . $divider . $m['Year']);
        } elseif (preg_match('#^\s*(?<Day>\d+)\/(?<Month>\w+)\.?\s*$#', $date, $m) && !empty($relativeDate)) {
            $divider = " ";

            if ((int) $m['Month'] > 0) {
                $divider = ".";
            } else {
                if ($translatedMonthName = MonthTranslate::translate($m['Month'], $this->lang)) {
                    $m['Month'] = $translatedMonthName;
                }
            }

            $date = EmailDateHelper::parseDateRelative($m['Day'] . $divider . $m['Month'], $relativeDate, true, "%D%{$divider}%Y%");
        } elseif (preg_match('#^\s*(?<Day>\d+)\s*(?<Month>\w+)\s*(?<Year>\d{4})\,?\s*(?<time>\d+\:\d+)$#', $date, $m)) {
            $divider = " ";

            if ((int) $m['Month'] > 0) {
                $divider = ".";
            } else {
                if ($translatedMonthName = MonthTranslate::translate($m['Month'], $this->lang)) {
                    $m['Month'] = $translatedMonthName;
                }
            }
            $date = strtotime($m['Day'] . $divider . $m['Month'] . $divider . $m['Year'] . $divider . $m['time']);
        } elseif (preg_match('#^\s*(?<Day>\d+)\.(?<Month>\d+)\.(?<Year>\d{2})\,?\s*(?<time>\d+\:\d+)$#', $date, $m)) {
            $divider = " ";

            if ((int) $m['Month'] > 0) {
                $divider = ".";
            } else {
                if ($translatedMonthName = MonthTranslate::translate($m['Month'], $this->lang)) {
                    $m['Month'] = $translatedMonthName;
                }
            }
            $date = strtotime($m['Day'] . $divider . $m['Month'] . $divider . '20' . $m['Year'] . $divider . $m['time']);
        } else {
            $date = null;
        }

        return $date;
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

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        return true;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
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
