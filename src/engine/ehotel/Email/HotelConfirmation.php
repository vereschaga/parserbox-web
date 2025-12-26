<?php

namespace AwardWallet\Engine\ehotel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelConfirmation extends \TAccountChecker
{
    public $mailFiles = "ehotel/it-750108276.eml, ehotel/it-752668628.eml, ehotel/it-754430161.eml, ehotel/it-755497181.eml, ehotel/it-755567763.eml, ehotel/it-757415803.eml";

    public $subjects = [
        'Confirmation',
        'Bestätigung',
        'Reservation',
    ];

    public $detectLang = [
        'en' => ['Confirmation'],
        'de' => ['Buchungsbestätigung'],
    ];

    public $pdfNamePattern = "(?:Buchungsbestaetigung|reservation_confirmation)_eHotel.*p.*df";

    public $lang = '';

    public static $dictionary = [
        'de' => [
            'Confirmation'              => 'Buchungsbestätigung',
            'Booking data'              => 'Buchungsdaten',
            'Hotel confirmation number' => 'Hotelreservierungsnummer',
            'Booking number'            => 'Buchungsnummer',
            'Booked at'                 => 'Gebucht am',
            'Booked by'                 => 'Gebucht von',
            'Guest'                     => 'Gast',
            'Total amount'              => 'Gesamtpreis',
            'Including Tax'             => 'Darin enthaltene Steuern',
            'Room information'          => 'Zimmerinformation',
            'Phone'                     => 'Telefon',
            'Telefax'                   => 'Telefax',
            'occupancy'                 => 'Belegung',
            'Departure'                 => 'Abreise',
            'Arrival'                   => 'Anreise',
            'Cancellation policy'       => 'Stornobedingungen',
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
                && strpos($text, $this->t('Confirmation')) !== false
                && strpos($text, $this->t('Booking data')) !== false
                && strpos($text, $this->t('Hotel confirmation number')) !== false) {
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

            $this->HotelConfirmation($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function HotelConfirmation(Email $email, $text)
    {
        $h = $email->add()->hotel();

        $this->logger->debug($text);

        $h->general()
            ->confirmation($this->re("/{$this->t('Booking number')}\:\s*([A-Z\d]+)(?:\n|\s)/", $text))
            ->date(strtotime($this->re("/{$this->t('Booked at')}\s*([\d\w]+[\.\s]*\d+[\.\,\s]*\d+)/", $text)));

        $email->ota()
            ->confirmation($this->re("/{$this->t('Hotel confirmation number')}\s*([A-Z\d]+)(?:\n|\s)/", $text), $this->re("/{$this->t('Hotel confirmation number')}\s*[A-Z\d]+\s*(.*)\n*/", $text));

        $hotelText = $this->re("/{$this->t('Booked by')}.+\n+(.+{$this->t('Departure')}.+)\n+{$this->t('Booking data')}/s", $text);
        $hotelArray = $this->splitCols($hotelText);

        $h->hotel()
            ->name($this->re("/(.+)\n+/", $hotelArray[0]))
            ->address($this->re("/\n(.+)\n/", $hotelArray[0]));

        $traveller = $this->re("/{$this->t('Guest')}\:\n([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\n\n/u", $hotelArray[1]);
        $h->addTraveller(preg_replace("/^(?:Mr\. Dr\.|Mr\.|Herr|Mrs\.)/", "", $traveller));

        $priceInfo = $this->re("/{$this->t('Total amount')}\:\n([\d\.\,\']+\s*\D{1,3})/", $hotelArray[1]);

        if (preg_match("/^(?<price>[\d\.\,\']+)\s*(?<currency>\D{1,3})$/", $priceInfo, $m)) {
            $h->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['price'], $m['currency']));

            $tax = $this->re("/{$this->t('Including Tax')}\:\n+([\d\.\,\']+)\s*\D{1,3}/", $hotelArray[1]);

            if ($tax !== null) {
                $h->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }
        }

        $roomType = $this->re("/{$this->t('Room information')}\s*(.+)\n/", $text);

        if ($roomType !== null) {
            $h->addRoom()
                ->setType($roomType);
        }

        $hotelPhone = $this->re("/{$this->t('Phone')}\:(.+)\n*?/", $hotelArray[0]);

        if ($hotelPhone !== null) {
            $h->hotel()
                ->phone($hotelPhone);
        }

        $hotelFax = $this->re("/{$this->t('Telefax')}\:(.+)\n*?/", $hotelArray[0]);

        if ($hotelFax !== null) {
            $h->hotel()
                ->fax($hotelFax);
        }

        $h->booked()
            ->checkIn(strtotime($this->re("/{$this->t('Arrival')}\n+([\d\w]+[\s\.]*\d+[\,\.\s]*\d+)/", $hotelArray[1])))
            ->checkOut(strtotime($this->re("/{$this->t('Departure')}\n+([\d\w]+[\s\.]*\d+[\,\.\s]*\d+)/", $hotelArray[2])));

        $occupancyInfo = $this->re("/{$this->t('occupancy')}\:\n(\d+)\s*\w+/", $hotelArray[1]);

        if ($occupancyInfo !== null) {
            $h->booked()
                ->guests($occupancyInfo);
        }

        $cancellation = $this->re("/{$this->t('Cancellation policy')}\s*(.+)\n/", $text);

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
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellation = $h->getCancellation())) {
            return;
        }

        if (preg_match("/(\d+\.\d+\.\d{4})\s*at\s*([\d\:]+\s*A?P?M?)\s*/", $cancellation, $m)) {
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
                if (stripos($text, $word) !== false) {
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
