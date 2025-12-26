<?php

namespace AwardWallet\Engine\scandichotels\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "scandichotels/it-40069253.eml";

    public $reFrom = "@scandichotels.com";
    public $reSubject = [
        "en" => ["Scandic Upplandsgatan", "Confirmation No"],
    ];
    public $reBody = 'Scandic';
    public $reBody2 = [
        "en" => [
            "About your reservatio",
            "We are pleased to confirm your reservation",
            "Thank you for your reservation. We look forward to your stay",
        ],
        "da" => ["Tak for din reservation"],
        "sv" => ["Din reservation"],
    ];
    public $pdfPattern = "[a-z_]+\d+.pdf";

    public static $dictionary = [
        "en" => [
            //			"Booking number" => "",
            //			"Booking date" => "",
            "About your reservation" => ["About your reservation", "About your reservatio"],
            "Address:"               => ["Address:", "Address"],
            "Call:"                  => ["Call:", "Phone"],
            //			"Arrival date" => "",
            //			"Check-in time" => "",
            //			"Departure date" => "",
            //			"Room and rate description" => "",
            //			"No. of rooms" => "",
            //			"No. of adults/children" => "",
            //			"Room type" => "",
            //			"Rate type" => "",
            //			"Total incl. VAT" => "",
            //			"Cancellation Policy" => "",
        ],
        "sv" => [
            "Booking number"            => "Bokningsnummer",
            "Booking date"              => "Bokningsdatum",
            "About your reservation"    => "Din reservation",
            "Address:"                  => "Adress:",
            "Call:"                     => "Tel:",
            "Arrival date"              => "Ankomstdatum",
            "Check-in time"             => "Incheckningstid",
            "Departure date"            => "Avresedatum",
            "Room and rate description" => "Uppgifter om rum och pris",
            "No. of rooms"              => "Antal rum",
            "No. of adults/children"    => "Antal personer",
            "Room type"                 => "Rumstyp",
            "Rate type"                 => "Pristyp",
            "Total incl. VAT"           => "Totalt pris inkl. moms",
            //			"Cancellation Policy" => "",
        ],
        "da" => [
            "Booking number"            => "Bookingnummer",
            "Booking date"              => "Bookingdato",
            "About your reservation"    => "Din reservation",
            "Address:"                  => ["Adresse:", "Adresse"],
            "Call:"                     => ["Tel:", "Tel"],
            "Arrival date"              => "Ankomstdato",
            "Check-in time"             => "Check-in tid",
            "Departure date"            => "Afrejsedato",
            "Room and rate description" => "Beskrivelse af værelse og pris",
            "No. of rooms"              => "Antal værelser",
            "No. of adults/children"    => "Antal voksne/børn",
            "Room type"                 => "Værelsestype",
            "Rate type"                 => "Pristype",
            "Total incl. VAT"           => "Total inkl. moms",
            "Cancellation Policy"       => "Afbestillingspolitik",
        ],
    ];

    public $lang = "";

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $subjects) {
            foreach ($subjects as $re) {
                if (stripos($headers["subject"], $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($text, $this->reBody) === false && strpos($text, 'scandichotels.com') === false) {
            return false;
        }

        foreach ($this->reBody2 as $body) {
            foreach ($body as $re) {
                if (strpos($text, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return $email;
        }
        $pdf = $pdfs[0];

        if (($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return $email;
        }

        $this->assignLang($textPdf);

        $this->parseHotel($email, $textPdf);
        $email->setType('ConfirmationPdf' . ucfirst($this->lang));

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

    public function assignLang($textPdf)
    {
        foreach ($this->reBody2 as $lang => $body) {
            foreach ($body as $re) {
                if (strpos($textPdf, $re) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }
    }

    private function parseHotel(Email $email, string $text)
    {
        if (is_array($this->t("Address:"))) {
            foreach ($this->t("Address:") as $word) {
                if (strpos($text, $word) !== false) {
                    $posBegin = strpos($text, $word);
                }
            }
        } else {
            $posBegin = strpos($text, $this->t("Address:"));
        }

        $table = substr($text, $posBegin, strpos($text, $this->t("Room and rate description")) - $posBegin);

        $pos = [0, strlen($this->re("#(.+)" . $this->t("Arrival date") . "#", $table)) - 2];
        $table = $this->splitCols($table, $pos);

        $h = $email->add()->hotel();

        $h->general()
            ->traveller($this->re("#\n[ ]*((?:[&Mrs]+|)[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s+{$this->preg_implode($this->t("Booking number"))}#u",
                $text))
            ->confirmation($this->re("#" . $this->preg_implode($this->t("Booking number")) . "[ ]+(.+)#", $text))
            ->date2($this->normalizeDate($this->re("#" . $this->preg_implode($this->t("Booking date")) . "\s+(.+)#",
                $text)));

        $name = $this->re("#" . $this->preg_implode($this->t("About your reservation")) . "\n+(.+)#", $text);

        if (empty($name)) {
            $name = $this->re("#" . $this->t("We are looking forward to welcoming you to the") . "(.+?)\.#", $text);
        }

        $h->hotel()->name($name);

        $h->hotel()
            ->address(preg_replace('/\s+/', ' ',
                $this->re("#" . $this->preg_implode($this->t("Address:")) . "[ ]+([\s\S]*?)\s+" . $this->preg_implode($this->t("Call:")) . "#",
                    $table[0])))
            ->phone($this->re("#" . $this->preg_implode($this->t("Call:")) . "[ ]+(.+)#", $table[0]));

        $patterns['time'] = '\d{1,2}(?:[.:]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        $h->booked()
            ->checkIn2($this->normalizeDate($this->re("#" . $this->preg_implode($this->t("Arrival date")) . "[ ]+(.+)#",
                    $table[1]) . ', ' . $this->re("#" . $this->preg_implode($this->t("Check-in time")) . "\s+({$patterns['time']})#",
                    $table[1])))
            ->checkOut2($this->normalizeDate($this->re("#" . $this->preg_implode($this->t("Departure date")) . "[ ]+(.+)#",
                $table[1])));

        $h->booked()->rooms($this->re("#" . $this->preg_implode($this->t("No. of rooms")) . "[ ]+(.+)#", $text));

        if (preg_match("#{$this->preg_implode($this->t("No. of adults/children"))}[ ]+(\d{1,3})[ ]*\/[ ]*(\d{1,3})\n#",
            $text, $m)) {
            $h->booked()
                ->guests($m[1])
                ->kids($m[2]);
        }

        $room = $h->addRoom();
        $room->setType($this->re("#" . $this->preg_implode($this->t("Room type")) . "[ ]+(.+)#", $text));
        $room->setRateType($this->re("#" . $this->preg_implode($this->t("Rate type")) . "[ ]+(.+)#", $text), false,
            true);

        $rateText = $this->re("/({$this->preg_implode($this->t("Total incl. VAT"))}.+)/s", $text);

        if (preg_match_all("/\s*(\d+\.\d+\.\d{1,2}\s*[\d\.\.\,]+\s*[A-Z]{3})/", $rateText, $m)) {
            $room->setRate(implode(', ', preg_replace("/\s+/", " ", $m[1])));
        }

        if (preg_match("#{$this->preg_implode($this->t("Total incl. VAT"))}[ ]+(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[A-Z]{3})[^A-Z]#",
            $text, $m)) {
            $h->price()
                ->total($this->amount($m['amount']))
                ->currency($m['currency']);
        }

        $cancellation = preg_replace('/\s+/', ' ',
            $this->re("#\n[ ]*{$this->preg_implode($this->t("Cancellation Policy"))}\n+[ ]*([\s\S]+?)(?:\n+[ ]*[[:upper:]][[:lower:]]|\n\n|$)#",
                $text));

        if (empty($cancellation)) {
            $cancellation = $this->re("#{$this->preg_implode($this->t("Cancellation Policy"))}:\n\s*([\s\S]+?)\.\s*\n#",
                $text);
        }

        $h->general()->cancellation($cancellation);

        if (!empty($cancellation)) {
            if (preg_match("#Cancellation must be made prior to (?<hour>{$patterns['time']}) on day of arrival\.#",
                    $cancellation, $m) // en
                || preg_match("#Afbestilling skal ske senest kl. (?<hour>{$patterns['time']}) på ankomstdagen.#",
                    $cancellation, $m)
            ) {
                $h->booked()->deadlineRelative('0 days', $m['hour']);
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\.(\d+)\.(\d{2}),\s+(\d+:\d+)$#", //07.08.17, 14:00
            "#^(\d+)\.(\d+)\.(\d{2})$#", //07.08.17, 14:00
        ];
        $out = [
            "$1.$2.20$3, $4",
            "$1.$2.20$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function TableHeadPos($row)
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

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {
            return preg_quote($v, '#');
        }, $field)) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,\s](\d{3})#", "$1", $s));
    }
}
