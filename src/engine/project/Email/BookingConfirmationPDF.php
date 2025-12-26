<?php

namespace AwardWallet\Engine\project\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "project/it-118308176.eml, project/it-157640637.eml, project/it-190463566.eml, project/it-190463628.eml, project/it-225139972.eml";
    private $subjects = [
        'Project ExpeditionsTours',
    ];

    private $lang = 'en';
    private $detectCompany = 'projectexpedition.com';

    private $pdfPattern = '.+\.pdf';
    private $detectBody = ['Booked through Project Expedition'];

    private static $dictionary = [
        "en" => [
            'addressEnd' => ['on Google Maps', 'Host name:'],
            'Ref:'       => ['Ref:', 'Reference:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@projectexpedition.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($textPdf, $this->detectCompany) === false) {
                continue;
            }

            foreach ($this->detectBody as $detectBody) {
                if (stripos($textPdf, $detectBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]projectexpedition\.com$/', $from) > 0;
    }

    public function ParseEvent(Email $email, $text): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $e = $email->add()->event();
        $e->type()->event();

        $headText = $this->re("/(?:^|\n{2})((?:.{2,}\n){1,2}\n*.*{$patterns['time']}.*\n+(?:\s*Meeting Time:.*)?\n*(?:\s*Language:.*)?\n*(?:.{2,}\n){1,2})\n*[ ]*Reservation\s*Details/", $text);

        if (preg_match("/^\s*(?<name>[\s\S]{2,}?)\n+\s*(?<start>[[:alpha:]]+\s*\d{1,2},\s*\d{2,4},\s*{$patterns['time']}).*\n+[ ]*(?<confDesc>{$this->opt($this->t('Ref:'))})\s*(?<conf>[-A-Z\d]{5,})\n+[ ]*{$this->opt($this->t('Status:'))}\s*(?<status>\w+)$/siu", $headText, $m)) {
            $e->general()
                ->confirmation($m['conf'], rtrim($m['confDesc'], ': '))
                ->status($m['status']);

            $e->setName(preg_replace('/\s+/', ' ', $m['name']));

            $e->booked()
                ->start($this->normalizeDate($m['start']));
        }

        $guests = $this->re("/{$this->opt($this->t('Traveler Names'))}\s*\((\d+)\s*{$this->opt($this->t('Total'))}\)/", $text);

        if (!empty($guests)) {
            $e->booked()
                ->guests($guests);
        }

        $reservationText = $this->re("/\n([ ]*Reservation Details(?:[ ]{2}.+)?\n[\s\S]+?)\n+[ ]*Booked through Project Expedition(?:[ ]{2}|\n)/", $text);
        $reservationTable = $this->splitCols($reservationText, ['0', '55']);

        // TODO: maybe inherit method findAddress() from parser `BookingConfirmation`
        $address = null;
        $general = preg_match("/(?:^\s*|\n){$this->opt($this->t('General:'))}\s*([\s\S]+?)(?:\n{2}|\s*$)/", $reservationTable[1], $m) ? $m[1] : null;
        $this->logger->debug('GENERAL: ' . $general);

        if (preg_match("/(?:Departure|Depart) from\s+(?<a>[\s\S]{15,90}?[^,])[.;! ]*$/m", $general, $m) && preg_match("/[[:alpha:]]{2}/u", $m[1]) // it-157640637.eml
        ) {
            $address = $m['a'];
        } else {
            $address = $this->re("/Meeting point:\s*([\s\S]{3,90}?)\s*{$this->opt($this->t('addressEnd'))}/", $reservationTable[1]) // it-118308176.eml
                ?? $this->re("/\bFrom\s+([\s\S]{3,90}?)\s*[:]+\s*Bus Transfer/", $reservationTable[1]) // it-190463628.eml
                ?? $this->re("/Shore Excursion -\s+([\s\S]{3,90}?)\s*,\s*City Tour/", $text) // it-190463566.eml
                ?? $this->re("/\n[ ]*Book more tours in\s+([^:]{3,90}?)\s*:/i", $reservationTable[0]) // other
            ;
        }

        if (!empty($address)) {
            $e->place()->address(preg_replace('/\s+/', ' ', $address));
        } elseif (stripos($reservationTable[1], 'Exact pick up time and guide details will be sent the night before 
by SMS') !== false) {
            $email->removeItinerary($e);
            $email->setIsJunk(true);
        } elseif (stripos($reservationTable[1], "You'll meet at the") !== false && !empty($general)) {
            $address = $this->re("/You\'ll meet at the\s+(.+)\.\s+The/s", $general);

            if (!empty($address)) {
                $e->setAddress($address);
            }
        }

        $travellersText = $this->re("/Traveler Names(.+)\s*Contact\s*Info/s", $reservationTable[0]);

        if (preg_match_all("/^[ ]*\d{1,3}\.[ ]*({$patterns['travellerName']})$/m", $travellersText, $m)) {
            $e->general()->travellers($m[1], true);
        }

        $endTimeText = str_replace('.', ':', $this->re("/Drop Off Location:.+at\s*({$patterns['time']})/s", $reservationTable[1]));

        if (!empty($endTimeText)) {
            $e->booked()
                ->end(strtotime($endTimeText, $e->getStartDate()));
        } elseif (empty($endTimeText) && strpos($reservationTable[1], 'Drop Off Location:') == false) {
            $e->booked()
                ->noEnd();
        }

        $priceText = $this->re("/\n^(Phone\:.+Payment Status:.+Due on Reservation Day:)/ms", $text);

        if (!empty($priceText)) {
            $priceTable = $this->splitCols($priceText);
            // C$ 120.29 Total Price:
            if (preg_match("/Payment Status:\n+[ ]*(?<currency>[A-Z\S]{2,3})[ ]+(?<total>\d[,.\'\d ]*)\n+[ ]*Total Price:/", $priceTable[1], $m)) {
                $currency = $this->normalizeCurrency($m['currency']);
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $e->price()->currency($currency)->total(PriceHelper::parse($m['total'], $currencyCode));
            } elseif (preg_match("/(?:^[ ]*|[ ]{2})Total Price:\s*(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/m", $text, $m)) {
                // $206.49
                $e->price()->currency($m['currency'])->total($m['amount']);
            }
        }

        $notesText = $this->re("/\n^(Traveler Names.+Notes from Tour Operator:.+Contact Info:)/ms", $text);

        if (!empty($notesText)) {
            $notesTable = $this->splitCols($notesText);

            if (stripos($notesTable[1], 'Emergency number') !== false) {
                $notes = $this->re("/Notes from Tour Operator:\s*(.+)\s*Emergency number/s", $notesTable[1]);
            } elseif (stripos($notesTable[1], 'COVID-19') !== false) {
                $notes = $this->re("/Notes from Tour Operator:\s*(.+)\s*COVID-19/s", $notesTable[1]);
            }

            if (!empty($notes)) {
                $e->setNotes(str_replace("\n", " ", $notes));
            } elseif (!empty($general)) {
                $e->setNotes(str_replace("\n", " ", $general));
            }
        }
    }

    public function ParseTransfer(Email $email, $text): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $t = $email->add()->transfer();

        $headText = $this->re("/^([\s\S]+?)\n*[ ]*Reservation\s*Details/", $text);

        if (preg_match("/\n+[ ]*(?<confDesc>{$this->opt($this->t('Ref:'))})\s*(?<conf>[-A-Z\d]{5,})\n+[ ]*{$this->opt($this->t('Status:'))}\s*(?<status>\w+)$/siu", $headText, $m)) {
            $t->general()
                ->confirmation($m['conf'], rtrim($m['confDesc'], ': '))
                ->status($m['status']);
        }

        $guests = $this->re("/{$this->opt($this->t('Traveler Names'))}\s*\((\d+)\s*{$this->opt($this->t('Total'))}\)/", $text);

        $reservationText = $this->re("/\n([ ]*Reservation Details(?:[ ]{2}.+)?\n[\s\S]+?)\n+[ ]*Booked through Project Expedition(?:[ ]{2}|\n)/", $text);
        $s = strlen($this->re("/\n(.+ {5,})Notes from Tour/", $text));

        if ($s > 40) {
            $columnPos = $s - 2;
        } else {
            $columnPos = 55;
        }
        $reservationTable = $this->splitCols($reservationText, ['0', (string) $columnPos]);

        $travellersText = $this->re("/Traveler Names(.+)\s*Contact\s*Info/s", $reservationTable[0]);

        if (preg_match_all("/^[ ]*\d{1,3}\.[ ]*({$patterns['travellerName']})$/m", $travellersText, $m)) {
            $t->general()->travellers($m[1], true);
        }

        $s = $t->addSegment();

        $pickupInfo = $this->re("/{$this->opt($this->t('Pickup Details'))}\s+(.+)\s+{$this->t('Drop Off:')}/s",
            $reservationTable[0]);
        $name = $this->re("/\n\s*Location:\s*(.+?)\n[^\n]+:/s", $pickupInfo);

        if (empty($name)) {
            $name = $this->re("/(?:^|\n)\s*From:\s*(.+?)\n[^\n]+:/s", $pickupInfo);
        }

        if (!empty($name)) {
            $s->departure()
                ->name($this->nice($name));
        }
        $address = $this->re("/\n\s*Address:\s*(.+?)\s*(?:Pickup Time:|$)/s", $pickupInfo);

        if (empty($address) && !empty($name)) {
            $address = $this->re("/(?:^|\n)\s*Details:\s*({$name}.+?)\s*(?:\n[^\n]+:|$)/s", $pickupInfo);
        }

        if (!empty($address)) {
            $s->departure()
                ->address($this->nice($address));
        }

        $date = $this->re("/(?:Pickup Time:)\s*(.+)/", $pickupInfo);

        if (empty($date)) {
            $date = $this->re("/(?:^|\n)\s*Pickup:\s*(.+?\d{4}.+?)\n[^\n]+:/", $pickupInfo);
        }

        if (empty($date)) {
            $date = $this->re("/(?:^|\n)\s*Arriving:\s*(.+?\d{4}.+?)\n[^\n]+:/", $pickupInfo);
        }
        $s->departure()
            ->date($this->normalizeDate($date));

        $dropoffInfo = $this->re("/{$this->t('Drop Off:')}\s*(.+?)\s*(?:{$this->t('Contact Info')}|$)/s",
            $reservationTable[0]);

        $name = $this->re("/^(.+?)\n[^\n]+:/s", $dropoffInfo);

        if (!empty($name)) {
            $s->arrival()
                ->name($this->nice($name));
        }
        $address = $this->re("/Address:\s*(.+?)\s*$/s", $dropoffInfo);

        if (!empty($address)) {
            $s->arrival()
                ->address($this->nice($address));
        }

        $notes = $this->re("/Notes from Tour Operator:([\s\S]+?)Tour Operator:/", $reservationTable[1]);

        if ($s->getDepDate()
            && (preg_match("/Duration: (.+?(?:hour|minute).*?) \| /", $notes, $m)
                || preg_match("/Duration: (.+?(?:hour|minute).*?)\n/", $notes, $m))
        ) {
            $duration = $m[1];
            $hours = $this->re("/\b(\d+)\s+hours?\b/i", $duration);
            $minutes = $this->re("/\b(\d+)\s+minutes?\b/i", $duration);
            $endDate = $s->getDepDate();

            if (!empty($hours)) {
                $endDate = strtotime("+" . $hours . ' hours', $endDate);
            }

            if (!empty($minutes)) {
                $endDate = strtotime("+" . $minutes . ' minutes', $endDate);
            }

            if ($endDate !== $s->getDepDate()) {
                $s->arrival()->date($endDate);
            }
        }

        $s->extra()
            ->adults($guests);

        $priceText = $this->re("/\n^(Phone\:.+Payment Status:.+Due on Reservation Day:)/ms", $text);

        if (!empty($priceText)) {
            $priceTable = $this->splitCols($priceText);
            // C$ 120.29 Total Price:
            if (preg_match("/Payment Status:\n+[ ]*(?<currency>[A-Z\S]{2,3})[ ]+(?<total>\d[,.\'\d ]*)\n+[ ]*Total Price:/", $priceTable[1], $m)) {
                $currency = $this->normalizeCurrency($m['currency']);
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $t->price()->currency($currency)->total(PriceHelper::parse($m['total'], $currencyCode));
            } elseif (preg_match("/(?:^[ ]*|[ ]{2})Total Price:\s*(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/m", $text, $m)) {
                // $206.49
                $t->price()->currency($m['currency'])->total($m['amount']);
            }
        }

        $notesText = $this->re("/\n^(Traveler Names.+Notes from Tour Operator:.+Contact Info:)/ms", $text);

        if (!empty($notesText)) {
            $notesTable = $this->splitCols($notesText);

            if (stripos($notesTable[1], 'Emergency number') !== false) {
                $notes = $this->re("/Notes from Tour Operator:\s*(.+)\s*Emergency number/s", $notesTable[1]);
            } elseif (stripos($notesTable[1], 'COVID-19') !== false) {
                $notes = $this->re("/Notes from Tour Operator:\s*(.+)\s*COVID-19/s", $notesTable[1]);
            }

            if (!empty($notes)) {
                $t->setNotes(str_replace("\n", " ", $notes));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($textPdf, 'Pickup Time:') !== false || stripos($textPdf, 'Transfer Reference:') !== false || stripos($textPdf, 'Transfer:') === 0) {
                $this->ParseTransfer($email, $textPdf);
            } else {
                $this->ParseEvent($email, $textPdf);
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = ' . print_r($str, true));

        $str = str_replace('0:00 am', '0:00', $str);

        $in = [
            "#^(\w+)\s*(\d+)\,\s*(\d{4})\,\s*([\d\:]+\s*a?p?m?)$#u", //October 4, 2022, 0:00 am
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'USD' => ['US$'],
            'CAD' => ['C$'],
            'EUR' => ['€'],
            'GBP' => ['£'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }
}
