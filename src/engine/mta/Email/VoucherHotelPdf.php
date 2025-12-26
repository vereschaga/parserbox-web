<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class VoucherHotelPdf extends \TAccountChecker
{
    public $mailFiles = "mta/it-114696577.eml, mta/it-21784412.eml, mta/it-24937437.eml, mta/it-27823219.eml, mta/it-30478815.eml, mta/it-41343414.eml, mta/it-49772726.eml";

    public $reFrom = "mtatravel.com.au";
    public $reBody = [
        'en'  => ['Your Reservation is Confirmed!', 'Check-in'],
        'en1' => ['Itinerary #', 'Check-in'],
        'pt'  => ['Nº do itinerário', 'Check-in'],
        'pt1' => ['Sua reserva foi confirmada!', 'Check-in'],
        'es'  => ['Reservado para', 'Check-in'],
    ];
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'adult'=> ['adult', 'adults'],
        ],
        'pt' => [
            'Itinerary #'             => 'Nº do itinerário',
            'Check-in'                => 'Check-in',
            'Check-out'               => 'Check-out',
            'Check-in time starts at' => 'Horário inicial do check-in:',
            'Reserved for:'           => 'Reservado para:',
            'adult'                   => ['adultos'],
            'child'                   => 'criança',
            'Room'                    => 'Quarto',
            'Includes:'               => 'Inclui:',
            'Cancel/Change Rules:'    => 'Regras de cancelamento/alteração:',
            // need examples
            //            'Price' => '',
            //            'Total:' => '',
            //            'Taxes and Fees:' => '',
            //            'Trip Net Price' => ''
        ],

        'es' => [
            'Itinerary #'             => 'No. de itinerario',
            'Check-in'                => 'Check-in',
            'Check-out'               => 'Check-out',
            'Check-in time starts at' => 'Hora de inicio de check-in:',
            'Reserved for:'           => 'Reservado para:',
            'adult'                   => ['adultos'],
            //'child'                   => '',
            'Room'                 => 'Habitación',
            'Includes:'            => '',
            'Cancel/Change Rules:' => 'por cancelaciones y cambios:',
            'Confirmation'         => ' No. de confirmación',
            // need examples
            //            'Price' => '',
            //            'Total:' => '',
            //            'Taxes and Fees:' => '',
            //            'Trip Net Price' => ''
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $i => $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }

            if (!$this->assignLang($text)) {
                $this->logger->debug("Can't determine a language at attachment-" . $i);

                continue;
            }

            $this->parseEmail($text, $email);
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, 'MTA Travel') !== false
                    || stripos($text, 'Mosaic Travel') !== false
                    || stripos($text, 'Get Out N About Travel') !== false
                    || stripos($text, 'Wotton Travel Ltd') !== false
                    || stripos($text, 'Expedia') !== false
                    || stripos($text, 'FLYING COLOURS TRAVEL') !== false
                    || stripos($text, 'The Well Connected Traveller') !== false
                    || stripos($text, 'Reservado para') !== false
                    || stripos($text, 'Check-in time starts at') !== false)
                && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail($textPDF, Email $email): void
    {
        if (preg_match("#{$this->opt($this->t('Itinerary #'))}\s+(?<confNo>\d+)\s+(?<hName>.+?)\s*?\n\n(?<table> +[^\n]+.+?\n\n)\s+(?<tableContact>[^\n]+.+?)\n\n#su",
            $textPDF, $m)) {
            $h = $email->add()->hotel();
            $h->general()->confirmation($m['confNo']);
            $h->hotel()
                ->name(trim(preg_replace("#\s+#", ' ', $m['hName'])));
        } else {
            $this->logger->debug("other format");

            return;
        }
        $table = $m['table'];
        $tableContact = $m['tableContact'];

        $table = $this->splitCols($table, $this->colsPos($table));

        if (count($table) != 2) {
            $this->logger->debug("other format dates position");

            return;
        }

        $tableContact = $this->splitCols($tableContact, $this->colsPos($tableContact));

        if (count($tableContact) == 0 || count($tableContact) > 2) {
            $this->logger->debug("other format address/phone position");

            return;
        }
        $h->hotel()
            ->address(trim(preg_replace("#\s+#", ' ', $tableContact[0])));

        if (isset($tableContact[1]) && strlen($tableContact[1]) > 5) {
            $h->hotel()->phone(trim(preg_replace("#\s+#", ' ', $tableContact[1])));
        }

        $h->booked()
            ->checkIn($this->normalizeDate($this->re("#(.+)\s+{$this->opt($this->t('Check-in'))}#u", $table[0])))
            ->checkOut($this->normalizeDate($this->re("#(.+)\s+{$this->opt($this->t('Check-out'))}#u", $table[1])));

        if (preg_match("#{$this->t('Check-in time starts at')} (\d+(?:[h:]\d+)?(?:[ ]*[ap]m)?)#i", $textPDF, $m)) {
            $h->booked()->checkIn(strtotime(str_replace("h", ":", $m[1]), $h->getCheckInDate()));
        }

        if (preg_match_all("#{$this->t('Reserved for:')} (.+)#u", $textPDF, $m)) {
            $h->general()->travellers($m[1], true);
        }

        $h->booked()
            ->guests($this->re("#{$this->t('Reserved for:')} .+\s+(\d+) {$this->opt($this->t('adult'))}#iu", $textPDF))
            ->kids($this->re("#{$this->t('Reserved for:')} .+\s+\d+ {$this->opt($this->t('adult'))}, (\d+) {$this->opt($this->t('child'))}#iu", $textPDF), false, true);

        if (preg_match_all("#^ *{$this->t('Room')} (?<room>\d+):\s*{$this->opt($this->t('Confirmation'))}\s*\#?\: (?<confirmation>\d+)\s+(?<type>.+?)(?:\s+(?<desc>{$this->t('Includes:')}.+))?\s+{$this->t('Reserved for:')}#mu", $textPDF, $m,
                PREG_SET_ORDER)
            || preg_match_all("#^ *{$this->t('Room')} (?<room>\d+):(?:\s*{$this->opt($this->t('Confirmation'))}\s*\#?\: (?<confirmation>\d+))?\s+(?<type>.+?)(?:\s+(?<desc>{$this->t('Includes:')}.+))?\s+{$this->t('Reserved for:')}#mu", $textPDF, $m,
                PREG_SET_ORDER)) {
            $h->booked()->rooms(end($m)['room']);

            foreach ($m as $mm) {
                $r = $h->addRoom();

                if (isset($mm['confirmation']) && !empty($mm['confirmation'])) {
                    $r->setConfirmation($mm['confirmation']);
                }
                $r->setType($mm['type']);

                if (isset($mm['desc']) && !empty(trim($mm['desc']))) {
                    $r->setDescription($mm['desc']);
                }
            }
        }
        $h->general()->cancellation(preg_replace("#\s+#", ' ',
            $this->re("#{$this->opt($this->t('Cancel/Change Rules:'))}\s+(.+?)\n\n#su", $textPDF)), true);

        if (!empty($node = $h->getCancellation())) {
            $this->detectDeadLine($h, $node);
        }

        $priceText = strstr($textPDF, "\n{$this->t('Price')}\n");

        if (!empty($priceText)) {
            $h->price()
                ->total(PriceHelper::cost($this->re("#{$this->t('Total:')} +([\d\.\,]+) +[A-Z]{3}#u", $priceText)))
                ->currency($this->re("#{$this->t('Total:')} +[\d\.\,]+ +([A-Z]{3})#", $priceText))
                ->tax(PriceHelper::cost($this->re("#{$this->t('Taxes and Fees:')} +([\d\.\,]+) +[A-Z]{3}#u", $priceText)))
                ->fee($this->re("#({$this->t('Trip Net Price')}): +[\d\.\,]+ +[A-Z]{3}#u", $priceText),
                    PriceHelper::cost($this->re("#{$this->t('Trip Net Price')}: +([\d\.\,]+) +[A-Z]{3}#u", $priceText)));
        }
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText): void
    {
        if (preg_match("#Cancellations or changes made after (?<time>\d+:\d+(?:\s*[ap]m)?|\d+(?:\s*[ap]m)?) \(.+?\) on (?<date>\d+ \w+ \d{4}) or no-shows are subject to a property fee equal to 100% of the total amount paid for the reservation#i",
            $cancellationText, $m)
        || preg_match("#cobra estas taxas por cancelamento ou alteração. Cancelamentos ou alterações feitos após (?<time>\d+[h:]\d+) \(.+\), em (?<date>.+? \d{4}), ou no-show#iu", $cancellationText, $m)) {
            $h->booked()->deadline($this->normalizeDate($m['date'] . ', ' . $m['time']));
        }

        if (preg_match("#las\s*(?<time>[\d\:]+).+del\s*(?<day>\d+)\s*de\s*(?<month>\w+)\s*de\s*(?<year>\d{4})\s*#iu", $cancellationText, $m)) {
            $h->booked()->deadline($this->normalizeDate($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['time']));
        }

        $h->booked()
            ->parseNonRefundable('#The room/unit type and rate selected are non-refundable\.#i') // en
            ->parseNonRefundable('#O tipo de quarto/unidade e tarifa selecionados não são reembolsáveis\.#i') // pt
        ;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        //$this->logger->error("IN-".$date);
        $in = [
            //18 jul, 2019
            '#^(\d+)\s+(\w+),?\s+(\d{4})$#u',
            //18 jul, 2019, 16h00
            '#^(\d+)\s+(\w+),?\s+(\d{4}),\s+(\d+)[h:](\d+)$#u',
            //8 de octubre de 2021
            "#^(\d+)\s*de\s*(\w+)\s*de\s*(\d{4})$#u",
        ];
        $out = [
            '$1 $2 $3',
            '$1 $2 $3, $4:$5',
            '$1 $2 $3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '#'));
        }, $field)) . ')';
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
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function colsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }
}
