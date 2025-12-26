<?php

namespace AwardWallet\Engine\hays\Email;

use AwardWallet\Schema\Parser\Email\Email;

class CustomerConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "hays/it-48662907.eml";
    public $From = '@hays-travel.co.uk';
    public $Subject = ['CustomerConfirmation - Hays Tour Operating Ltd'];
    public $flightNum = 0;
    public $reservDate;
    public $segType;
    public $flightSegUniq;
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            "Departure Airport:" => ["Departure Airport:", "Departure"],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            //$this->logger->debug($textPdf);
            $description = trim($this->cutText('Your ', ': HTR-', $textPdf));
            $email->ota()
                ->confirmation(trim($this->cutText('Your Booking Reference: ', 'Your Party Details', $textPdf)), $description);

            $totalPrice = $this->re('/Total Holiday Cost:\s+(.+)\n/', $textPdf);

            $email->price()
                ->currency($this->normalizeCurrency($this->re('/(.)/u', $totalPrice)))
                ->total($this->normalizePrice($this->re('/.(\d.+)/', $totalPrice)));

            $travelText = $this->cutText('D.O.B.', 'Accommodation', $textPdf);

            if (preg_match_all('/(.+)\s+adult/', $travelText, $ms)) {
                $travellers = $ms[1];
            }

            $rowArray = preg_split('/\n/', $textPdf);

            foreach ($rowArray as $row) {
                if ($this->re('/(Issue Date:)/', $row) == 'Issue Date:') {
                    $this->reservDate = $this->re('/Issue Date:\s+(.+)/', $row);

                    continue;
                }

                //HOTEL
                if ($row == 'Accommodation') {
                    $this->segType = 'HOTEL';
                    $h = $email->add()->hotel();
                    $h->general()
                        ->travellers($travellers, true);
                    $h->setReservationDate($this->normalizeDate($this->reservDate));

                    continue;
                }

                if ($this->re('/(Hotel:)/', $row) == 'Hotel:' && $this->segType == 'HOTEL') {
                    $h->hotel()
                        ->noAddress()
                        ->name($this->re('/Hotel:\s+(.+)/', $row));

                    continue;
                }

                if ($this->re('/(Supplier Ref:)/', $row) == 'Supplier Ref:' && $this->segType == 'HOTEL') {
                    $h->general()
                        ->confirmation($this->re('/Supplier Ref:\s+(\d+)/', $row), 'Supplier Ref');

                    continue;
                }

                if ($this->re('/(Resort:)/', $row) == 'Resort:' && $this->segType == 'HOTEL') {
                    continue;
                }

                if (($this->re('/(Room[(]s[)]:)/', $row) == 'Room(s):' || $this->re('/(Adults)/', $row) == 'Adults') && $this->segType == 'HOTEL') {
                    if (preg_match('/Room[(]s[)][:]\s+(?<type>\D+)\s+[?(](?<description>\D+)[)]\s+[-]\s+/', $row, $m)) {
                        $h->addRoom()
                            ->setType($m['type'])
                            ->setDescription($m['description']);
                    } elseif (preg_match('/(?:Room[(]s[)][:]|\s+)\s+(?<type>\D+)\s+[-]\s+/', $row, $m)) {
                        $h->addRoom()
                            ->setType($m['type']);
                    }
                    $this->logger->warning($row);

                    $adultCount = $this->re('/Adults:\s+(\d+)[,]/', $row);
                    $childrenCount = $this->re('/Children:\s+(\d+)[,]/', $row);
                    $infantsCount = $this->re('/Infants:\s+(\d+)/', $row);

                    if ($adultCount > 0) {
                        $h->booked()
                            ->guests($h->getGuestCount() + $adultCount);
                    }

                    if (($childrenCount >= 0) | ($infantsCount >= 0)) {
                        $h->booked()
                            ->kids($h->getKidsCount() + $childrenCount + $infantsCount);
                    }

                    continue;
                }

                if ($this->re('/(Board Type:)/', $row) == 'Board Type:' && $this->segType == 'HOTEL') {
                    continue;
                }

                if ($this->re('/(Check In:)/', $row) == 'Check In:' && $this->segType == 'HOTEL') {
                    $h->booked()
                        ->CheckIn($this->normalizeDate($this->re('/Check In:\s+(\S+\s+\S+)/', $row)));

                    continue;
                }

                if ($this->re('/(Check Out:)/', $row) == 'Check Out:' && $this->segType == 'HOTEL') {
                    $h->booked()
                        ->CheckOut($this->normalizeDate($this->re('/Check Out:\s+(\S+\s+\S+)/', $row)));

                    continue;
                }

                //FLIGHT
                if ($row == 'Flight') {
                    $this->segType = 'FLIGHT';

                    $this->flightSegUniq = 1;

                    if ($this->flightNum == 0) {
                        $f = $email->add()->flight();
                        $f->general()
                            ->travellers($travellers, true);
                        $f->setReservationDate($this->normalizeDate($this->reservDate));
                        $this->flightNum = 1;
                    }

                    continue;
                }

                if ($this->re('/(Departure Date:)/', $row) == 'Departure Date:' && $this->segType == 'FLIGHT') {
                    if ($this->flightSegUniq > 1) {
                        continue;
                    }

                    $s = $f->addSegment();

                    if (preg_match('/Departure Date(?:[:]\s+|:)(?<depDate>\d+.\d+.\d+)\s+(?<flightNumber>[A-Z\d]{4,})\s+(?<depTime>\d+[:]\d+)\s+(?<arrTime>\d+[:]\d+)/', $row, $m)) {
                        $s->airline()
                            ->name($this->re('/(\D+)\d+/', $m['flightNumber']))
                            ->number($this->re('/\D+(\d+)/', $m['flightNumber']));
                        $s->departure()
                            ->date($this->normalizeDate($m['depDate'] . ' ' . $m['depTime']));

                        if (!empty($arrDate = $this->re('/Departure Date:.+\d+[:]\d+\s+\d+[:]\d+\s+[(](\d+.\d+.\d+)[)]/', $row))) {
                            $s->arrival()
                                ->date($this->normalizeDate($arrDate . ' ' . $m['arrTime']));
                        } else {
                            $s->arrival()
                                ->date($this->normalizeDate($m['depDate'] . ' ' . $m['arrTime']));
                        }

                        $this->flightSegUniq = 2;
                    }

                    continue;
                }

                if (($this->re('/(Departure Airport:)/', $row) == 'Departure Airport:'
                    || $this->re('/(Departure)/', $row) == 'Departure')
                    && $this->segType == 'FLIGHT') {
                    $code = $this->re("/{$this->opt($this->t('Departure Airport:'))}.+[(](.+)[)]/", $row);

                    if (!empty($code)) {
                        $s->departure()
                            ->code($code);
                    }

                    continue;
                }

                if ($this->re('/(Arrival Airport:)/', $row) == 'Arrival Airport:' && $this->segType == 'FLIGHT') {
                    $s->arrival()
                        ->code($this->re('/Arrival Airport:.+[(](.+)[)]/', $row));

                    continue;
                }

                if ($this->re('/(Airline:)/', $row) == 'Airline:' && $this->segType == 'FLIGHT') {
                    continue;
                }

                if ($this->re('/(Airline Locator:)/', $row) == 'Airline Locator:' && $this->segType == 'FLIGHT') {
                    if (!empty($confirmation = $this->re('/Airline Locator[:].+([A-Z\d]{6,8})/', $row))) {
                        $s->airline()
                            ->confirmation($confirmation);
                        $f->general()
                            ->noConfirmation();
                    } else {
                        $f->general()
                            ->noConfirmation();
                    }

                    continue;
                }

                if ($this->re('/(Flight Class:)/', $row) == 'Flight Class:' && $this->segType == 'FLIGHT') {
                    $s->setCabin($this->re('/Flight Class:\s+(.+)/', $row));

                    continue;
                }

                if ($this->re('/(Our Reference:)/', $row) == 'Our Reference:' && $this->segType == 'FLIGHT') {
                    continue;
                }

                //TRANSFERS
                if ($row == 'Transfers') {
                    $this->segType = 'TRANSFER';
                    $t = $email->add()->transfer();
                    $t->general()
                        ->travellers($travellers, true);
                    $t->setReservationDate($this->normalizeDate($this->reservDate));
                    $ts = $t->addSegment();

                    continue;
                }

                if ($this->re('/(Return\s+Date.)/', $row) == 'Return Date:' && $this->segType == 'TRANSFER') {
                    $ts->arrival()
                        ->noDate();

                    continue;
                }

                if ($this->re('/(Date:)/', $row) == 'Date:' && $this->segType == 'TRANSFER') {
                    $depDate = $this->re('/Date:\s+(\d+.\d+.\d+\s+\d+[:]\d+)/', $row);
                    $ts->departure()
                        ->date($this->normalizeDate($depDate));

                    continue;
                }

                if ($this->re('/(Type:)/', $row) == 'Type:' && $this->segType == 'TRANSFER') {
                    continue;
                }

                if ($this->re('/(Supplier Ref:)/', $row) == 'Supplier Ref:' && $this->segType == 'TRANSFER') {
                    $t->general()
                        ->confirmation($this->re('/Supplier Ref:\s+([A-Z\d\-]{6,15})/u', $row), 'Supplier Ref');

                    continue;
                }

                if ($this->re('/(Pick Up:)/', $row) == 'Pick Up:' && $this->segType == 'TRANSFER') {
                    $ts->departure()
                        ->name($this->re('/Pick Up:\s+(.+)/', $row));

                    if (!empty($codeDep = $this->re("/Airport\s+\(([A-Z]{3})\)/", $row))) {
                        $ts->departure()
                            ->code($codeDep);
                    }

                    if (!empty($hotelAddress = $this->re("/Accommodation\nHotel\:\s+{$ts->getDepName()}.+?\nSupplier\sRef\:.+?\nResort\:\s+(.+)\n/", $textPdf))) {
                        if (empty($this->re("/({$hotelAddress})/", $ts->getDepName()))) {
                            $ts->departure()
                                ->name($ts->getDepName() . ', ' . $hotelAddress);
                        }
                    }

                    continue;
                }

                if ($this->re('/(Drop Off:)/', $row) == 'Drop Off:' && $this->segType == 'TRANSFER') {
                    $ts->arrival()
                        ->name($this->re('/Drop Off:\s+(.+)/', $row));

                    if (!empty($codeArr = $this->re("/Airport\s+\(([A-Z]{3})\)/", $row))) {
                        $ts->arrival()
                            ->code($codeArr);
                    }

                    continue;
                }

                if ($this->segType == 'TRANSFER' && empty($ts->getArrDate())) {
                    $ts->arrival()
                        ->noDate();
                }
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@]hays[-]travel\.co\.uk/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->Subject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (((stripos($textPdf, 'Hays Travel') > 0) || (stripos($textPdf, 'Hays Faraway') > 0) || (stripos($textPdf, 'Hays Tour Operating') > 0))
                && (stripos($textPdf, 'Your Booking Reference') > 0)
                && (stripos($textPdf, 'Total Holiday Cost') > 0)) {
                return true;
            }
        }

        return false;
    }

    protected function normalizePrice($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    private function cutText($start, $end, $text)
    {
        if (!empty($start) && !empty($end) && !empty($text)) {
            $txt = stristr(stristr($text, $start), $end, true);

            return substr($txt, strlen($start));
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

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+).(\d+).(\d+)\s+[(](\d+[:]\d+)[)]#", // 30/12/2019 (14:10)
            "#^(\d+).(\d+).(\d+)\s+(\d+[:]\d+)#", // 20/10/2020 14:00
            "#^(\d+)[/](\d+)[/](\d+)#", // 03/01/2020
        ];
        $out = [
            "$1.$2.$3 $4",
            "$1.$2.$3 $4",
            "$1.$2.$3",
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }
}
