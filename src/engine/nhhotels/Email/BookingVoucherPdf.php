<?php

namespace AwardWallet\Engine\nhhotels\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BookingVoucherPdf extends \TAccountChecker
{
    public $mailFiles = ""; // +1 bcdtravel(pdf)[es]

    private $langDetectors = [
        'es' => ['DETALLE DE LA RESERVA', 'Detalle De La Reserva', 'Detalle de la reserva'],
    ];
    private $lang = '';
    private static $dict = [
        'es' => [
            'roomTypes' => ['Habitación Standard Double Queen', 'Habitación Superior Double'], // hard-coded
        ],
    ];

    // Standard Methods

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($textPdf === null) {
                continue;
            }

            if (strpos($textPdf, 'NH Hotel Group') === false && stripos($textPdf, '@nh-hotels.com') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $detectLang = false;

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($textPdf === null) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $detectLang = true;
                $this->parseEmail($email, $textPdf);
            }
        }

        $email->setType('BookingVoucherPdf' . ucfirst($this->lang));

        if (!$detectLang) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email, $text)
    {
        $patterns = [
            'phone'         => '[+)(\d][-.\s\d)(]{5,}[\d)(]', // +377 (93) 15 48 52    |    713.680.2992
            'travellerName' => '[[:alpha:]][-,.\'[:alpha:] ]*[[:alpha:]]', // Mr. QUESADA RAMIREZ, ALEJANDRO
        ];

        $h = $email->add()->hotel();

        $text = preg_replace("/.*?^[ ]*({$this->opt($this->t('COMPROBANTE DE RESERVA'))}.+)/ms", '$1', $text);
        $text = preg_replace("/^(.+?){$this->opt($this->t('Este mensaje es de NH HOTELES y es privado y confidencial'))}.*/is", '$1', $text);

        // confirmation number
        if (preg_match("/({$this->opt($this->t('Nº de reserva'))})[: ]+([A-Z\d]{7,})(?:[ ]{2}|$)/m", $text, $m)) {// 0061284890
            $h->general()->confirmation($m[2], $m[1]);
        }

        // hotelName
        // address
        // phone
        // fax
        $patterns['hotelInfo'] = "/"
            . "{$this->opt($this->t('Nº de reserva'))}[: ]+.+" // Nº de reserva 0063284890
            . "\s+^[ ]*(?<name>.{3,})$" // NH Royal Pavillon
            . "\s+^[ ]*(?<address>.{2,}$" // CALLE 94, 11-45
            . "(?:\s+^[ ]*.{2,}$)?" // 111831 - BOGOTA (Colombia)
            . "(?:\s+^[ ]*.{2,}$)?" // address row 3
            . "(?:\s+^[ ]*.{2,}$)?" // address row 4
            . "(?:\s+^[ ]*.{2,}$)?)" // address row 5
            . "\s+^[ ]*{$this->opt($this->t('Tel:'))}[ ]*(?<phone>{$patterns['phone']})?$" // Tel: 57 1 6502555
            . "\s+^[ ]*{$this->opt($this->t('Fax:'))}[ ]*(?<fax>{$patterns['phone']})?$" // Fax: 57 1 6502556
            . "/m";

        if (preg_match($patterns['hotelInfo'], $text, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address(preg_replace('/\s+/', ' ', $m['address']))
                ->phone($m['phone'], false, true)
                ->fax($m['fax'], false, true)
            ;
        }

        // travellers
        $holderText = preg_match("/^[ ]*{$this->opt($this->t('TITULAR DE LA RESERVA'))}$\s+(.+?)\s+^[ ]*{$this->opt($this->t('DATOS DE FACTURACIÓN'))}$/ms", $text, $m) ? $m[1] : '';

        if (preg_match_all("/^[ ]*{$this->opt($this->t('Nombre:'))}[ ]*({$patterns['travellerName']})$/m", $holderText, $nameMatches)) {
            $h->general()->travellers($nameMatches[1]);
        }

        $detailsText = preg_match("/^[ ]*{$this->opt($this->t('DETALLE DE LA RESERVA'))}$\s+(.+?)\s+^[ ]*{$this->opt($this->t('TOTAL ESTANCIA'))}$/ms", $text, $m) ? $m[1] : '';

        // checkInDate
        if (preg_match("/^[ ]*{$this->opt($this->t('Llegada:'))}[ ]*(.{6,})$/im", $detailsText, $matches)) {
            $h->booked()->checkIn2($matches[1]);

            if (
                !empty($h->getCheckInDate())
                && preg_match('/La habitación (?:estará|está) disponible desde las\s*(\d{1,2})h\.?/iu', $text, $m) // es
            ) {
                $h->booked()->checkIn(strtotime($m[1] . ':00', $h->getCheckInDate()));
            }
        }

        // checkOutDate
        if (preg_match("/^[ ]*{$this->opt($this->t('Salida:'))}[ ]*(.{6,})$/im", $detailsText, $matches)) {
            $h->booked()->checkOut2($matches[1]);

            if (
                !empty($h->getCheckOutDate())
                && preg_match('/hasta las\s*(\d{1,2})h\.?\s*del día de la salida/iu', $text, $m) // es
            ) {
                $h->booked()->checkOut(strtotime($m[1] . ':00', $h->getCheckOutDate()));
            }
        }

        // guestCount
        if (preg_match("/\b(\d{1,3})[ ]*{$this->opt($this->t('Adulto'))}\b/i", $detailsText, $m)) {
            $h->booked()->guests($m[1]);
        }

        $r = $h->addRoom();

        // r.type
        if (preg_match("/^[ ]*({$this->opt($this->t('roomTypes'))})$/im", $detailsText, $m)) {
            $r->setType($m[1]);
        }

        $totalStayText = preg_match("/^[ ]*{$this->opt($this->t('TOTAL ESTANCIA'))}$\s+(.+?)\s+^[ ]*{$this->opt($this->t('DESCRIPCIÓN Y CONDICIONES DE LA TARIFA'))}$/ms", $text, $m) ? $m[1] : '';

        // p.total
        // p.currencyCode
        if (preg_match("/^[ ]*{$this->opt($this->t('Importe total factura (impuestos incluidos):'))}[ ]*(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[A-Z]{3})$/im", $totalStayText, $matches)) {
            // 7.830,00 MXN
            $h->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency']);
            // p.cost
            if (preg_match("/^[ ]*{$this->opt($this->t('Base imponible (sin tasas):'))}[ ]*(?<amount>\d[,.\'\d]*)[ ]*" . preg_quote($matches['currency'], '/') . "$/im", $totalStayText, $m)) {
                $h->price()->cost($this->normalizeAmount($m[1]));
            }
            // p.tax
            if (preg_match("/^[ ]*{$this->opt($this->t('IVA / Tasas'))}.*[: ]+(?<amount>\d[,.\'\d]*)[ ]*" . preg_quote($matches['currency'], '/') . "$/im", $totalStayText, $m)) {
                $h->price()->tax($this->normalizeAmount($m[1]));
            }
        }

        $descriptionText = preg_match("/^[ ]*{$this->opt($this->t('DESCRIPCIÓN Y CONDICIONES DE LA TARIFA'))}$\s+(.+)/ms", $text, $m) ? $m[1] : '';

        // r.rateType
        if (preg_match("/^[ ]*{$this->opt($this->t('Tarifa:'))}[ ]*([^:]+?)\s+^[ ]*{$this->opt($this->t('Garantía:'))}.+/im", $descriptionText, $m)) {
            $r->setRateType($m[1]);
        }

        // deadline
        if (
            preg_match("/La reserva se puede modificar o cancelar sin gastos hasta\s*(\d{1,3})\s*horas antes de la fecha de llegada/i", $text, $m) // es
        ) {
            $h->booked()->deadlineRelative($m[1] . ' hours', '00:00');
        }
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang($text): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
