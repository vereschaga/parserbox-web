<?php

namespace AwardWallet\Engine\iberostar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use DateTime;
use PlancakeEmailParser;

class HotelReservation extends \TAccountChecker
{
    public $mailFiles = "iberostar/it-761856214.eml, iberostar/it-799417047.eml";
    public $subjects = [
        'Factura reserva número',
    ];

    public $lang = '';
    public $pdfNamePattern = ".*pdf";

    public $detectLang = [
        "pt" => ['Dados da Reserva', 'Dados do cliente'],
        "en" => ['Booking Details', 'Customer Details'],
    ];

    public static $dictionary = [
        'pt' => [
        ],
        'en' => [
            'Dados da Reserva'              => 'Booking Details',
            'Dados do cliente'              => 'Customer Details',
            'SERVIÇO DE ATENÇÃO AO CLIENTE' => 'CUSTOMER CARE SERVICE',

            'Código de reserva' => 'Booking code',
            'Tarifa'            => 'Rate:',
            'Hotel'             => 'Hotel:',
            //'Tel:' => '',
            //'Fax:' => '',
            'País'           => 'Country:',
            'Período'        => 'Period:',
            'Pessoas'        => 'Nr. of guests:',
            'Nº de quartos'  => 'No. of rooms:',
            'Tipo de regime' => 'Type of accommodation:',
            'Nome'           => 'First Name:',
            //'Total' => '',
            //'Before Tax' => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && mb_stripos($headers['from'], '@iberostar.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]iberostar\.com$/', $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('@iberostar.com'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Dados da Reserva'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Dados do cliente'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('SERVIÇO DE ATENÇÃO AO CLIENTE'))}]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function parseHotel(Email $email, $text = null)
    {
        $h = $email->add()->hotel();

        // collect reservation confirmation
        $confirmationInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Código de reserva'))}]/ancestor::table[1][normalize-space()]");

        if (preg_match("/^\s*(?<desc>{$this->opt($this->t('Código de reserva'))})\s*(?<number>\w+)\s*$/mu", $confirmationInfo, $m)) {
            $h->general()
                ->confirmation($m['number'], $m['desc']);
        }

        $hotelInfo = $this->http->XPath->query("//text()[{$this->contains($this->t('Tarifa'))}]/ancestor::table[1]")[0] ?? null;

        // collect hotel name
        $name = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Hotel'))}]/following::text()[normalize-space()][1]", $hotelInfo);

        if (!empty($name)) {
            $h->setHotelName($name);
        }

        // collect address, phone and fax
        $contactsInfo = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Código de reserva'))}]/following::table[not(descendant::table)][1]/descendant::tr[normalize-space()]"));

        // contacts example
        // Iberostar Grand Packard
        // Prado 51. Entre Cárcel y Genios
        // 10200 La Habana, Cuba
        // Tel: +53 78664931
        // Fax:
        // reservas2@packard.co.cu'

        // regex for address parsing
        $reg = "/^\s*{$this->opt($h->getHotelName())}\s*\n"
            . "\s*(.+)\s*\n"
            . "\s*Tel.+$/su";

        $address = $this->re($reg, $contactsInfo);

        if (!empty($address)) {
            $h->setAddress(preg_replace("/\s+/", ' ', $address));
        }

        $phone = $this->re("/^\s*Tel\:\s*(\+?\s*\d[\d\-\s\(\)]+\d)\s*$/m", $contactsInfo);

        if (!empty($phone)) {
            $h->setPhone($phone);
        }

        $fax = $this->re("/^\s*Fax\:\s*(\+?\s*\d[\d\-\s\(\)]+\d)\s*$/m", $contactsInfo);

        if (!empty($fax)) {
            $h->setFax($fax);
        }

        // if not collected address before
        if (empty($h->getAddress())) {
            $address = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('País'))}]/preceding::text()[normalize-space()][1]", $hotelInfo)
                . ", "
                . $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('País'))}]/following::text()[normalize-space()][1]", $hotelInfo);

            $h->setAddress($address);
        }

        // collect check-in, check-out and nights count
        $datesInfo = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Período'))}]/following::text()[normalize-space()][1]", $hotelInfo);

        // parse and set check-in and check-out dates
        if (preg_match("/\s*(?<checkIn>\d+\/\d+\/\d{4})\s+\-\s+(?<checkOut>\d+\/\d+\/\d{4})\s+(?<nightsCount>\d+)\s*/", $datesInfo, $m)) {
            $this->setDates($m['checkIn'], $m['checkOut'], $m['nightsCount'], $h);
        }

        // collect guests count
        $guestCount = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Pessoas'))}]/following::text()[normalize-space()][1]", $hotelInfo, true, "/^\s*(\d+)\s*{$this->opt($this->t('Adultos'))}\s*$/");

        if (!empty($guestCount)) {
            $h->setGuestCount($guestCount);
        }

        // collect rooms count
        $roomsCount = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Nº de quartos'))}]/following::text()[normalize-space()][1]", $hotelInfo, true, "/^\s*(\d+)\s*$/");

        if (!empty($roomsCount)) {
            $h->setRoomsCount($roomsCount);
        }

        $room = $h->addRoom();

        // collect rate type
        $rateType = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Tarifa'))}]/following::text()[normalize-space()][1]", $hotelInfo);

        if (!empty($rateType)) {
            $room->setRateType($rateType);
        }

        // collect room type
        $roomType = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Tipo de regime'))}]/following::text()[normalize-space()][1]", $hotelInfo);

        if (!empty($roomType)) {
            $room->setType($roomType);
        }

        // collect traveller
        $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Nome'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*$/mu");

        if (!empty($traveller)) {
            $h->addTraveller($traveller, true);
        }

        // collect provider phone
        $providerInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('SERVIÇO DE ATENÇÃO AO CLIENTE'))}]/following::tr[normalize-space()][1]");

        if (preg_match("/^.+?(?<desc>{$this->opt($this->t('Serviço de Atenção ao Cliente'))}).+?\s+(?<phone>\+?\s*\d[\d\-\s\(\)]+\d)\s+.+$/mu", $providerInfo, $m)) {
            $h->addProviderPhone($m['phone'], $m['desc']);
        }

        // collect cost, total and currency from pdf
        $currency = null;

        if (preg_match("/{$this->opt($this->t('Total'))}\s+(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,\']+)\s*/", $text, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);

            $h->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        $cost = $this->re("/{$this->opt($this->t('Before Tax'))}\s+(?<cost>[\d\.\,\']+)\s*/", $text);

        if (!empty($cost)) {
            $h->price()
                ->cost(PriceHelper::parse($cost, $currency));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->AssignLang();

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->ParseHotel($email, $text);
        }

        if (empty($pdfs)) {
            $this->ParseHotel($email);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function setDates($checkInStr, $checkOutStr, $nightsCount, \AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        $checkInOutDates = [];

        // parse and set check-in and check-out dates
        // check USA (m/d/Y) or other (d/m/Y) date notation
        $checkInOutDates[] = [DateTime::createFromFormat('m/d/Y H:i:s', $checkInStr . ' 00:00:00'),
            DateTime::createFromFormat('m/d/Y H:i:s', $checkOutStr . ' 00:00:00'), ];
        $checkInOutDates[] = [DateTime::createFromFormat('d/m/Y H:i:s', $checkInStr . ' 00:00:00'),
            DateTime::createFromFormat('d/m/Y H:i:s', $checkOutStr . ' 00:00:00'), ];

        foreach ($checkInOutDates as [$checkInDate, $checkOutDate]) {
            if ($checkInDate && $checkOutDate) {
                $dayDiff = $checkInDate->diff($checkOutDate)->format('%a');

                if ($dayDiff == $nightsCount) {
                    $h->setCheckInDate($checkInDate->getTimestamp());
                    $h->setCheckOutDate($checkOutDate->getTimestamp());

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'         => 'EUR',
            'US dollars'=> 'USD',
            '£'         => 'GBP',
            '₹'         => 'INR',
            '$'         => '$',
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

    private function AssignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
