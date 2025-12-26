<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Event extends \TAccountChecker
{
    public $mailFiles = "expedia/it-803099441.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
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

            if (strpos($text, "TAAP Itinerary") !== false
                && (strpos($text, 'Lead Traveller') !== false)
                && (strpos($text, 'Tour date') !== false)
                && (strpos($text, 'Meeting/Redemption Point') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mtatravel\.com\.au$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParsePDF($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParsePDF(Email $email, $text)
    {
        $this->logger->debug($text);

        $e = $email->add()->event();

        $e->type()
            ->event();

        $e->general()
            ->confirmation($this->re("/Supplier Reference [#][:]\s*([A-Z\d]{5,})\n/", $text), 'Supplier Reference#');

        $bookingDate = $this->re("/Booking Date:\s+(\d+\/\d+\/\d{4})/", $text);

        if (!empty($bookingDate)) {
            $e->general()
                ->date(strtotime(str_replace('/', '.', $bookingDate)));
        }

        if (preg_match_all("/Lead Traveller\:.+\n([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\b\s+Voucher/u", $text, $m)) {
            $e->general()
                ->travellers(array_unique($m[1]));
        }

        if (preg_match("/Meeting\/Redemption Point\n(?<address>(?:.+\n*){1,5})\nPhone:\s+(?<phone>[+\d\s\-\(\)]+)\b\n/", $text, $m)) {
            if (stripos($m['address'], '|') !== false) {
                $m['address'] = preg_replace("/(\|.+)/s", "", $m['address']);
            }
            $e->setAddress(preg_replace("/\n[ ]*/u", " ", $m['address']));
            $e->setPhone($m['phone']);
        }

        $e->booked()
            ->guests($this->re("/\(Adult\s+\d+\/(\d+)\)/", $text));

        if (preg_match("/[ ]{5,}Voucher\s+\d+\/\d+[ ]{5,}(?<date>\d+\s*\w+\s*\d{4})\s+\D+(?<hours>\d+)?h?\s*(?<min>\d+)?m?\n/", $text, $match)) {
            $duration = 0;

            if (isset($match['hours'])) {
                $duration += intval($match['hours'] * 60);
            }

            if (isset($match['min'])) {
                $duration += intval($match['min']);
            }

            if (isset($match['date'])
                && (preg_match("/Wicked On Broadway\:\s*(?<timeStart>[\d\:]+\s*A?P?M)\,.+\n(?<name>.+)\nby/", $text, $m)
                    || preg_match("/(?<name>.+)\nCruise with Lunch\:\s*(?<timeStart>[\d\:]+\s*A?P?M)\,/", $text, $m))) {
                $e->setName($m['name']);

                $e->setStartDate(strtotime($match['date'] . ', ' . $m['timeStart']));
                $e->setEndDate(strtotime($duration . ' min', $e->getStartDate()));
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
