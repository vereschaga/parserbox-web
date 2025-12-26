<?php

namespace AwardWallet\Engine\tiket\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelVoucherPdf extends \TAccountChecker
{
    public $mailFiles = "tiket/it-717272612.eml, tiket/it-58228690-id.eml";
    public static $dictionary = [
        "id" => [
            //            "Itinerary ID" => "",
            //            "Check-In" => "",
            //            "Check-Out" => "",
            //            "Kamar" => "",
            //            "Tamu" => "",
            "hotelEndPhrases" => ["Akses Instan", "Semua di Satu Aplikasi", "Selalu Ada Untukmu"],
            //            "Nama Tamu" => "",
            //            "Tipe Kamar" => "",
            // "cancellationPolicy" => "",
            //            "Kebijakan Pembatalan & Refund" => "",
        ],
        "en" => [
            "Itinerary ID" => "Order Detail ID",
            // "Check-In" => "",
            // "Check-Out" => "",
            "Kamar"                         => "Room",
            "Tamu"                          => "Guest",
            "hotelEndPhrases"               => ["Instant Access", "All in One App", "Ready for You"],
            "Nama Tamu"                     => "Guest Name",
            "Tipe Kamar"                    => "Room Type",
            "cancellationPolicy"            => "Cancellation Policy",
            "Kebijakan Pembatalan & Refund" => "Refund & Reschedule Policy",
        ],
    ];

    private $detectFrom = "@tiket.com";

    private $detectSubject = [
        "id" => "Kamu akan menginap di",
        "en" => "You will stay at",
    ];
    private $detectCompany = "tiket.com";
    private $detectBodyHtml = [
        "id" => [
            "Temukan voucher hotel di lampiran",
        ],
        "en" => [
            "Find your reservation e-voucher in the attachment",
        ],
    ];

    private $detectBodyPdf = [
        "id" => [
            "Hotel Voucher",
        ],
        "en" => [
            "Reservation E-voucher",
        ],
    ];

    private $pdfPattern = ".*\.pdf";

    private $lang = "";

    private $patterns = [
        'time'          => '\d{1,2}[:：]\d{2}(?:\s*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52  |  (+351) 21 342 09 07  |  713.680.2992
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        // Travel Agency
        if (preg_match("/- (Order ID) (\d+)(?: |$)/i", $parser->getSubject(), $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        } else {
            $email->obtainTravelAgency();
        }

        $type = '';
        $error = false;
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (empty($text = \PDF::convertToText($parser->getAttachmentBody($pdf)))) {
                continue;
            }

            foreach ($this->detectBodyPdf as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($text, $dBody) !== false) {
                        $this->lang = $lang;

                        if (!$this->parsePdf($email, $text)) {
                            $this->logger->info("parsePdf is failed");
                            $error = true;
                        }
                        $type = 'Pdf';

                        continue 3;
                    }
                }
            }
        }

        if ($error === true || count($email->getItineraries()) === 0) {
            $email->clearItineraries();
            $body = html_entity_decode($this->http->Response["body"]);

            foreach ($this->detectBodyHtml as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($body, $dBody) !== false
                        || $this->http->XPath->query("//*[{$this->contains($dBody)}]")->length > 0
                    ) {
                        $this->lang = $lang;

                        break;
                    }
                }
            }
            $this->parseHtml($email);
            $type = 'Html';
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (stripos($body, $this->detectCompany) === false
            && $this->http->XPath->query("//a[{$this->contains($this->detectCompany, '@href')}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBodyHtml as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false
                    || $this->http->XPath->query("//*[{$this->contains($dBody)}]")->length > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parsePdf(Email $email, string $text): bool
    {
        if (preg_match($pattern = "#(^\s*|\n[ ]*|[ ]{2,})({$this->preg_implode($this->t("Itinerary ID"))})[ ]*[:]+[ ]*([A-Z\d]{5,25})(\n)#", $text, $m)) {
            $text = preg_replace($pattern, '$1$4', $text);
            $email->ota()->confirmation($m[3], $m[2]);
        }

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->traveller($this->re("#{$this->preg_implode($this->t("Nama Tamu"))}[ ]*[:]+[ ]*(?:\([ ]*{$this->preg_implode($this->t("Kamar"))}[ ]*\d{1,3}[ ]*\)[ ]*)?({$this->patterns['travellerName']})$#imu", $text), true)
        ;

        $hotel = $this->re("#(.+{$this->preg_implode($this->t("Check-In"))}.*(?:\n.+){1,11})\n+[ ]*{$this->preg_implode($this->t("hotelEndPhrases"))}#", $text);

        if (empty($hotel)) {
            $this->logger->debug('parsePdf: hotelName error!');

            return false;
        }
        $table = $this->SplitCols($hotel, $this->rowColsPos($this->inOneRow($hotel)));

        if (count($table) !== 3) {
            $this->logger->debug('parsePdf: table is wrong!');

            return false;
        }

        // Hotel
        $hotelTexts = preg_split("/[ ]*\n[ ]*\n[ ]*/", $table[0]);
        $addressParts = [];

        foreach ($hotelTexts as $i => $hText) {
            if (preg_match("/^{$this->patterns['phone']}$/", $hText)
                && strlen(preg_replace('/\D+/', '', $hText)) > 5
            ) {
                $h->hotel()->phone($hText);

                break;
            }

            if ($i === 0) {
                $h->hotel()->name(preg_replace('/\s+/', ' ', $hText));

                continue;
            }

            $addressParts[] = $hText;
        }

        if (count($addressParts) > 0 && count($addressParts) < 3) {
            $h->hotel()->address(preg_replace('/\s+/', ' ', implode(' ', $addressParts)));
        } else {
            $this->logger->debug('parsePdf: address error!');

            return false;
        }

        // Booked
        if (preg_match("#{$this->preg_implode($this->t("Check-In"))}\s+([\s\S]{4,}?\n+.*{$this->patterns['time']}.*)#", $table[1], $m)) {
            $m[1] = preg_replace("/({$this->patterns['time']})[-\s]+{$this->patterns['time']}/", '$1', $m[1]);
            $h->booked()->checkIn($this->normalizeDate($m[1]));
        } else {
            $this->logger->debug('parsePdf: checkIn error!');

            return false;
        }

        if (preg_match("#{$this->preg_implode($this->t("Check-Out"))}\s+([\s\S]{4,}?\n+.*{$this->patterns['time']}.*)#", $table[2], $m)) {
            $m[1] = preg_replace("/{$this->patterns['time']}[-\s]+({$this->patterns['time']})/", '$1', $m[1]);
            $h->booked()->checkOut($this->normalizeDate($m[1]));
        } else {
            $this->logger->debug('parsePdf: checkOut error!');

            return false;
        }

        if (preg_match("#[ ]{3,}(\d{1,2})[ ]?" . $this->preg_implode($this->t("Kamar")) . "[^\w\n]*(\d{1,2})[ ]?" . $this->preg_implode($this->t("Tamu")) . "#", $hotel, $m)) {
            $h->booked()
                ->rooms($m[1])
                ->guests($m[2])
            ;
        }

        if (preg_match("#\n[ ]*{$this->preg_implode($this->t("Tipe Kamar"))}[ ]*[:]+[ ]*((?:[^:\n]+\n){1,3})(?:\n|.+:)#", $text, $m) && !empty($h->getRoomsCount())) {
            $m[1] = preg_replace('/\s+/', ' ', trim($m[1]));

            for ($i = 0; $i < $h->getRoomsCount(); $i++) {
                $h->addRoom()->setType($m[1]);
            }
        }

        if (preg_match("#\n[ ]*{$this->preg_implode($this->t("cancellationPolicy"))}[ ]*[:]+[ ]*((?:[^:\n]+\n){1,6})(?:\n|.+:)#", $text, $m) // it-717272612.eml
            || preg_match("#\n[ ]*{$this->preg_implode($this->t("Kebijakan Pembatalan & Refund"))}\n{1,2}((?:[ ]{5,20}.+\n)+)#", $text, $m) // it-58228690-id.eml
        ) {
            $cp = preg_replace('/\s+/', ' ', trim($m[1]));
            $h->general()->cancellation($cp);
            $this->detectDeadLine($h, $cp);
        }

        return true;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, ?string $cancellationText): void
    {
        if (empty($cancellationText)) {
            return;
        }

        if (preg_match("/^Free cancellation until\s+(?<date>\d{1,2}[,.\s]+[[:alpha:]]+[,.\s]+\d{4})\s*\./iu", $cancellationText, $m) // en
            || preg_match("/^Pembatalan gratis hingga\s+(?<date>\d{1,2}[,.\s]+[[:alpha:]]+[,.\s]+\d{4})\s*\./iu", $cancellationText, $m) // id
        ) {
            $h->booked()->deadline($this->normalizeDate($m['date']));

            return;
        }
//        if (
//        	preg_match("#Cancelaciones con un mínimo de (?<day>\d+) días antes del check-in, NO se cobrarán cargos.#ui", $cancellationText, $m)
//        ) {
//            $h->booked()->deadlineRelative($m['day'].' day');
//            return;
//        }
//        if (
//        	preg_match("#La tarifa seleccionada no permite realizar cambios o cancelaciones\.#ui", $cancellationText)
//        ) {
//            $h->booked()->nonRefundable();
//            return;
//        }
    }

    private function parseHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Nama Tamu")) . "]/following::text()[normalize-space() and normalize-space() != ':'][1]"), true)
        ;

        // Hotel
        $xpath = "(//img[contains(@src, 'tix-hotel') and @width='10px' and @height='10px' and following::text()[normalize-space()][position()<5][" . $this->eq($this->t("Check-In")) . "]]/ancestor::*[1])[1]";
        $h->hotel()
            ->name($this->http->FindSingleNode($xpath))
            ->address($this->http->FindSingleNode($xpath . "/following::td[normalize-space() and not(.//td)][1]"))
            ->phone($this->http->FindSingleNode($xpath . "/following::td[normalize-space() and not(.//td)][2]", null, true, "#^\s*[\d \-\+\(\)\.]+\s*$#"), true, true)
        ;

        // Booked
        $date = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Check-In")) . "]/following::td[normalize-space()][1]");
        $time = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Check-In")) . "]/following::td[normalize-space()][2]", null, true, "#^\s*\d{1,2}:\d{2}.*#");
        $h->booked()
            ->checkIn($this->normalizeDate($date . ' ' . $time));

        $date = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Check-Out")) . "]/following::td[normalize-space()][1]");
        $time = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Check-Out")) . "]/following::td[normalize-space()][2]", null, true, "#^\s*\d{1,2}:\d{2}.*#");
        $h->booked()
            ->checkOut($this->normalizeDate($date . ' ' . $time));

        $info = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Check-In")) . "]/following::td[normalize-space()][position() < 5][" . $this->contains($this->t("Kamar")) . "][1]");

        if (preg_match("#^\s*(\d{1,2})\s*" . $this->preg_implode($this->t("Kamar")) . "\b\s*\W*\s*(\d{1,2})[ ]?" . $this->preg_implode($this->t("Tamu")) . "#", $info, $m)) {
            $h->booked()
                ->rooms($m[1])
                ->guests($m[2])
            ;
        }

        $type = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Tipe Kamar")) . "]/following::text()[normalize-space() and normalize-space() != ':'][1]");

        if (preg_match("#(\d{1,2})x[ ]*(.+)#", $type, $m)) {
            for ($i = 1; $i <= $m[1]; $i++) {
                $h->addRoom()->setType($m[2]);
            }
        }

        return $email;
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

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*[^\s\d]+\s*,\s*(\d{1,2})\s*([^\s\d]+)\s+(\d{4})\s+(\d+:\d+)\s*$#u", //Rab, 04 Mar 2020 14:00
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        $pos = [];
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }
}
