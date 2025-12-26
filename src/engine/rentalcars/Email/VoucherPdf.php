<?php

namespace AwardWallet\Engine\rentalcars\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VoucherPdf extends \TAccountChecker
{
    public $mailFiles = "rentalcars/it-151472190.eml, rentalcars/it-151969097-pt.eml, rentalcars/it-179028081.eml, rentalcars/it-88266620.eml";

    public $reSubject = [
        "nl"=> "Mijn e-voucher",
        "de"=> "Mein eVoucher",
        "pt"=> "Voucher Carro",
    ];

    // public $pdfPattern = "(?:Uw voucher|Ihr Buchungsbeleg|Your Voucher|Ваш Ваучер|Rental Voucher) \d+.pdf";

    public static $dictionary = [
        "nl" => [
            // "Boekingsnummer" => "",
            // "Boekingsdocumenten" => "",
            //			"Belangrijke informatie" => "",
            //			"Reisgegevens" => "",
            //			"Auto" => "",
            "Bevestigingsnummer" => "Bevestigingsnummer",
            "Hoofdbestuurder"    => "Hoofdbestuurder",
            //			"Car Hire Company" => "", // to translate
        ],
        "de" => [
            // "Boekingsnummer" => "",
            "Boekingsdocumenten"     => "Buchungsbeleg",
            "Belangrijke informatie" => "Wichtige Informationen",
            "Reisgegevens"           => "Wo & Wann",
            "Auto"                   => "Fahrzeug",
            "Bevestigingsnummer"     => "Reservierungsnummer",
            "Hoofdbestuurder"        => "Hauptfahrer",
            "Car Hire Company"       => "Autovermieter",
        ],
        "pt" => [ // it-151969097-pt.eml
            "Boekingsnummer"         => "Referência da Reserva",
            "Boekingsdocumenten"     => "Documentos de reserva",
            "Belangrijke informatie" => "Informações importantes",
            "Reisgegevens"           => "Detalhes da Viagem",
            "Auto"                   => "Carro",
            "Bevestigingsnummer"     => "Nº de confirmação",
            "Hoofdbestuurder"        => "Condutor principal",
            "Car Hire Company"       => "Locadora",
        ],
        "en" => [ // it-151472190.eml
            "Boekingsnummer"         => "Booking Reference",
            "Boekingsdocumenten"     => "Rental Voucher",
            "Belangrijke informatie" => "Important Information",
            "Reisgegevens"           => "Trip Details",
            "Auto"                   => "Car",
            "Bevestigingsnummer"     => "Confirmation number",
            "Hoofdbestuurder"        => "Main Driver",
            'Loyalty number : '      => ['Loyalty number : ', 'Account number'],
            //			"Car Hire Company" => "",
        ],
        "ru" => [ // it-88266620.eml
            "Boekingsnummer"         => "Номер Бронирования",
            "Boekingsdocumenten"     => "Ваучер аренды",
            "Belangrijke informatie" => "Важная информация",
            "Reisgegevens"           => "Детали поездки",
            "Auto"                   => "Автомобиль",
            "Bevestigingsnummer"     => "Номер подтверждения",
            "Hoofdbestuurder"        => "Детали Главного Водителя",
            "Car Hire Company"       => "Прокатная компания",
        ],
        "fr" => [ // it-88266620.eml
            "Boekingsnummer"         => "Référence de réservation",
            "Boekingsdocumenten"     => "Votre réservation",
            "Belangrijke informatie" => "Informations importantes",
            "Reisgegevens"           => "Où et quand",
            "Auto"                   => "Voiture",
            "Bevestigingsnummer"     => "Numéro de confirmation",
            "Hoofdbestuurder"        => "Conducteur principal",
            "Car Hire Company"       => ["La société de location de voitures", "Compañía de alquiler"],
        ],
    ];
    public $lang = "nl";

    private $providerCode = '';

    public function parsePdf(Email $email, $text): void
    {
        $pos = [0, strlen($this->re("#\n(.*?)" . $this->t("Belangrijke informatie") . "#", $text))];

        if ($this->lang == 'ru' || $this->lang == 'fr') {
            $pos = [0, 74];
        }

        $mainTable = $this->splitCols($this->re("#(?:^|\n)([^\n\S]*" . $this->t("Reisgegevens") . ".+)#ms", $text), $pos);

        if (count($mainTable) < 2) {
            $this->logger->debug("incorrect split mainTable!");

            return;
        }
        $detailsTable = $this->splitCols(preg_replace("#^\s*\n#", "", substr($mainTable[0], $s = strpos($mainTable[0], $this->t('Reisgegevens')) + strlen($this->t("Reisgegevens")), strpos($mainTable[0], $this->t('Auto')) - $s)));

        if (count($detailsTable) < 2) {
            $this->logger->debug("incorrect split detailsTable!");

            return;
        }
        $pickup = explode("\n", $detailsTable[0]);

        $dropoff = explode("\n", $detailsTable[1]);

        if (count($pickup) < 4 || count($dropoff) < 4) {
            $this->logger->debug("incorrect rows count detailsTable!");

            return;
        }

        $r = $email->add()->rental();

        $headerText = $this->re("/^\n*(.+?)\n+[ ]*{$this->opt($this->t("Reisgegevens"))}/s", $text);
        $headerText = preg_replace("/^[ ]*{$this->opt($this->t("Boekingsdocumenten"))}(?:[ ]{2}|$)/m", '', $headerText);

        if (preg_match("/(?:^[ ]*|[ ]{2})(?<description>{$this->opt($this->t("Boekingsnummer"))})\s+(?<number>[A-Z\d][-A-Z\d ]{3,}[A-Z\d])$/m", $headerText, $m)) {
            $r->ota()->confirmation(str_replace(' ', '', $m['number']), $m['description']);
        }

        if (preg_match("/^[ ]*({$this->opt($this->t("Bevestigingsnummer"))})[ ]+([-A-Z\d]{5,})\D*$/m", $mainTable[0], $m)) {
            $r->general()->confirmation($m[2], $m[1]);
        }

        $traveller = $this->re("/^[ ]*{$this->opt($this->t("Hoofdbestuurder"))}[ ]+(?:(?:Господин|Госпожа|Пан|Sr|Mme)[. ]+)?([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/imu", $mainTable[0]);
        $r->general()->traveller(preg_replace("/^(M\.)\s+/u", "", $traveller));

        $r->pickup()
            ->date(strtotime($this->normalizeDate($pickup[1])))
            ->location($pickup[2]);

        $r->dropoff()
            ->date(strtotime($this->normalizeDate($dropoff[1])))
            ->location($dropoff[2]);

        $r->setCompany($this->re("/{$this->opt($this->t("Car Hire Company"))}\n+.+?:[ ]*(.{2,})\n/", $mainTable[0]));

        $r->car()
            ->type($this->re("/{$this->opt($this->t("Auto"))}\s+(.{2,}?)[ ]*\(.{2,}?\)/", $mainTable[0]))
            ->model($this->re("/{$this->opt($this->t("Auto"))}\s+.{2,}?[ ]*\([ ]*(.{2,}?)[ ]*\)/", $mainTable[0]));

        $account = $this->re("/{$this->opt($this->t('Loyalty number : '))}\s*[$]*([A-Z\d]+)/", $text);

        if (!empty($account)) {
            $r->program()
                ->account($account, false);
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@reservations.rentalcars.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $detectProvider = $this->detectEmailFromProvider($parser->getHeader('from')) === true;

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($detectProvider === false && $this->assignProvider($textPdf) === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->assignProvider($textPdf);
                $this->parsePdf($email, $textPdf);
            }
        }

        $email->setProviderCode($this->providerCode);

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('VoucherPdf' . ucfirst($this->lang));

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

    public static function getEmailProviders()
    {
        return ['booking', 'rentalcars'];
    }

    private function assignProvider($text): bool
    {
        if (strpos($text, 'Booking.com') !== false) {
            // it-151472190.eml
            $this->providerCode = 'booking';

            return true;
        }

        if (strpos($text, 'Rentalcars.com') !== false
        || strpos($text, ' Priceline.com') !== false) {
            // it-88266620.eml
            $this->providerCode = 'rentalcars';

            return true;
        }

        return false;
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Bevestigingsnummer']) || empty($phrases['Hoofdbestuurder'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['Bevestigingsnummer']) !== false
                && $this->strposArray($text, $phrases['Hoofdbestuurder']) !== false
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(?:om|-|at)\s+(\d+:\d+)$#", //ma 17 juli om 17:00, Sa 6 Okt - 7:30, Sat 6 Oct at 7:30
            "#^\w+\s*(\d+\s*\w+)\s*\D\s*([\d\:]+)$#u", //Пт 21 Май в 7:00
        ];
        $out = [
            "$1 $2 $year, $3",
            "$1 $year, $2",
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
            foreach ($pos as $k=>$p) {
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
