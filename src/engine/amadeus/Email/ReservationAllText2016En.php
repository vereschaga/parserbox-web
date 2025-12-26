<?php

namespace AwardWallet\Engine\amadeus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationAllText2016En extends \TAccountChecker
{
    use \DateTimeTools;

    public $mailFiles = "amadeus/it-4845980.eml, amadeus/it-813430529.eml, amadeus/it-4720364.eml";

    public $lang = '';

    public $detectLang = [
        "es" => ['REGISTRO DE ENTRADA', 'TIPO DE HABITACION'],
        "it" => ['ALBERGO', 'PREZZO TOTALE'],
        "en" => ['FLIGHT', 'CHECK-IN:', 'RESERVATION'],
    ];

    public static $dictionary = [
        "en" => [
            //'GENERAL INFORMATION' => '',
            'hotel'       => ['HOTEL', 'HOTEL RESERVATION'],
            'flight'      => 'FLIGHT',
            'hotelRef'    => 'HOTEL BOOKING REF:',
            'checkIn'     => 'CHECK-IN:',
            'checkOut'    => 'CHECK-OUT:',
            'location'    => 'LOCATION:',
            'reservation' => 'RESERVATION',
            'chainName'   => 'HOTEL CHAIN NAME:',
            'roomType'    => 'ROOM TYPE:',
            'tel'         => 'TELEPHONE:',
            'fax'         => 'FAX:',
            'total'       => 'TOTAL RATE',
            'departure'   => 'DEPARTURE:',
            'arrival'     => 'ARRIVAL:',
            'flightRef'   => 'FLIGHT BOOKING REF:',
            'seat'        => 'SEAT:',
            'sex'         => ['MR', 'MIS', 'MRS'],
            'status'      => 'RESERVATION',
            //'CancellationPolicy' => ''
        ],

        "es" => [
            'GENERAL INFORMATION' => 'INFORMACION GENERAL',
            'hotel'               => ['HOTEL', 'HOTEL RESERVATION'],
            'flight'              => 'FLIGHT',
            'hotelRef'            => 'REFERENCIA RESERVA DE HOTEL:',
            'checkIn'             => 'REGISTRO DE ENTRADA:',
            'checkOut'            => 'SALIDA:',
            'location'            => 'DIRECCION:',
            'reservation'         => 'RESERVATION',
            'chainName'           => 'HOTEL CHAIN NAME:', // no
            'roomType'            => 'TIPO DE HABITACION:',
            'tel'                 => 'TELEFONO:',
            'fax'                 => 'FAX:',
            'total'               => 'TARIFA TOTAL',
            'departure'           => 'DEPARTURE:', // no
            'arrival'             => 'ARRIVAL:', // no
            'flightRef'           => 'FLIGHT BOOKING REF:', // no
            'seat'                => 'SEAT:', // no
            'sex'                 => ['MR', 'MIS', 'MRS'],
            'status'              => 'RESERVA',
            'CancellationPolicy'  => 'CONDICION. CANCELACION:',
        ],

        "it" => [
            'GENERAL INFORMATION' => 'INFORMAZIONI GENERALI',
            'hotel'               => ['ALBERGO'],
            //'flight'      => '',
            'hotelRef'    => 'CODICE PRENOTAZIONE HOTEL:',
            'checkIn'     => 'CHECK-IN HOTEL:',
            'checkOut'    => 'CHECK-OUT:',
            'location'    => 'INDIRIZZO:',
            //'reservation' => '',
            //'chainName'   => '', // no
            'roomType'    => 'TIPO CAMERA:',
            'tel'         => 'TELEFONO:',
            'fax'         => 'FAX:',
            'total'       => 'PREZZO TOTALE',
            //'departure'   => '', // no
            //'arrival'     => '', // no
            //'flightRef'   => '', // no
            //'seat'        => '', // no
            'sex'                => ['MR', 'MIS', 'MRS'],
            'status'             => 'PRENOTAZIONE',
            'CancellationPolicy' => 'REGOLE DI CANCELLAZIONE:',
        ],
    ];

    protected $result = [];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->detectEmailByBody($parser)) {
            $this->http->Log('file not recognized, check detectEmailByHeaders or detectEmailByBody method', LOG_LEVEL_ERROR);

            return false;
        }

        if (empty($textBody = $parser->getPlainBody())) {
            $textBody = $this->htmlToText($parser->getHTMLBody());
        }
        $textBody = str_replace(['&nbsp;', chr(194) . chr(160), '&#160;'], ' ', $textBody);

        $this->assignLang($textBody);

        $this->http->SetEmailBody($textBody);

        $this->parseSegments($email, $textBody);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'itinerary@amadeus.com') !== false
                // SEDOGO/LEOPOLD MR 24MAR2016 SUV
                && preg_match('/.+?\s+\d+\w+\s+[A-Z]{3}/u', $headers['subject']);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang($parser->getHTMLBody());

        return strpos($parser->getHTMLBody(), $this->t('checkIn')) !== false
                && strpos($parser->getHTMLBody(), $this->t('GENERAL INFORMATION')) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@amadeus.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function parseSegments(Email $email, $text)
    {
        $segments = preg_split('/(?:\n[ >]*){2,}/', $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($segments as $segment) {
            if (preg_match("/{$this->opt($this->t('hotel'))}.+?\d+\s*[^\d\W]+\s*\d+/iu", $segment) && stripos($segment, $this->t('location')) !== false) {
                // HOTEL      HOLIDAY INN SUVA    THU 24 MARCH 2016
                $this->parseHotelR($email, $segment);
            } elseif (preg_match("/{$this->opt($this->t('flight'))}\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*\d+/", $segment) && stripos($segment, $this->t('flightRef')) !== false) {
                // FLIGHT     FJ 853 - FIJI AIRWAYS
                $this->parseAirT($email, $segment);
            }
        }
    }

    protected function parseHotelR(Email $email, $text)
    {
        $h = $email->add()->hotel();

        if (preg_match("/{$this->opt($this->t('hotelRef'))}\s*([A-Z\d]+)/u", $text, $matches)) {
            $h->general()
                ->confirmation($matches[1]);
        } else {
            $h->general()
                ->noConfirmation();
        }

        if (preg_match("/{$this->opt($this->t('CancellationPolicy'))}\s+(.+)/", $text, $m)) {
            $h->general()
                ->cancellation($m[1]);
        }

        if (preg_match("/{$this->opt($this->t('hotel'))}(?:[ ]{2,}(\S.+?\S))?[ ]{2,}(?:[^\d\W]+ )?(\d+\s+[^\d\W]+\s+\d+)[ ]*$/mu", $text, $matches)) {
            if (!empty($matches[1])) {
                $hotelName = $matches[1];
            }
            $date = $matches[2];
        }

        if (isset($date) && preg_match("/{$this->opt($this->t('location'))}\s*(.*?)\s+{$this->opt($this->t('checkIn'))}\s*(\d+ \w+)\s+.*?{$this->opt($this->t('checkOut'))}\s*(\d+ \w+)/", $text, $matches)) {
            $h->hotel()
                ->address($matches[1]);

            $dateArray = $this->increaseDate($date, $this->dateStringToEnglish($matches[2]), $this->dateStringToEnglish($matches[3]));

            $h->booked()
                ->checkIn($dateArray['DepDate'])
                ->checkOut($dateArray['ArrDate']);
        }

        if (preg_match("/{$this->opt($this->t('reservation'))} (\w+)/", $text, $matches)) {
            $h->setStatus($matches[1]);
        }

        if (isset($hotelName)) {
            $h->setHotelName($hotelName);
        } elseif (preg_match("/(?:XX\s+|{$this->opt($this->t('chainName'))})\s*(.+)/", $text, $matches)) {
            $h->setHotelName($matches[1]);
        }

        if (preg_match("/{$this->opt($this->t('roomType'))}\s*(.+)/", $text, $matches)) {
            $h->addRoom()->setType($matches[1]);
        }

        $patterns['phone'] = '[+)(\d][-.\s\d)(]{5,}[\d)(]'; // +377 (93) 15 48 52    |    713.680.2992

        if (preg_match("#{$this->opt($this->t($this->t('tel')))}[ ]*({$patterns['phone']})[>\s]*{$this->opt($this->t($this->t('fax')))}[ ]*({$patterns['phone']})#", $text, $matches)) {
            $h->hotel()
                ->phone(preg_replace('/[^\d+-]/', '-', $matches[1]))
                ->fax(preg_replace('/[^\d+-]/', '-', $matches[2]));
        }

        if (preg_match("/([\d.]+)\s*([A-Z]{2,3})\s*{$this->opt($this->t('total'))}/", $text, $matches)) {
            $currency = currency($matches[2]);

            $h->price()
                ->total(PriceHelper::parse($matches[1], $currency))
                ->currency($currency);
        }

        if (preg_match("/{$this->opt($this->t('status'))}\s+(\w+)\s*\(/", $text, $matches)) {
            $h->setStatus($matches[1]);
        }

        $this->detectDeadLine($h);
    }

    protected function parseAirT(Email $email, $text)
    {
        $f = $email->add()->flight();

        $s = $f->addSegment();

        if (preg_match("/{$this->opt($this->t('flight'))}\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+).*?(\d+\s*\w+\s*\d+)/", $text, $matches)) {
            $s->airline()
                ->name($matches[1])
                ->number($matches[2]);

            $date = $matches[3];
        }

        $pregx = '\s+(.+?)[\s-]*(\d+\s*\w+)\s*(\d+:\d+(?:[AP]M)?)';

        if (isset($date) && preg_match("/{$this->opt($this->t('departure'))}{$pregx}/", $text, $matches1) && preg_match("/{$this->opt($this->t('arrival'))}{$pregx}/", $text, $matches2)) {
            $dateArray = $this->increaseDate($date, $matches1[2] . ',' . $matches1[3], $matches2[2] . ',' . $matches2[3]);

            $s->departure()
                ->date($dateArray['DepDate'])
                ->noCode();

            if (preg_match("/^(?<depName>.+)\,\s*TERMINAL\s*(?<depTerminal>.+)/", $matches1[1], $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->terminal($m['depTerminal']);
            } else {
                $s->departure()
                    ->name($matches1[1]);
            }

            $s->arrival()
                ->date($dateArray['ArrDate'])
                ->noCode();

            if (preg_match("/^(?<arrName>.+)\,\s*TERMINAL\s*(?<arrTerminal>.+)/", $matches2[1], $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->terminal($m['arrTerminal']);
            } else {
                $s->arrival()
                    ->name($matches2[1]);
            }
        }

        if (preg_match("/{$this->opt($this->t('reservation'))} \w+, (\w+) \((\w+)\)/", $text, $matches)) {
            $s->extra()
                ->cabin($matches[1])
                ->bookingCode($matches[2]);
        }

        if (preg_match("#{$this->opt($this->t('flightRef'))}\s*([A-Z\d/]+)#", $text, $matches)) {
            $f->general()
                ->confirmation($matches[1]);
        }

        if (preg_match("#{$this->opt($this->t('seat'))}\s*([A-Z\d/]+)+\s+.*?FOR\s+(.*)(?:{$this->opt($this->t('sex'))})#", $text, $matches)) {
            $s->extra()
                ->seat($matches[1], true, true, $matches[2]);

            $f->general()
                ->traveller($matches[2]);
        }
    }

    protected function increaseDate($dateSegment, $depTime, $arrTime)
    {
        $depDate = strtotime($depTime, strtotime($this->dateStringToEnglish($dateSegment)));
        $arrDate = strtotime($arrTime, $depDate);

        while ($depDate > $arrDate) {
            $arrDate = strtotime('+1 day', $arrDate);
        }

        return [
            'DepDate' => $depDate,
            'ArrDate' => $arrDate,
        ];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function htmlToText($s = '', $brConvert = true): string
    {
        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b[ ]*\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z]+\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z]+\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
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

    private function assignLang(string $text)
    {
        foreach ($this->detectLang as $lang => $array) {
            foreach ($array as $word) {
                if (stripos($text, $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/CANCELS ALWAYS CHARGED-CXL FEE FULL/", $cancellationText)) {
            $h->setNonRefundable(true);
        }

        if (preg_match("/Free cancellation until\s+(?<date>\d+\s*\w+\s*\d{4})\s+at\s+(?<time>[\d\:]+\s*a?p?m)\s+\(/", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m['date'] . ', ' . $m['time']));
        }
    }
}
