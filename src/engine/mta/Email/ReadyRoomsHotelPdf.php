<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReadyRoomsHotelPdf extends \TAccountChecker
{
    public $mailFiles = "mta/it-817386290.eml, mta/it-823640917.eml";

    public $reFrom = ["mtatravel.com.au", "@readyrooms.com.au"];
    public $reBody = [
        'en' => ['Prepaid Voucher', 'Accommodation:'],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                if (!$this->assignLang($text)) {
                    $this->logger->debug('can\'t determine a language');

                    continue;
                }
                $this->parseEmail($text, $email);
            }
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

            if ((strpos($text, 'ReadyRooms') !== false)
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
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
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

    private function parseEmail($textPDF, Email $email)
    {
        $email->ota()
            ->confirmation($this->re("#\n *{$this->opt($this->t('ReadyRooms Booking ID:'))} (\d{5,})(?: {3,}|\n)#", $textPDF));

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->re("#\n *{$this->opt($this->t('Reservation Number:'))} *([\dA-Z\-]{5,})(?: {3,}|\n)#", $textPDF));

        $trText = $this->re("#{$this->opt($this->t('Traveller(s) Details:'))} +.*\n([\S\s]+)\n\s*{$this->opt($this->t('Accommodation:'))}#", $textPDF);

        if (preg_match_all("#^ +(\S(?: ?\S)+) {2,}#m", $trText, $m)) {
            $h->general()
                ->travellers($m[1]);
        }

        $h->hotel()
            ->name(preg_replace("#\s+#", ' ',
                $this->re("#{$this->opt($this->t('Accommodation:'))}\s+(.+?)\s+{$this->opt($this->t('Address:'))}#s",
                    $textPDF)))
            ->address(preg_replace("#\s+#", ' ',
                $this->re("#{$this->opt($this->t('Address:'))}\s+(.+?)\s+{$this->opt($this->t('Contact Number:'))}#s",
                    $textPDF)))
            ->phone($this->re("#{$this->opt($this->t('Contact Number:'))} *([\d\-\(\)\+ ]{5,})#", $textPDF));

        if (preg_match("/{$this->opt($this->t('Check In:'))} *(?<date>.+?) *(?:\s*\(\D*\b(?<time>\d{1,2}:\d{2}(?:[ap]m)?)\))?\n\s*{$this->opt($this->t('Check Out:'))}/s", $textPDF, $m)) {
            $h->booked()
                ->checkIn(strtotime($m['date'] . ', ' . $m['time']));
        }

        if (preg_match("/{$this->opt($this->t('Check Out:'))} *(?<date>.+?) *(?:\s*\(\D*\b(?<time>\d{1,2}:\d{2}(?:[ap]m)?)\))?\n\s*{$this->opt($this->t('Duration:'))}/s", $textPDF, $m)) {
            $h->booked()
                ->checkOut(strtotime($m['date'] . ', ' . $m['time']));
        }

        $h->addRoom()
            ->setType(preg_replace("#\s+#", ' ',
                $this->re("#{$this->opt($this->t('Room type:'))}\s+(.+?)\s+{$this->opt($this->t('Includes:'))}#s",
                    $textPDF)))
            ->setDescription(preg_replace("#\s+#", ' ',
                $this->re("#{$this->opt($this->t('Includes:'))}\s+(.+?)\s+{$this->opt($this->t('Special request(s):'))}#s",
                    $textPDF)))
        ;

        $cancellation = $this->re("#\n *{$this->opt($this->t('Cancellation Policy'))}\s+(.+?)\n *{$this->opt($this->t('Helpful Tips When Checking In'))}#s", $textPDF);
        $h->setCancellation(trim(preg_replace(["/\s*\n *{$this->opt($this->t('ReadyRooms Booking ID:'))}.+\n *Page \d+.+\n\s*/", "/\s+/"], ' ', $cancellation)));

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
