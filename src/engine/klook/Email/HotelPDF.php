<?php

namespace AwardWallet\Engine\klook\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelPDF extends \TAccountChecker
{
    public $mailFiles = "klook/it-480431901.eml, klook/it-763688606.eml";
    public $subjects = [
        '/Your booking confirmation for.*\. Keep this handy\./',
    ];

    public $lang = '';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
            'hotelInfoStart'      => ['Stays'],
            'bookingDetailsStart' => ['Booking details'],

            // TABLE-2
            'Booking reference ID' => ['Booking reference ID', 'Booking'],
            // 'Supplier reference no.' => [''],
            'Hotel confirmation no.' => ['Hotel confirmation no.', 'Hotel'],

            // TABLE-3
            'Number of adults'   => ['Number of adults', 'Number of'],
            'Number of children' => ['Number of children', 'Number of'],
            'Number of rooms'    => ['Number of rooms', 'Number of'],
            'Check-in date'      => ['Check-in date', 'Check-in'],
            'Check-out date'     => ['Check-out date', 'Check-out'],
            'Guest for room'     => ['Guest for room', 'Guest for'],
        ],
    ];

    private $otaConfNumbers = [];

    private $patterns = [
        'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.  |  3pm
        'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52  |  (+351) 21 342 09 07  |  713.680.2992
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:]\s]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && (stripos($headers['from'], '@klook.com') !== false)) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $detectProvider = $this->http->XPath->query('//a[contains(@href,".klook.com/") or contains(@href,"click.klook.com")] | //text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"Klook Travel")]')->length > 0;

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)
                || !$detectProvider && stripos($text, 'Klook customer service') === false
            ) {
                continue;
            }

            if ($this->assignLang($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]klook\.com$/', $from) > 0;
    }

    public function ParseHotelPDF(Email $email, $text): void
    {
        $h = $email->add()->hotel();

        $cancellation = $this->re("/{$this->opt($this->t('Cance l and re fund policy:'))}\s*(.+?)\s+{$this->opt($this->t('Bre akfast policy:'))}/s", $text);

        if ($cancellation) {
            $h->general()->cancellation(preg_replace('/\s+/', ' ', $cancellation));
        }

        /*
            TABLE-1
        */

        $hotelInfo = $this->re("/([ ]*{$this->opt($this->t('hotelInfoStart'))}.*(?:.*\n){2,7})\n{4}\s*Booking/", $text);
        $hotelTable = $this->splitCols($hotelInfo);

        if (count($hotelTable) > 0 && preg_match("/{$this->opt($this->t('hotelInfoStart'))}\n+(.+?)\s*$/s", $hotelTable[0], $m)) {
            $h->hotel()->name(preg_replace('/\s+/', ' ', $m[1]));
        }

        if (count($hotelTable) > 1 && preg_match("/^\s*Location\n{0,1}((?:\n.+){1,3})(?:\n|\s*$)/", $hotelTable[1], $m)) {
            $h->hotel()->address(preg_replace('/\s+/', ' ', trim($m[1])));
        }

        if (count($hotelTable) > 1 && preg_match("/\n[ ]*Telephone\n{1,2}[ ]*({$this->patterns['phone']})\s*$/i", $hotelTable[1], $m)) {
            $h->hotel()->phone($m[1]);
        }

        /*
            TABLE-2
        */

        $confText = $this->re("/\n([ ]*Booking.+?)\n{2,}[ ]*{$this->opt($this->t('bookingDetailsStart'))}/s", $text) ?? '';

        /*
            Booking      GGF961955    Supplier reference no.:    GN82023014 Hotel          6222SE4426
        */
        $confTextFirstRow = $this->re("/(.+)/", $confText);

        $tablePos = [0];

        if (preg_match("/^([ ]*{$this->opt($this->t('Booking reference ID'))}[ :]+)[-A-Z\d]{2,30}[ ]+{$this->opt($this->t('Supplier reference no.'))}/", $confTextFirstRow, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.+? ){$this->opt($this->t('Supplier reference no.'))}(?:[ :]|\n|$)/", $confTextFirstRow, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.+? {$this->opt($this->t('Supplier reference no.'))}[ :]+)[-A-Z\d]{2,30}[ ]+{$this->opt($this->t('Hotel confirmation no.'))}/", $confTextFirstRow, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.+ ){$this->opt($this->t('Hotel confirmation no.'))}(?:[ :]|\n|$)/", $confTextFirstRow, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.+ {$this->opt($this->t('Hotel confirmation no.'))}[ :]+)[-A-Z\d]{2,30}(?:\n|$)/", $confTextFirstRow, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        $confTable = $this->splitCols($confText, $tablePos);
        $confTextTransform = implode("\n", $confTable);

        if (preg_match("/^\s*({$this->opt($this->t('Booking reference ID'))})[\s:]+([-A-Z\d\s]{4,40}?)\n+[ ]*{$this->opt($this->t('Supplier reference no.'))}/", $confTextTransform, $m)) {
            $otaConfirmation = preg_replace('/\s+/', '', $m[2]);

            if (!in_array($otaConfirmation, $this->otaConfNumbers)) {
                $email->ota()->confirmation($otaConfirmation, preg_replace('/\s+/', ' ', $m[1]));
                $this->otaConfNumbers[] = $otaConfirmation;
            }
        }

        if (preg_match("/(?:^\s*|\n[ ]*)({$this->opt($this->t('Supplier reference no.'))})[\s:]+([-A-Z\d\s]{4,40}?)\n+[ ]*{$this->opt($this->t('Hotel confirmation no.'))}/", $confTextTransform, $m)) {
            $otaConfirmation = preg_replace('/\s+/', '', $m[2]);

            if (!in_array($otaConfirmation, $this->otaConfNumbers)) {
                $email->ota()->confirmation($otaConfirmation, preg_replace('/\s+/', ' ', $m[1]));
                $this->otaConfNumbers[] = $otaConfirmation;
            }
        }

        if (preg_match("/(?:^\s*|\n[ ]*)({$this->opt($this->t('Hotel confirmation no.'))})[\s:]+([-A-Z\d\s]{4,40}?)\s*$/", $confTextTransform, $m)) {
            $h->general()->confirmation(preg_replace('/\s+/', '', $m[2]), preg_replace('/\s+/', ' ', $m[1]));
        } elseif (preg_match("/(?:^\s*|\n[ ]*)({$this->opt($this->t('Hotel confirmation no.'))})[\s:]*$/i", $confTextTransform) && count($this->otaConfNumbers) > 0) {
            $h->general()->noConfirmation();
        }

        /*
            TABLE-3
        */

        $bookingDetails = $this->re("/^[ ]*{$this->opt($this->t('bookingDetailsStart'))}\n+(.+?)\n{3,}[ ]*Contact info/ms", $text);
        $detailsTR1 = $this->re("/^\n*([ ]*{$this->opt($this->t('Number of adults'))}.+?)\n+[ ]*{$this->opt($this->t('Room type'))}/s", $bookingDetails) ?? $bookingDetails;
        $detailsTR2 = $this->re("/(?:^\n*|\n)([ ]*{$this->opt($this->t('Room type'))}.+?)\n+[ ]*{$this->opt($this->t('Check-out date'))}/s", $bookingDetails) ?? $bookingDetails;
        $detailsTR3 = $this->re("/(?:^\n*|\n)([ ]*{$this->opt($this->t('Check-out date'))}.+?)\s*$/s", $bookingDetails) ?? $bookingDetails;

        $detailsTR1FirstRow = $this->re("/(.+)/", $detailsTR1);
        $detailsTR2FirstRow = $this->re("/(.+)/", $detailsTR2);
        $detailsTR3FirstRow = $this->re("/(.+)/", $detailsTR3);

        $tablePos = [0];

        if (preg_match("/^([ ]*{$this->opt($this->t('Number of adults'))}[ :]+)\d+[ ]+{$this->opt($this->t('Number of children'))}/", $detailsTR1FirstRow, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^([ ]*{$this->opt($this->t('Number of adults'))}[ :]+.*? ){$this->opt($this->t('Number of children'))}(?:[ :]|\n|$)/", $detailsTR1FirstRow, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^([ ]*{$this->opt($this->t('Number of adults'))}[ :]+.*? {$this->opt($this->t('Number of children'))}[ :]+)\d+[ ]+{$this->opt($this->t('Number of rooms'))}/", $detailsTR1FirstRow, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.+ ){$this->opt($this->t('Number of rooms'))}(?:[ :]|\n|$)/", $detailsTR1FirstRow, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.+ {$this->opt($this->t('Number of rooms'))}[ :]+)\d+(?:\n|$)/", $detailsTR1FirstRow, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        $detailsTR1Table = $this->splitCols($detailsTR1, $tablePos);
        $detailsTR1Transform = implode("\n", $detailsTR1Table);

        $tablePos = [0];

        if (preg_match("/^([ ]*{$this->opt($this->t('Room type'))}[ :]+)[^:\s].*?[ ]+{$this->opt($this->t('Bed type'))}/", $detailsTR2FirstRow, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^([ ]*{$this->opt($this->t('Room type'))}[ :]+.*? ){$this->opt($this->t('Bed type'))}(?:[ :]|\n|$)/", $detailsTR2FirstRow, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^([ ]*{$this->opt($this->t('Room type'))}[ :]+.*? {$this->opt($this->t('Bed type'))}[ :]+)[^:\s].*?[ ]+{$this->opt($this->t('Check-in date'))}/", $detailsTR2FirstRow, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.+ ){$this->opt($this->t('Check-in date'))}(?:[ :]|\n|$)/", $detailsTR2FirstRow, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.+ {$this->opt($this->t('Check-in date'))}[ :]+)[^:\s].*(?:\n|$)/", $detailsTR2FirstRow, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        $detailsTR2Table = $this->splitCols($detailsTR2, $tablePos);
        $detailsTR2Transform = implode("\n", $detailsTR2Table);

        $tablePos = [0];

        if (preg_match("/^([ ]*{$this->opt($this->t('Check-out date'))}[ :]+)[^:\s].*?[ ]+{$this->opt($this->t('Guest for room'))}/", $detailsTR3FirstRow, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^([ ]*{$this->opt($this->t('Check-out date'))}[ :]+.*? ){$this->opt($this->t('Guest for room'))}(?:\s*\d{1,3})?(?:[ :]|\n|$)/", $detailsTR3FirstRow, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.+ {$this->opt($this->t('Guest for room'))}(?:\s*\d{1,3})?[ :]+)[^:\s].*(?:\n|$)/", $detailsTR3FirstRow, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        $detailsTR3Table = $this->splitCols($detailsTR3, $tablePos);
        $detailsTR3Transform = implode("\n", $detailsTR3Table);

        if (preg_match("/^\s*{$this->opt($this->t('Number of adults'))}[\s:]+(\d{1,3})\n+[ ]*{$this->opt($this->t('Number of children'))}/", $detailsTR1Transform, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/(?:^\s*|\n[ ]*){$this->opt($this->t('Number of children'))}[\s:]+(\d{1,3})\n+[ ]*{$this->opt($this->t('Number of rooms'))}/", $detailsTR1Transform, $m)) {
            $h->booked()->kids($m[1]);
        }

        if (preg_match("/(?:^\s*|\n[ ]*){$this->opt($this->t('Number of rooms'))}[\s:]+(\d{1,3})\s*$/", $detailsTR1Transform, $m)) {
            $h->booked()->rooms($m[1]);
        }

        if (preg_match("/^\s*{$this->opt($this->t('Room type'))}[\s:]+([^\s:][\s\S]*?)\n+[ ]*{$this->opt($this->t('Bed type'))}/", $detailsTR2Transform, $m)) {
            $roomType = preg_replace('/\s+/', ' ', $m[1]);
        } else {
            $roomType = null;
        }

        if (preg_match("/(?:^\s*|\n[ ]*){$this->opt($this->t('Check-in date'))}[\s:]+([^\s:][\s\S]*?)\s*$/", $detailsTR2Transform, $m)) {
            $dateCheckIn = strtotime($this->normalizeDate($m[1]));
        } else {
            $dateCheckIn = null;
        }

        if (preg_match("/^\s*{$this->opt($this->t('Check-out date'))}[\s:]+([^\s:][\s\S]*?)\n+[ ]*{$this->opt($this->t('Guest for room'))}/", $detailsTR3Transform, $m)) {
            $dateCheckOut = strtotime($this->normalizeDate($m[1]));
        } else {
            $dateCheckOut = null;
        }

        if (preg_match("/(?:^\s*|\n[ ]*){$this->opt($this->t('Guest for room'))}(?:\s*\d{1,3})?[\s:]+([^\s:][\s\S]*?)\s*$/", $detailsTR3Transform, $m)) {
            $traveller = preg_replace('/\s+/', ' ', $m[1]);

            if (preg_match("/^{$this->patterns['travellerName']}$/u", $traveller)) {
                $h->general()->traveller($traveller, true);
            }
        }

        if ($roomType) {
            $h->addRoom()->setType($roomType);
        }

        $timeCheckIn = $this->re("/{$this->opt($this->t('Earliest check-in time'), true)}[:\s]*({$this->patterns['time']})/", $text);
        $timeCheckOut = $this->re("/{$this->opt($this->t('Latest check-out time'), true)}[:\s]*({$this->patterns['time']})/", $text);

        if ($timeCheckIn) {
            $dateCheckIn = strtotime($timeCheckIn, $dateCheckIn);
        }

        if ($timeCheckOut) {
            $dateCheckOut = strtotime($timeCheckOut, $dateCheckOut);
        }

        $h->booked()->checkIn($dateCheckIn)->checkOut($dateCheckOut);

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text) || !$this->assignLang($text)) {
                continue;
            }

            $this->ParseHotelPDF($email, $text);
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

    public function detectDeadLine($h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/{$this->opt($this->t('Free cancellation until'), true)}\s*(?<month>\d{1,2})\D{1,2}(?<day>\d{1,2})\D{1,2}(?<year>\d{4})\s*,\s*(?<time>{$this->patterns['time']})/i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($m['day'] . '.' . $m['month'] . '.' . $m['year'] . ', ' . $m['time']));
        }
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['hotelInfoStart']) || empty($phrases['bookingDetailsStart'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['hotelInfoStart']) !== false
                && $this->strposArray($text, $phrases['bookingDetailsStart']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
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

    private function opt($field, bool $addSpaces = false): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) use ($addSpaces) {
            return $addSpaces ? $this->addSpacesWord($s) : str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function addSpacesWord(string $text): string
    {
        return preg_replace('/([^\s\\\])/u', '$1\s*', preg_quote($text, '/'));
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

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 13 Apr 2 0 2 5
            '/^(\d{1,2})[,.\s]*([[:alpha:]]+)[,.\s]*(\d)\s*(\d)\s*(\d)\s*(\d)$/u',
        ];
        $out = [
            '$1 $2 $3$4$5$6',
        ];

        return preg_replace($in, $out, $text);
    }
}
