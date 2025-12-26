<?php

namespace AwardWallet\Engine\ratehawk\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelBookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "ratehawk/it-665267684.eml, ratehawk/it-665552248.eml, ratehawk/it-740163756.eml, ratehawk/it-745994579.eml, ratehawk/it-748364460.eml";
    public $lang = '';
    public $pdfNamePattern = ".*pdf";

    public $subjects = [
        'Confirmation for booking ref',
        'Booking cancellation',
        'Подтверждение бронирования №',
        'Confirmação da reserva',
        'Reserva cancelada em',
    ];

    public $detectLang = [
        'ru' => ['Подтверждение бронирования'],
        'pt' => ['foi cancelado', 'Nome do hóspede'],
        'en' => ['Confirmation for booking', 'Check-in'],
    ];

    public static $dictionary = [
        "en" => [
            'Amount'                                                                   => ['Amount', 'Amount due'],
            'adults'                                                                   => ['adults', 'adult'],
            'was canceled automatically due to non-compliance with payment conditions' => [
                'was canceled automatically due to non-compliance with payment conditions',
                'has been cancelled',
            ],
        ],
        "ru" => [
            //'was canceled automatically due to non-compliance with payment conditions' => '',
            'order number' => 'Номер заказа',
            'Guest name'   => 'Гость',

            'This accommodation is booked by our partner' => 'Размещение забронировано нашим партнером',
            'Hotel'                                       => 'Hotel',
            'Check-in'                                    => 'Заезд',
            'Guests:'                                     => 'Гости:',
            'Reservation'                                 => 'Бронирование',
            'made on'                                     => 'от',
            'Amount'                                      => ['Сумма заказа'],
            'Cancellation conditions'                     => 'Условия отмены',
            'for'                                         => 'для',
            'adults'                                      => ['взрослого'],
            //'children' => '',
            'Check-out' => 'Выезд',
            'from'      => 'с',
            'until'     => 'до',
        ],
        "pt" => [
            //HTML
            'Guest name'                                                               => 'Nome do hóspede',
            'As per your request, booking ref'                                         => 'Infelizmente, o número do pedido',
            'has been cancelled'                                                       => 'foi cancelado',
            'was canceled automatically due to non-compliance with payment conditions' => 'foi cancelado automaticamente devido ao não cumprimento de condições de pagamento',
            'Check-in'                                                                 => 'Check-in',

            'order number'                                => 'Номер заказа',
            'This accommodation is booked by our partner' => 'Este alojamento é reservado pelo nosso',
            'Guests:'                                     => 'Hóspedes:',
            'Reservation'                                 => 'Reserva',
            'made on'                                     => 'feita a',
            'Amount'                                      => ['Valor devido'],
            'Cancellation conditions'                     => 'Condições de cancelamento',
            'for'                                         => 'para',
            'adults'                                      => ['adulto'],
            'children'                                    => 'criança',
            'Check-out'                                   => 'Check-out',
            'from'                                        => 'a partir das',
            'until'                                       => 'até',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.ratehawk.com') !== false) {
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

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->logger->debug($text);

            if (strpos($text, $this->t('This accommodation is booked by our partner')) !== false
                && (strpos($text, $this->t('Check-in')) !== false)
                && (strpos($text, $this->t('Guests:')) !== false)
            ) {
                return true;
            }
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('was canceled automatically due to non-compliance with payment conditions'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Check-in'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.ratehawk\.com$/', $from) > 0;
    }

    public function ParseHotelPDF(Email $email, $text)
    {
        $h = $email->add()->hotel();

        if (preg_match("/{$this->opt($this->t('Reservation'))}\s*(?<conf>\d+)\s*{$this->opt($this->t('made on'))}\s*(?<dateRes>\d+\.\d+\.\d+)\n/u", $text, $m)) {
            $h->general()
                ->confirmation($m['conf']);

            if (preg_match("/(\d+)\.(\d+)\.(\d{2})/", $m['dateRes'], $match)) {
                if ($match[1] > 12) {
                    $h->general()
                        ->date(strtotime($match[1] . '.' . $match[2] . '.20' . $match[3]));
                } else {
                    $h->general()
                        ->date($this->normalizeDate($m['dateRes']));
                }
            }
        }

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Amount'))}]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,\']+)$/", $price, $m)
        || preg_match("/^(?<total>[\d\.\,\']+)\s*(?<currency>\D{1,3})$/", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $h->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation conditions'))}]/ancestor::tr[1]/descendant::td[2]");

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        $this->detectDeadLine($h);

        if (preg_match_all("/{$this->opt($this->t('Guests:'))}\s*(?<guests>[[:alpha:]][-.;\'’[:alpha:] ]*[[:alpha:]])\n/", $text, $m)) {
            $h->general()
                ->travellers(array_unique($m['guests']));
        }

        if (preg_match_all("/\n\n\n(?<rooms>.+\n*.*)\,\s*{$this->opt($this->t('for'))}\s*(?<adults>\d+)\s*(?:{$this->opt($this->t('adults'))})/", $text, $m)) {
            $h->setGuestCount(array_sum($m['adults']));

            foreach ($m['rooms'] as $roomText) {
                $h->addRoom()->setDescription($roomText);
            }
        }

        if (preg_match_all("/(?:{$this->opt($this->t('adults'))})\D*(?<kids>\d+)\s*{$this->opt($this->t('children'))}/", $text, $m)) {
            $h->setKidsCount(array_sum($m['kids']));
        }

        $hotelInfo = $this->re("/\n\n\n(.+\n*.*{$this->opt($this->t('Check-in'))}\n(?:.+\n){2,15}\s*{$this->opt($this->t('Check-out'))}\:?\n\s+[\d\.]+\,\s*{$this->opt($this->t('until'))}\n\s*[\d\:]+)\n\n\n/", $text);

        if (!empty($hotelInfo)) {
            $hotelTable = $this->splitCols($hotelInfo, [0, 90]);

            if (preg_match("/^\s+(?<hotelName>.+)\n\n+(?<address>(?:.+\n*){1,5})[ ]{10,}(?<phone>[+]*\d+)/", $hotelTable[0], $m)) {
                $h->hotel()
                    ->name($m['hotelName'])
                    ->address(preg_replace("/(?:\n\s+)/", " ", $m['address']))
                    ->phone($m['phone']);
            }

            if (preg_match("/{$this->opt($this->t('Check-in'))}\n+\s*(?<inDate>[\.\d]+)\,\s*{$this->opt($this->t('from'))}\n*\s*(?<inTime>\d+\:\d+)\:\d+\n+\s*{$this->opt($this->t('Check-out'))}\:?\s+(?<outDate>[\d\.]+)\,\s*{$this->opt($this->t('until'))}\n\s*(?<outTime>\d+\:\d+)\:\d+/", $hotelTable[1], $m)) {
                $h->booked()
                    ->checkIn($this->normalizeDate($m['inDate'] . ', ' . $m['inTime']))
                    ->checkOut($this->normalizeDate($m['outDate'] . ', ' . $m['outTime']));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('was canceled automatically due to non-compliance with payment conditions'))}]")->length > 0) {
            $conf = $this->http->FindNodes("//text()[{$this->contains($this->t('order number'))}]/following::text()[normalize-space()][1]", null, "/^\s*[№](\d+)\s*$/u");

            if (!empty($conf[0])) {
                $h = $email->add()->hotel();

                $h->general()
                    ->confirmation($conf[0])
                    ->travellers(array_unique($this->http->FindNodes("//text()[{$this->contains($this->t('Guest name'))}]/following::text()[normalize-space()][1]")))
                    ->cancelled();
            }
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'Invoice B2B') !== false) {
                continue;
            }

            if ($this->lang !== 'en' && stripos($text, 'This accommodation is booked by our partner') !== false) {
                continue;
            }

            $this->ParseHotelPDF($email, $text);
        }

        if (count($pdfs) === 0) {
            $this->ParseHotelHTML($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseHotelHTML(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest name'))}]/following::p[1]"));

        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('As per your request, booking ref'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('As per your request, booking ref'))}\s*[№]\s*(\d{5,})\s/u");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//img[contains(@src, 'ratehawk')]/preceding::text()[{$this->eq($this->t('As per your request, booking ref'))}][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('As per your request, booking ref'))}\s*[№]\s*(\d{5,})\s/u");
        }

        if (!empty($conf)) {
            $h->general()
                ->confirmation($conf);
        } else {
            $h->general()
                ->noConfirmation();
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('has been cancelled'))}]")->length > 0) {
            $h->general()
                ->cancelled();
        }

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Check-in']/following::p[1]")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Check-out']/following::p[1]")));

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest name'))}]/preceding::p[2]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest name'))}]/preceding::p[1]"));
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $this->logger->error($str);
        $in = [
            "#^(\d+)\.(\d+)\.(\d{2})$#u", //08.05.24, 15:00
            "#^(\d+)\.(\d+)\.(\d{2})\,\s*([\d\:]+)$#u", //08.05.24, 15:00
        ];
        $out = [
            "$2.$1.20$3",
            "$2.$1.20$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'         => 'EUR',
            'US dollars'=> 'USD',
            'US$'       => 'USD',
            '£'         => 'GBP',
            '₹'         => 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return $s;
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

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/This booking is non-refundable/", $cancellationText)) {
            $h->setNonRefundable(true);
        }

        if (preg_match("/Free cancellation\s*before\s*(\d+\.\d+\.\d{4}\,\s*\d+\:\d+)\s*\(?UTC/", $cancellationText, $m)
        || preg_match("/Бесплатная отменадо (\d+\.\d+\.\d{4}\,\s*\d+\:\d+)\s*UTC/", $cancellationText, $m)
        || preg_match("/Cancelamento gratuitoantes de (\d+\.\d+\.\d{4}\,\s*\d+\:\d+)/", $cancellationText, $m)) {
            $h->setDeadline(strtotime($m[1]));
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $items) {
            foreach ($items as $item) {
                if ($this->http->XPath->query("//text()[{$this->contains($item)}]")->length > 0) {
                    $this->lang = $lang;
                    $this->pdfNamePattern = ".*{$lang}.*pdf";

                    $this->logger->debug($this->pdfNamePattern);

                    return true;
                }
            }
        }

        return false;
    }
}
