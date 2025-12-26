<?php

namespace AwardWallet\Engine\traveluro\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class VoucherPdf extends \TAccountChecker
{
    public $mailFiles = "traveluro/it-640219407-2.eml, traveluro/it-742626686-2-fr.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang = '';
    public static $dictionary = [
        'en' => [
            'Prepaid - Hotel Reservation' => 'Prepaid - Hotel Reservation',
            // 'Check In' => '',
            // 'Check Out' => '',
            'Number of Guests' => 'Number of Guests',
            // 'Room Type' => '',
            // 'Room' => '',
            // 'Adult' => '',
            // 'Child' => '',
            // 'Order ID' => '',
            // 'Confirmation Status' => '',
            // 'Confirmation number' => '',
            'Guest and Billing Details' => 'Guest and Billing Details',
            // 'Cancelation Policy' => '',
        ],
        'fr' => [
            'Prepaid - Hotel Reservation' => "Réservation d'hôtel prépayée",
            'Check In'                    => 'Arrivée',
            'Check Out'                   => 'Départ',
            'Number of Guests'            => 'Nombre de clients',
            'Room Type'                   => 'Type de chambre',
            'Room'                        => 'Chambre',
            'Adult'                       => ['Adultes', 'Adulte'],
            'Child'                       => ['Enfants', 'Enfant'],
            'Order ID'                    => 'Numéro de commande',
            'Confirmation Status'         => 'État de Confirmation',
            'Confirmation number'         => 'Numéro de confirmation',
            'Guest and Billing Details'   => ['Détails de facturation et au sujet des clients', 'Détails de facturation et au'],
            'Cancelation Policy'          => "Politique d'annulation",
        ],
        'de' => [
            'Prepaid - Hotel Reservation' => 'Vorausbezahlte - Hotelreservierung',
            'Check In'                    => 'Einchecken',
            'Check Out'                   => 'Auschecken',
            'Number of Guests'            => 'Anzahl der Gäste',
            'Room Type'                   => 'Zimmertyp',
            'Room'                        => 'Zimmer',
            'Adult'                       => 'Erwachsene',
            'Child'                       => 'Kind',
            'Order ID'                    => 'Auftragsnummer',
            'Confirmation Status'         => 'Bestätigungsstatus',
            'Confirmation number'         => 'Bestätigungsnummer',
            'Guest and Billing Details'   => ['Gäste- und Rechnungsdetails', 'Gäste- und'],
            'Cancelation Policy'          => 'Widerrufsbelehrung',
        ],
        'es' => [
            'Prepaid - Hotel Reservation' => 'Reserva prepaga de hotel',
            'Check In'                    => 'Entrada',
            'Check Out'                   => 'Salida',
            'Number of Guests'            => 'Número de huéspedes',
            'Room Type'                   => 'Tipo de habitación',
            'Room'                        => 'Habitación',
            'Adult'                       => ['Adult'],
            'Child'                       => ['Child', 'Niño'],
            'Order ID'                    => 'Id. del pedido',
            'Confirmation Status'         => 'Estado de confirmación',
            'Confirmation number'         => 'Número de confirmación',
            'Guest and Billing Details'   => ['Detalles del huésped y facturación', 'Detalles del huésped y'],
            'Cancelation Policy'          => 'Política de cancelación',
        ],
    ];

    private $patterns = [
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]traveluro\.com\b/i", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                return true;
            }
        }

        return false;
    }

    public function detectPdf($text): bool
    {
        // detect provider
        $providerDetected = false;

        if ($this->http->XPath->query("//a[{$this->contains(['.traveluro.com/'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Traveluro'])}]")->length === 0
        ) {
            $providerDetected = true;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if ($providerDetected === false
                && !empty($dict['Prepaid - Hotel Reservation'])
                && $this->containsText($text, $dict['Prepaid - Hotel Reservation']) === false
            ) {
                continue;
            }

            if (!empty($dict['Number of Guests'])
                && $this->containsText($text, $dict['Number of Guests']) === true
                && !empty($dict['Guest and Billing Details'])
                && $this->containsText($text, $dict['Guest and Billing Details']) === true
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                $this->parseEmailPdf($email, $text);
            }
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

    private function parseEmailPdf(Email $email, ?string $textPdf = null): void
    {
        // Travel Adency
        $tableText = $this->re("/\n( *{$this->opt($this->t('Order ID'))} {3,}{$this->opt($this->t('Confirmation Status'))}[\s\S]+?)\n *{$this->opt($this->t('Guest and Billing Details'))}/", $textPdf);
        $tableOrder = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));

        $otaConfirmation = $otaConfirmationTitle = null;

        if (preg_match("/^\s*({$this->opt($this->t('Order ID'))})\s+([A-Z\d]{5,25})\s*(?:\(\))?\s*$/", $tableOrder[0] ?? '', $m)) {
            $otaConfirmationTitle = $m[1];
            $otaConfirmation = $m[2];
        }

        $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);

        if (preg_match("/^\s*({$this->opt($this->t('Confirmation number'))})\s+([A-Z\d]{5,25})\s*$/", $tableOrder[2] ?? '', $m)) {
            $otaConfirmationTitle = $m[1];
            $otaConfirmation = $m[2];
            $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
        }

        // HOTEL
        $h = $email->add()->hotel();

        if ($otaConfirmation) {
            $h->general()->noConfirmation();
        }

        $h->general()
            ->traveller($this->re("/\n[ ]*{$this->opt($this->t('Guest and Billing Details'))}[ ]+((?:[[:alpha:]\-] ?)+)[ ]{3}/u", $textPdf), true)
            ->status($this->re("/^\s*{$this->opt($this->t('Confirmation Status'))}\s+([[:alpha:]]+)\s*$/u", $tableOrder[1] ?? ''))
            ->cancellation(preg_replace('/\s+/', ' ',
                $this->re("/\n *{$this->opt($this->t('Cancelation Policy'))}\n\s*((?:.+\n){1,7})\n\n/u", $textPdf)))
        ;

        // Hotel
        if (preg_match("/^\s*{$this->opt($this->t('Prepaid - Hotel Reservation'))}\n+[ ]{20,}(?<name>\S.+(?:\n[ ]{20,}\S.{1,30})?)\n+[ ]{21,}(?<address>\S.+(?:\n[ ]{21,}\S.{1,30})?)\n+[ ]{0,10}\S/", $textPdf, $m)) {
            $h->hotel()->name(preg_replace('/\s+/', ' ', trim($m['name'])))->address(preg_replace('/\s+/', ' ', trim($m['address'])));
        }

        // Booked
        $tableText = $this->re("/\n(.+ {3,}{$this->opt($this->t('Check In'))} {3,}{$this->opt($this->t('Check Out'))}[\s\S]+?)\n *{$this->opt($this->t('Number of Guests'))}/", $textPdf);
        $tablePos = $this->rowColumnPositions($this->inOneRow($tableText));

        if (count($tablePos) < 3
            && preg_match("/^(.+[ ]{2}){$this->opt($this->t('Check Out'))}/m", $tableText, $matches)
        ) {
            $tablePos[] = mb_strlen($matches[1]) - 2;
        }

        $tableDate = $this->createTable($tableText, $tablePos);

        $h->booked()
            ->checkIn(strtotime($this->normalizeDate($this->re("/^\s*{$this->opt($this->t('Check In'))}\s+(.+\b\d{4}\b.*?)\s*$/s", $tableDate[1] ?? ''))))
            ->checkOut(strtotime($this->normalizeDate($this->re("/^\s*{$this->opt($this->t('Check Out'))}\s+(.+\b\d{4}\b.*?)\s*$/s", $tableDate[2] ?? ''))))
            // ->rooms($this->re("/^\s*{$this->opt($this->t('Number of Guests'))} +.*\s*$/s", $textPdf))
            ->rooms($this->re("/\n *{$this->opt($this->t('Number of Guests'))} +.*\b(\d+)\s+{$this->opt($this->t('Room'))}/", $textPdf))
            ->guests($this->re("/\n *{$this->opt($this->t('Number of Guests'))} +.*\b(\d+)\s+{$this->opt($this->t('Adult'))}/", $textPdf))
            ->kids($this->re("/\n *{$this->opt($this->t('Number of Guests'))} +.*\b(\d+)\s+{$this->opt($this->t('Child'))}/", $textPdf), true, true)
        ;

        // Rooms
        $h->addRoom()
            ->setType(preg_replace('/\s+/', ' ',
                $this->re("/\n {0,10}{$this->opt($this->t('Room Type'))} +(\S.+(?:\n.*){0,5}?)\n {0,10}\S/", $textPdf)));

        $this->detectDeadLine($h);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^\s*Cancell?ations before (.+?)(?:\s*\(UTC\))? are fully refundable\./i", $cancellationText, $m)
            || preg_match("/^\s*Cette réservation vous donne droit à un remboursement complet en cas d’annulation à condition de remplir la demande d’annulation avant le\s+(.{4,20}\b\d{4})(?:\s*[.;!]|\s*$)/i", $cancellationText, $m) // fr
        ) {
            $h->booked()->deadline(strtotime($this->normalizeDate($m[1])));
        }
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function contains($field, $text = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    // additional methods

    private function createTable(?string $text, $pos = []): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
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

    private function rowColumnPositions(?string $row): array
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

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
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

    private function normalizeDate(?string $date): ?string
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            // 18 Apr 2024 Thursday, 16:00  |  01 Jul 2023 Saturday, from 15:00
            "/^\s*(\d{1,2})[,.\s]+([[:alpha:]]+)[,.\s]+(\d{4})\s+[-[:alpha:]]+\s*,\s*\D{0,15}\b({$this->patterns['time']}).*$/u",
        ];
        $out = [
            '$1 $2 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));
        if (preg_match("/^\s*(\d+\s+)([^\d\s]+)(\s+\d{4}.*)/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $date = $m[1] . $en . $m[3];
            }
        }

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return $date;
    }

    private function opt($field, $delimiter = '/'): string
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
