<?php

namespace AwardWallet\Engine\ehotel\Email;

use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelCancellation extends \TAccountChecker
{
    public $mailFiles = "ehotel/it-757555627.eml, ehotel/it-761854948.eml, ehotel/it-762623914.eml, ehotel/it-763652132.eml";
    public $subjects = [
        'Cancellation',
        'Stornierung',
    ];
    public $detectLang = [
        'en' => ['Cancellation'],
        'de' => ['Stornierungsbestätigung'],
    ];

    public $pdfNamePattern = "(?:Stornierung_|Cancellation_).*p.*df";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Cancellation'              => 'Cancellation',
        ],
        'de' => [
            'Cancellation'              => 'Stornierungsbestätigung',
            'Hotel data'                => 'Hoteldaten',
            'Booking data'              => 'Buchungsdaten',
            'Booking number'            => 'Buchungsnummer',
            'Booking date'              => 'Buchungsdatum',
            'Guest'                     => 'Gast',
            'Phone'                     => 'Telefon',
            'Telefax'                   => 'Telefax',
            'Departure'                 => 'Abreise',
            'Arrival'                   => 'Anreise',
            'Cancellation policy'       => 'Stornobedingungen',
            'number'                    => 'Nummer',
            'Hotel reservation'         => 'Hotelreservierungs',
            'Hotel name'                => 'Hotelname',
            'Address'                   => 'Anschrift',
            'Nights'                    => 'Nächte',
            'The hotel'                 => 'Das Hotel',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'res@ehotel.de') !== false) {
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
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->assignLang($text);

            if (strpos($text, "@ehotel.de") !== false
                && strpos($text, $this->t('Cancellation')) !== false
                && strpos($text, $this->t('Hotel data')) !== false
                && strpos($text, $this->t('Booking data')) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]ehotel\.de$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->assignLang($text);

            $this->HotelCancellation($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function HotelCancellation(Email $email, $text)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->cancellationNumber($this->re("/\n\s*{$this->t('number')}\s*([A-Z\d]+)\n/", $text))
            ->confirmation($this->re("/\n\s*{$this->t('Booking number')}\s*([A-Z\d]+)\n/", $text))
            ->date(strtotime($this->re("/{$this->t('Booking date')}\s*([\d\w]+[\.\s]*\d+[\.\,\s]*\d+\s*\d+\:\d+\:\d+\s*A?P?M?)\n+/", $text)))
            ->cancelled();

        $email->ota()
            ->confirmation($this->re("/{$this->t('Hotel reservation')}\-?\s*([A-Z\d]+)(?:\n|\s)/", $text), $this->re("/{$this->t('Hotel reservation')}\-?\s*[A-Z\d]+\s*(.*)\n+\s*number/", $text));

        $h->hotel()
            ->name($this->re("/{$this->t('Hotel name')}\s*(.+)\n/", $text))
            ->address($this->re("/{$this->t('Address')}\s*(.+)\n/", $text));

        $traveller = $this->re("/{$this->t('Guest')}\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\n+/u", $text);
        $h->addTraveller(preg_replace("/^(?:Mr\. Dr\.|Mr\.|Herr|Mrs\.)/", "", $traveller));

        $hotelPhone = $this->re("/{$this->t('Phone')}\s*(.+)\n*?/", $text);

        if ($hotelPhone !== null) {
            $h->hotel()
                ->phone($hotelPhone);
        }

        $hotelFax = $this->re("/{$this->t('Telefax')}\s*(.+)\n+\sE\-Mail/", $text);

        if ($hotelFax !== null) {
            $h->hotel()
                ->fax($hotelFax);
        }

        $h->booked()
            ->checkIn(strtotime($this->re("/{$this->t('Arrival')}\s*([\d\w]+[\s\.]*\d+[\,\.\s]*\d+)\n+/", $text)))
            ->checkOut(strtotime($this->re("/{$this->t('Departure')}\s*\|\s*{$this->t('Nights')}\s*([\d\w]+[\s\.]*\d+[\,\.\s]*\d+)\s*\|/", $text)));

        $cancellation = preg_replace("/\s+/", " ", $this->re("/{$this->t('Cancellation policy')}\s*(.+){$this->t('The hotel')}\s*\//s", $text));

        if ($cancellation !== null) {
            $h->general()
                ->cancellation($cancellation);
        }
        $this->detectDeadLine($h);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellation = $h->getCancellation())) {
            return;
        }

        if (preg_match("/(\d+\.\d+\.\d{4})\s*\w+\s*([\d\:]+\s*A?P?M?)\s*/", $cancellation, $m)) {
            $h->booked()
                ->deadline(strtotime($m[1] . $m[2]));
        }

        if (preg_match("/^Reservation may not be cancelled free of charge/", $cancellation)) {
            $h->booked()
                ->nonRefundable();
        }
    }

    private function assignLang($text)
    {
        foreach ($this->detectLang as $lang => $dBody) {
            foreach ($dBody as $word) {
                if (strpos($text, $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
