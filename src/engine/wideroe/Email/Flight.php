<?php

namespace AwardWallet\Engine\wideroe\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "wideroe/it-18173768.eml";

    private $detects = [
        'Please note that on most Wideroe flights',
    ];

    private $pdfDetects = [
        'WIDEROE MYIDTRAVEL',
    ];

    private $from = '/[@.](?:wideroe|wias)[.](?:no)/';

    private $prov = 'Wideroe';

    private $lang = 'en';

    private $pdf = '';

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (0 < count($pdfs)) {
            $pdf = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

            foreach ($this->pdfDetects as $pdfDetect) {
                if (false !== stripos($pdf, $pdfDetect)) {
                    $this->pdf = $pdf;
                    $this->parseEmail($email);

                    break;
                }
            }
        }
        $ns = explode('\\', __CLASS__);
        $class = end($ns);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseEmail(Email $email): Email
    {
        $f = $email->add()->flight();

        $text = $this->cutText('Route', 'Form of Payment', $this->pdf);

        if (empty($text)) {
            $this->logger->info("Segments did not found");

            return $email;
        }

        $re = '/(?<an>[A-Z\d]{2})\s*(?<fn>\d+)\s*\/\s*(?<day>\d{1,2})\s*(?<month>\w+)\s+(?<dname>.+)\s*\-\s*(?<aname>.+)\s+(?<dtime>\d{1,2}:\d{2})\s+(?<atime>\d{1,2}:\d{2})\s+\d+:\d+\s+(?:Terminal\s+(?<term>[A-Z\d]+))?.*\s*\s+(?<bc>[A-Z])\s*\/\s*\w+/u';
        preg_match_all($re, $text, $segments, PREG_SET_ORDER);

        $itinerary = $this->cutText('Electronic Ticket Itinerary/Receipt', 'IATA Number', $this->pdf);
        preg_match_all('/Electronic\s+Ticket\s+Itinerary\/Receipt\s+(.+?\/.+?\s+[mrsi]{1,5})/ui', $itinerary, $m);
        $f->general()
            ->travellers($m[1]);

        preg_match_all('/Ticket\s+Number\s*\:\s*([\d-]+)/i', $text, $m);

        if (!empty($m[1])) {
            $f->setTicketNumbers($m[1], false);
        }

        if (preg_match('/Booking Reference\s*\:\s*(\w+)/i', $itinerary, $m)) {
            $f->general()->confirmation($m[1]);
        }

        if (preg_match('/Fare\s+([\d\.]+)\s+[A-Z]{3}\s+(?:Equivalent Fare Paid\s+[\d\.]+\s+[A-Z]{3}\s+)?Taxes, Fees, Other Charges\s+([\d\.]+)\s+[A-Z]{3}\s+Ticket Amount\s*:\s*([\d\.]+)\s+([A-Z]{3})/', $text, $m)) {
            $f->price()
                ->cost($m[1])
                ->tax($m[2])
                ->currency($m[4])
                ->total($m[3]);
        }

        $year = '';

        if (preg_match('/Date of Issue\s*\:\s*\d{1,2}\s*[a-z]+\s*(\d{2,4})/i', $itinerary, $m)) {
            $year = $m[1];
        }

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $date = $segment['day'] . ' ' . $segment['month'] . ' ' . $year;

            $s->airline()
                ->name($segment['an'])
                ->number($segment['fn']);

            $s->departure()
                ->terminal($segment['term']);

            $s->departure()
                ->name($segment['dname'])
                ->date(strtotime($date . ', ' . $segment['dtime']));

            $s->arrival()
                ->name($segment['aname'])
                ->date(strtotime($date . ', ' . $segment['atime']));

            $s->extra()
                ->bookingCode($segment['bc']);

            $s->departure()
                ->noCode();

            $s->arrival()
                ->noCode();
        }

        return $email;
    }

    private function cutText(string $start, string $end, string $text)
    {
        if (empty($start) && empty($end) && empty($text)) {
            return false;
        }

        return stristr(stristr($text, $start), $end, true);
    }
}
