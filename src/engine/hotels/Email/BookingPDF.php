<?php

namespace AwardWallet\Engine\hotels\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingPDF extends \TAccountChecker
{
    public $mailFiles = "hotels/it-220158363.eml, hotels/it-220158497.eml, hotels/it-267414225.eml, hotels/it-461443600.eml";
    public $lang = '';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
        "da" => [
            'Hotels.com itinerary' => 'Hotels.com-rejseplansnr.',
            'Reservation details'  => 'Reservationsoplysninger',
            //'Booking details' => '',

            'Reserved for'    => 'Reserveret til',
            'Payment details' => 'Betalingsoplysninger',
            //'Purchase date:' => '',
            'Change and cancellation rules' => '',
            'Check-in'                      => 'Indtjekning',
            'Check-out'                     => 'Udtjekning',
            //'room' => '',
            'Booked for:'  => 'Reserveret til',
            'Room price'   => 'Værelsespris',
            'Room details' => 'Værelsesoplysninger',
            'Taxes & Fees' => 'Skatter og gebyrer',
            'Total'        => 'I alt',
            //'Total Invoice Amount' => '',

            'Stay in'  => 'Ophold i',
            'Location' => 'Beliggenhed',
            'adult'    => 'voksne',
        ],
    ];

    public $detectLang = [
        'en' => ['itinerary'],
        'da' => ['Indtjekning'],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->assignLang($text);

            if (strpos($text, $this->t('Hotels.com itinerary')) !== false
                && (strpos($text, $this->t('Reservation details')) !== false
                    || strpos($text, $this->t('Booking details')) !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]vacv\.com$/', $from) > 0;
    }

    public function ParseHotelPDF(Email $email, $text)
    {
        $email->ota()
            ->confirmation($this->re("/{$this->opt($this->t('Hotels.com itinerary:'))}\s*(\d+)\n/", $text));

        $h = $email->add()->hotel();

        $travellers = $this->res("/{$this->opt($this->t('Booked for:'))} *(.+)/", $text);
        $travellers = array_filter(array_unique($travellers));

        $h->general()
            ->noConfirmation()
            ->date(strtotime($this->re("/{$this->opt($this->t('Purchase date:'))}\s*(.+)\n/", $text)))
            ->travellers($travellers, true);

        if (preg_match("/{$this->opt($this->t('Booking details'))}\n+(?<hotelName>.+)\n+(?<address>.+)\n+ *{$this->opt($this->t('Check-in:'))}/", $text, $m)) {
            $this->logger->debug('Yes');
            $h->hotel()
                ->name(trim($m['hotelName']))
                ->address(trim($m['address']));
        }

        $h->booked()
            ->checkIn(strtotime($this->re("/{$this->opt($this->t('Check-in:'))}\s*(.+)/", $text)))
            ->checkOut(strtotime($this->re("/{$this->opt($this->t('Check-out:'))}\s*(.+)/", $text)))
            ->rooms($this->re("/(\d+)\s*{$this->opt($this->t('room'))}/", $text));

        if ($h->getRoomsCount() === 1) {
            $description = $this->re("/(.+)\n{$this->opt($this->t('Booked for:'))}/", $text);
            $rateText = $this->re("/{$this->opt($this->t('Room price'))}\n+(.+)\n+{$this->opt($this->t('Taxes & Fees'))}/us",
                $text);

            if (preg_match_all("/\s+\D([\d\.\,]+)(?:\n|$)/u", $rateText, $m)) {
                $rate = implode(' / night, ', $m[1]);
            }

            if (!empty($description) || !empty($rate)) {
                $room = $h->addRoom();

                if (!empty($description)) {
                    $room->setDescription($description);
                }

                if (!empty($rate)) {
                    $room->setRate($rate . ' / night');
                }
            }
        } else {
            $descriptions = $this->res("/(.+)\n{$this->opt($this->t('Booked for:'))}/", $text);
            $rateTitle = str_replace('\s+', '(?: | \d+ )', $this->opt($this->t('Room price')));
            $rateTexts = $this->res("/{$rateTitle}\n+(.+?)\n+{$this->opt($this->t('Taxes & Fees'))}/us",
                $text);

            $rates = [];

            foreach ($rateTexts as $rateText) {
                if (preg_match_all("/\s+\D([\d\.\,]+)(?:\n|$)/u", $rateText, $m)) {
                    $rates[] = $m[1];
                }
            }

            if (empty($descriptions) && !empty($rates)) {
                foreach ($rates as $rate) {
                    $h->addRoom()
                        ->setRates($rate);
                }
            } elseif (!empty($descriptions) && empty($rates)) {
                foreach ($descriptions as $description) {
                    $h->addRoom()
                        ->setDescription($description);
                }
            } elseif (!empty($descriptions) && !empty($rates) && count($descriptions) == count($rates)) {
                foreach ($descriptions as $i => $description) {
                    $h->addRoom()
                        ->setRates($rates[$i])
                        ->setDescription($description);
                }
            }
        }

        $priceText = $this->re("/\n *{$this->opt($this->t('Total'))} {2,}(.+?) *\n/u", $text);

        if (
            // Total                  $7 428 16
            preg_match("/^(?<currency>\D)\s*(?<total>\d[\d ]*?)(?<totaldecimical> \d\d)$/u", $priceText, $m)
            || preg_match("/^(?<currency>\D)\s*(?<total>[\d\.\, ]+?)$/u", $priceText, $m)
        ) {
            $currency = $this->normalizeCurrency($m['currency']);

            $h->price()
                ->total(PriceHelper::parse($m['total'] . ((!empty($m['totaldecimical']) ? '.' . trim($m['totaldecimical']) : '')), $currency))
                ->currency($currency);

            $taxTexts = $this->res("/{$this->opt($this->t('Taxes & Fees'))}\s*\D\s*([\d\.\,]+)\n/u", $text);
            $tax = 0.0;

            foreach ($taxTexts as $taxText) {
                $tax += $taxText;
            }

            if (!empty($tax)) {
                $h->price()
                    ->tax(PriceHelper::parse($tax, $currency));
            }
            $rateTitle = str_replace('\s+', '(?: | \d+ )', $this->opt($this->t('Room price')));
            $feeTexts = implode("\n\n", $this->res("/{$this->opt($this->t('Taxes & Fees'))}.+([\s\S]*?)\n *(?:{$rateTitle}|{$this->opt($this->t('Total'))})/u", $text));
            $fees = [];

            if (preg_match_all("/^ *(.+) {3,}\D{0,5}(\d[\d ,.]*)\D{0,5}\s*$/m", $feeTexts, $m)) {
                foreach ($m[0] as $i => $v) {
                    if (isset($fees[trim($m[1][$i])])) {
                        $fees[trim($m[1][$i])] += PriceHelper::parse($m[2][$i], $currency);
                    } else {
                        $fees[trim($m[1][$i])] = PriceHelper::parse($m[2][$i], $currency);
                    }
                }
            }

            foreach ($fees as $name => $amount) {
                $h->price()
                    ->fee($name, $amount);
            }
        }
    }

    public function ParseHotelPDF2(Email $email, $text)
    {
        $h = $email->add()->hotel();

        $email->ota()
            ->confirmation($this->re("/{$this->opt($this->t('Hotels.com itinerary'))}\:?\s*(\d+)\n/", $text), 'Hotels.com itinerary');

        $travellersText = $this->re("/{$this->opt($this->t('Reserved for'))}\s*(.+)\s*{$this->opt($this->t('Payment details'))}/s", $text);

        if (preg_match_all("/^([[:alpha:]][-&.\'’[:alpha:] ]*[[:alpha:]])\,?\s*\(?\d+\s*{$this->opt($this->t('adult'))}\)?\n$/mu", $travellersText, $m)) {
            $travellers = $m[1];
        }

        $h->general()
            ->noConfirmation()
            ->travellers($travellers, true);

        if (stripos($text, $this->t('Change and cancellation rules')) !== false) {
            $h->general()
                ->cancellation(str_replace("\n", "", $this->re("/{$this->opt($this->t('Change and cancellation rules'))}\n(.+)\n+{$this->opt($this->t('Payment details'))}/s", $text)));
        }

        $h->hotel()
            ->name($this->re("/{$this->opt($this->t('Stay in'))}\s*(.+)\n/", $text))
            ->address($this->re("/{$this->opt($this->t('Location'))}\n(.+)\n+{$this->opt($this->t('Room details'))}/", $text));

        $year = $this->re("/{$this->opt($this->t('Stay in'))}.+\n.*\s(\d{4})\s*\-/", $text);
        $regexp = "/{$this->opt($this->t('Check-in'))}\:?\s*{$this->opt($this->t('Check-out'))}\:?\n(?<inDay>.+)[ ]{5,}(?<outDay>.+)\n(?:kl\.\s*)?(?<inTime>[\d\:\.]+\s*A?P?M?)\s+(?:kl\.\s*)?(?<outTime>(?:[\d\:\.]+\s*A?P?M?|noon))/";

        if (preg_match($regexp, $text, $m)) {
            if (stripos($m['inDay'], $year) == false) {
                $h->booked()
                    ->checkIn($this->normalizeDate($m['inDay'] . ' ' . $year . ', ' . str_replace('.', ':', $m['inTime'])))
                    ->checkOut($this->normalizeDate($m['outDay'] . ' ' . $year . ', ' . str_replace('.', ':', $m['outTime'])));
            } else {
                $h->booked()
                    ->checkIn($this->normalizeDate(trim($m['inDay']) . ', ' . str_replace('.', ':', $m['inTime'])))
                    ->checkOut($this->normalizeDate(trim($m['outDay']) . ', ' . str_replace('.', ':', $m['outTime'])));
            }
        }

        $guests = $this->re("/(\d+)\s*{$this->opt($this->t('adult'))}/", $text);

        if (!empty($guests)) {
            $h->booked()
                ->guests($guests);
        }

        $description = $this->re("/\n+(.+)\n{$this->opt($this->t('Reserved for'))}/", $text);
        $rateText = $this->re("/{$this->opt($this->t('Room price'))}\n+(.+)\n+{$this->opt($this->t('Taxes & Fees'))}/us", $text);

        if (preg_match_all("/\s+\D([\d\.\,]+)(?:\n|$)/u", $rateText, $m)) {
            $rate = implode(' / night, ', $m[1]);
        }

        if (!empty($description) || !empty($rate)) {
            $room = $h->addRoom();

            if (!empty($description)) {
                $room->setDescription($description);
            }

            if (!empty($rate)) {
                $room->setRate($rate . ' / night');
            }
        }

        $priceText = $this->re("/{$this->opt($this->t('Total'))}\s*(\D[\d\.\,\s]+)\n/u", $text);

        if (empty($priceText)) {
            $priceText = $this->re("/{$this->opt($this->t('Total'))}\s*([\d\.\,\s]+\D{1,5})\n/u", $text);
        }

        if (preg_match("/^\s*(?<currency>\D)\s*(?<total>[\d\.\,]+)/u", $priceText, $m)
        || preg_match("/^\s*(?<total>[\d\.\,]+)\s*(?<currency>[A-z\.]{3})/u", $priceText, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);

            $h->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $tax = $this->re("/{$this->opt($this->t('Taxes & Fees'))}\s*\D\s*([\d\.\,]+)\n/u", $text);

            if (!empty($tax)) {
                $h->price()
                    ->tax($tax);
            }
        }

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->assignLang($text);

            if (strpos($text, $this->t('Booking details')) !== false) {
                $this->ParseHotelPDF($email, $text);
            } elseif (strpos($text, $this->t('Reservation details')) !== false) {
                $this->ParseHotelPDF2($email, $text);
            }
        }

        if (preg_match("/{$this->opt($this->t('Total Invoice Amount'))}\s*([\d\.]+)\s*([A-Z]{3})/u", $text, $m)) {
            $email->price()
                ->total($m[1])
                ->currency($m[2]);
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

    protected function assignLang($text)
    {
        foreach ($this->detectLang as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
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

    private function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return [];
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

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'SEK' => ['kr.'],
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.'],
            'USD' => ['US$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function normalizeDate($str)
    {
        $str = str_replace(['noon'], ['12:00 PM'], $str);

        $in = [
            "#^\w+\,\s*(\d+\s*\w+\s*\d{4}\,\s*[\d\:]+\s*A?P?M?)$#u", //Tue, 8 Nov 2022, 3:00 PM
            "#^\D+(\d+)\.\s*(\w+)\s*(\d{4})\,\s*([\d\:]+)\s*$#", //torsdag den 26. januar 2023, 14:00
        ];
        $out = [
            "$1",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Cancellations or changes made after\s*(?<time>[\d\:]+).*on\s*(?<date>\d+\s*\w+\s*\d{4}) or no-shows are subject to a property fee/", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m['date'] . ' ' . $m['time']));
        }

        if (preg_match("/Free cancellation until\s*(?<day>\w+\s*\d+)\s*at\s*(?<time>[\d\:]+\s*a?p?m)/", $cancellationText, $m)) {
            $year = date('Y', $h->getCheckInDate());
            $h->booked()
                ->deadline(strtotime($m['day'] . ' ' . $year . ', ' . $m['time']));
        }
    }
}
