<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Schema\Parser\Email\Email;

class HotelPdf extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-7953987.eml";

    public static $detectProvider = [
        'goibibo' => [
            'from'       => '@goibibo.com',
            'detectBody' => ['IBIBO Group'],
        ],
        'maketrip' => [
            'from'       => '@makemytrip.com',
            'detectBody' => ['MakeMyTrip'],
        ],
    ];

    private $lang = 'en';
    private $pdf;

    private $detects = [
        'This is a computer generated Invoice and does not require Signature/Stamp',
        'This is a computer generated advance voucher and does not require Signature/Stamp',
    ];

    private $detectFrom = 'makemytrip';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $body = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);

            foreach ($this->detects as $detect) {
                if (stripos($body, $detect) !== false) {
                    $this->pdf = clone $this->http;
                    $this->pdf->SetEmailBody($body);

                    foreach (self::$detectProvider as $code => $params) {
                        if (isset($params['detectBody']) && $this->striposAll($body, $params['detectBody']) !== false) {
                            $email->setProviderCode($code);

                            break;
                        }
                    }
                    $this->parseEmail($email);
                }
            }
        }

        foreach (self::$detectProvider as $code => $params) {
            if (isset($params['from']) && !stripos($parser->getCleanFrom(), $params['from']) === false) {
                $email->setProviderCode($code);

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $body = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach ($this->detects as $detect) {
                if (stripos($body, $detect) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectProvider as $params) {
            if (isset($params['from']) && !stripos($headers["from"], $params['from']) === false) {
                return true;
            }
        }

        return false;
    }

    private function parseEmail(Email $email): void
    {
        $r = $email->add()->hotel();
        // ConfirmationNumber
        $conf = $this->getNode('Booking ID:');

        if ($conf) {
            $r->general()->confirmation($conf);
        } else {
            $r->general()->noConfirmation();
        }
        $r->hotel()->name($this->getNode('Hotel Name'));
        $date = $this->getNode('Travel Date');

        if (preg_match('/(\d+ \w+ \d+)\s+-\s+(\d+ \w+ \d+)/', $date, $m)) {
            $r->booked()->checkIn(strtotime($m[1]));
            $r->booked()->checkOut(strtotime($m[2]));
        }
        // 27/06/2020 - 28/06/2020
        elseif (preg_match('#(\d+/\d+/\d{4})\s+-\s+(\d+/\d+/\d{4})#', $date, $m)) {
            $r->booked()->checkIn(strtotime($this->ModifyDateFormat($m[1])));
            $r->booked()->checkOut(strtotime($this->ModifyDateFormat($m[2])));
        }
        $r->hotel()->address($this->getNode('City'));
        $r->hotel()->phone($this->getNode('Contact No.'), false, true);
        $r->general()->traveller($this->getNode('Customer Name'));

        if (!empty($this->getNode('Grand Total', '/([\d\.]+)/'))) {
            $r->price()->total($this->getNode('Grand Total', '/([\d\.]+)/'));
            $r->price()->currency($this->getNode('Grand Total', '/([A-Z]{3})/'));
        } else {
            $r->price()->total($this->getNode('Total Booking Amount', '/([\d\.]+)/'));
            $r->price()->currency($this->getNode('Total Booking Amount', '/([A-Z]{3})/'));
        }
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    private function getNode($str, $re = null)
    {
        return $this->pdf->FindSingleNode("(//p[contains(., '" . $str . "')]/following-sibling::p[1])[1]", null, true, $re);
    }

    private function striposAll($text, $needle): bool
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
}
