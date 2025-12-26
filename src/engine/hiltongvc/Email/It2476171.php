<?php

namespace AwardWallet\Engine\hiltongvc\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It2476171 extends \TAccountChecker
{
    public $mailFiles = "hiltongvc/it-1592820.eml, hiltongvc/it-1605077.eml, hiltongvc/it-1605110.eml, hiltongvc/it-1618425.eml, hiltongvc/it-2476171.eml, hiltongvc/it-2477702.eml";
    public $subjects = [
        '/Reservation Confirmation [#]\d+$/',
    ];

    public $lang = 'en';
    public $subject;
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@hgvc.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'club.hiltongrandvacations.com') != false) {
                return true;
            }

            if ($this->http->XPath->query("//text()[contains(.,'A document confirming your reservation details is attached.') or contains(.,'Thank you for your recent reservation. A document confirming your reservation details is attached.')]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]hgvc\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email, $text)
    {
        $h = $email->add()->hotel();

        $info = $this->re("/^\s*(.+?)\s*(?:\d+|\n)/u", $text);
        $h->general()
            ->confirmation($this->re("/(\w+)\s*\w+\s*\d+,\s*\d{4}/", $text))
            ->travellers(preg_split('/\s+and\s+/i', $info), true);

        if (preg_match("/{$h->getConfirmationNumbers()[0][0]}\s*(?<inDate>\w+\s*\d+\,\s*\d{4})\s*(?<inTime>[\d\:]+\s*A?P?M)\s*(?<outDate>\w+\s*\d+\,\s*\d{4})\s*(?<outTime>[\d\:]+\s*A?P?M)/su", $text, $m)) {
            $h->booked()
                ->checkIn(strtotime($m['inDate'] . ', ' . $m['inTime']))
                ->checkOut(strtotime($m['outDate'] . ', ' . $m['outTime']));
        } elseif (preg_match("/{$h->getConfirmationNumbers()[0][0]}\s*(?<inDate>\w+\s*\d+\,\s*\d{4})\s*(?<outDate>\w+\s*\d+\,\s*\d{4})/su", $text, $m)) {
            $h->booked()
                ->checkIn(strtotime($m['inDate']))
                ->checkOut(strtotime($m['outDate']));
        }

        if (preg_match("/(?:A?P?M|\s\d{4})\n\n\s*(?<name>\D+)\n\s*(?<address>.+)\s+(?<phone>\d+\-\d+\-\d+)\n\s+(?<roomType>.+)\s*(?<pax>\d+)(?:\n|$)/us", $text, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address(preg_replace("/\s*\n\s*/", " ", $m['address']))
                ->phone($m['phone']);

            $room = $h->addRoom();
            $room->setType($m['roomType']);

            $h->booked()
                ->guests($m['pax']);
        }

        $account = $this->re("/^\D+\s+([\d\-\s]+)\s*\n/", $text);

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }

        if (preg_match("/Reservation Confirmation/", $this->subject)) {
            $h->general()
                ->status('confirmed');
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->ParseHotel($email, $text);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
