<?php

namespace AwardWallet\Engine\triprewards\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ResConfirmationFromPDF extends \TAccountChecker
{
    public $mailFiles = "triprewards/it-1708015.eml";

    public $reFrom = ["@laquinta.com", "@ehm-inc.com"];
    public $reBody = [
        'en' => ['Thank you for choosing the', 'We hope that you enjoy your stay at the'],
    ];
    public $reSubject = [
        'Reservation Confirmation From',
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'Arrival Date:'        => ['Arrival:', 'Arrival Date:'],
            'Departure Date:'      => ['Departure:', 'Departure Date:'],
            'Room Type Requested:' => ['Room Type Requested:', 'Room Type:'],
            'CXL Policy:'          => ['CXL Policy:', 'GTD/CXL Policy:'],
            'Account Number:'      => ['Account Number:', 'Wyndham Rewards#:'],
        ],
    ];
    private $supportedHotels = ['SUPER 8 RAPID CITY', 'DAYS INN MCPHERSON', 'LA QUINTA BY WYNDHAM LAX'];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->detectBody($text) && $this->assignLangText($text)) {
                        $this->parseEmailPdf($text, $email);
                    }
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectBody($text) && $this->assignLangText($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("#\b{$this->opt($this->supportedHotels)}\b#i", $headers["subject"]) > 0)
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        $r = $email->add()->hotel();

        $r->general()
            ->confirmation($this->re("/{$this->t('Confirmation Number:')}[ ]*([\w\-]+)/", $textPDF))
            ->date($this->normalizeDate($this->re("/\n\s*{$this->t('Date:')}[ ]*(.+)/", $textPDF)))
            ->traveller($this->re("/\n\s*{$this->t('Name:')}[ ]*(.+?)(?:\n|[ ]{3,})/", $textPDF))
            ->cancellation($this->re("/{$this->opt($this->t('CXL Policy:'))}[ ]*(.+)/", $textPDF));

        $acc = $this->re("/{$this->opt($this->t('Account Number:'))}[ ]*([\w\-]+)/", $textPDF);

        if (!empty($acc)) {
            $r->program()->account($acc, false);
        }

        if (preg_match("/(.+)\s{2,}(\d.*)\s{2,}{$this->t('phone')}:\s*([\d\s\(\)\s\-]+)\D*\s{2,}{$this->t('fax')}:\s*(.*?)$/ims",
            $textPDF, $ms)) {
            $r->hotel()
                ->name(preg_replace('/\s+/', ' ', trim($ms[1])))
                ->address(nice($ms[2], ','))
                ->phone($ms[3])
                ->fax($ms[4]);
        }

        $r->booked()
            ->checkIn($this->normalizeDate($this->re("/{$this->opt($this->t('Arrival Date:'))}[ ]*(.+)/", $textPDF)))
            ->checkOut($this->normalizeDate($this->re("/{$this->opt($this->t('Departure Date:'))}[ ]*(.+)/",
                $textPDF)))
            ->guests($this->re("/{$this->t('Guests')}\s*:\s*(\d+)/", $textPDF), false, true);

        $room = $r->addRoom();
        $roomRate = implode(";", array_filter(array_map("trim", explode("\n",
            $this->re("/{$this->opt($this->t('Room Rate:'))}\s*(.+?)\s*{$this->opt($this->t('Special Requests:'))}/s",
                $textPDF)))));
        $room
            ->setType($this->nice($this->re("/{$this->opt($this->t('Room Type Requested:'))}\s*(.+)\s*{$this->opt($this->t('Rate Plan Requested:'))}/s",
                $textPDF)))
            ->setRate($roomRate)
            ->setRateType($this->nice($this->re("/{$this->opt($this->t('Rate Plan Requested:'))}\s*(.+)/", $textPDF)));

        // Total Estimated Stay Amount: $874.88 + Tax
        if (preg_match("/\n\s*{$this->opt($this->t('Total Estimated Stay Amount:'))}[ ]+(?<currency>\D+)(?<amount>\d[,.\d ]*)[ ]+\+[ ]{$this->t('Tax')}/m",
            $textPDF, $m)) {
            $r->price()
                ->currency($this->normalizeCurrency($m['currency']))
                ->cost(PriceHelper::cost($m['amount']));
        }

        $this->detectDeadLine($r);

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //Monday, June 09, 2014
            '#^\s*(\w+),\s+(\w+)\s+(\d+),\s+(\d{4})\s*$#u',
        ];
        $out = [
            '$3 $2 $4',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^CANCEL BEFORE (?<time>\d+[ap]m)$/i", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative('0 days', $m['time']);

            return;
        }

        if (preg_match("/^CANCEL (?<hours>\d+) HOURS PRIOR TO (?<time>\d+[ap]m)$/i", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['hours'] . ' hours', $m['time']);

            return;
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
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

    private function assignLangText($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Arrival Date:"], $words["Room Type Requested:"])) {
                if ($this->stripos($body, $words["Arrival Date:"])
                    && $this->stripos($body, $words["Room Type Requested:"])
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function nice($str)
    {
        return trim(preg_replace("/\s+/", ' ', $str));
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
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
}
