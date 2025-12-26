<?php

namespace AwardWallet\Engine\sj\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TrainBusTicketPdf extends \TAccountChecker
{
    public $mailFiles = "sj/it-694063823.eml, sj/it-697211140.eml";
    public $reFrom = ["noreply@ticket.sj.se"];
    public $reBody = [
        'en' => ['The ticket was sold by SJ AB', 'Ticket no:'],
    ];
    public $reSubject = [
        '#Tickets for booking#',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";
    public $trainArray = [];
    public $trainSegments = [];
    public $busArray = [];
    public $paxArray = [];
    public static $dict = [
        'en' => [
            'Booking no:' => 'Booking no:',
            'Ticket no:'  => 'Ticket no:',
        ],
    ];
    private $keywordProv = ['.sj.se', 'SJ'];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->strposArray($text, $this->keywordProv)
                        && $this->detectBody($text)
                        && $this->assignLang($text)
                    ) {
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

            if ($this->strposArray($text, $this->keywordProv)
                && $this->detectBody($text)
                && $this->assignLang($text)
            ) {
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
                if (($fromProv && preg_match($reSubject, $headers["subject"]) > 0)
                    || $this->strposArray($headers["subject"], $this->keywordProv)
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

    public function ParseTrain(Email $email, $textPDF)
    {
        $conf = '';
        $traveller = '';
        $ticket = '';
        $t = '';

        if (preg_match("/\n*^(?<traveller>[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\n*(?:Member:.*\n)?Booking no:\s*(?<conf>[A-Z\d\-]{5,10})\nTicket no:\s*(?<ticket>[A-Z\d\-]{10,})/mu", $textPDF, $m)) {
            $conf = $m['conf'];
            $ticket = $m['ticket'];
            $traveller = $m['traveller'];
        }

        if (empty($this->trainArray)) {
            $t = $email->add()->train();
        } else {
            foreach ($email->getItineraries() as $itinerary) {
                if ($conf === $itinerary->getConfirmationNumbers()[0][0]) {
                    $t = $itinerary;
                } else {
                    $t = $email->add()->train();
                }
            }
        }

        if (in_array($conf, $this->trainArray) === false) {
            $this->trainArray[] = $conf;
            $t->general()
                ->confirmation($m['conf']);
        }

        if (in_array($traveller, $this->paxArray) === false) {
            $t->general()
                ->traveller($traveller);
            $this->paxArray[] = $traveller;
        }

        $t->addTicketNumber($ticket, false, $traveller);

        $s = '';
        $segSearch = false;
        $service = '';
        $number = '';
        $car = '';
        $seat = '';

        $trainSegments = $t->getSegments();

        if (preg_match("/\s+Train\s+(?<number>\d+)\s+Carriage\s+(?<car>\d+)\s*Seat\s*(?<seat>\d+)\D*\n*(?<serviceName>SJ.+)\n/", $textPDF, $m)
            || preg_match("/\s+Train\s+(?<number>\d+)\s+Carriage\s+(?<car>\d+)\s*Berth.*\n*(?<serviceName>SJ.+)\n/", $textPDF, $m)) {
            $m['serviceName'] = preg_replace("/\,\s*CIV\s*[<].+/", "", $m['serviceName']);

            $number = $m['number'];
            $car = $m['car'];
            $service = $m['serviceName'];

            if (isset($m['seat']) && !empty($m['seat'])) {
                $seat = $m['seat'];
            }
        } elseif (preg_match("/\s+Train\s*(?<number>\d+)\s*No seat reservation\n*(?<service>(?:S?J?\s*\w+\,|S?J?\s*\w+\n))/u", $textPDF, $m)) {
            $number = $m['number'];
            $service = $m['service'];
        }

        foreach ($trainSegments as $segment) {
            if ($segment->getNumber() === $number) {
                $s = $segment;
                $segSearch = true;
            }
        }

        if ($segSearch === false) {
            $s = $t->addSegment();
        }

        $s->extra()
            ->number($number)
            ->service($service);

        if (!empty($seat)) {
            $s->extra()
                ->seat($seat, false, false, $traveller);
        }

        if (!empty($car)) {
            $s->extra()
                ->car($car);
        }

        if (preg_match("/(?<date>\w+\,\s*\d+\s+\w+\s*\d{4})\n+\s+(?<depTime>\d+\:\d+)\s*(?<depName>.+)\b\s*(?:Barcode for scanning)?\n+\s+(?<arrTime>\d+\:\d+)\s+(?<arrName>.+)\n+/", $textPDF, $m)) {
            $s->departure()
                ->date(strtotime($m['date'] . ',' . $m['depTime']))
                ->name($m['depName'] . ', Europe');

            $arrDate = strtotime($m['date'] . ',' . $m['arrTime']);

            if ($arrDate < $s->getDepDate()) {
                $s->arrival()
                    ->date(strtotime('+1 day', $arrDate));
            } else {
                $s->arrival()
                    ->date($arrDate);
            }

            $s->arrival()
                ->name($m['arrName'] . ', Europe');
        }

        $cabin = $this->re("/\s(\S+\s(?:class|klasse))/iu", $textPDF);

        if (!empty($cabin)) {
            $s->setCabin($cabin);
        }
    }

    public function ParseBus(Email $email, $textPDF)
    {
        $conf = '';
        $traveller = '';
        $ticket = '';
        $b = '';

        if (preg_match("/\n*^(?<traveller>[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\n*Booking no:\s*(?<conf>[A-Z\d\-]{5,10})\nTicket no:\s*(?<ticket>[A-Z\d\-]{10,})/m", $textPDF, $m)) {
            $conf = $m['conf'];
            $ticket = $m['ticket'];
            $traveller = $m['traveller'];
        }

        if (empty($this->trainArray)) {
            $b = $email->add()->bus();
        } else {
            foreach ($email->getItineraries() as $itinerary) {
                if ($conf === $itinerary->getConfirmationNumbers()[0][0]) {
                    $b = $itinerary;
                }
            }
        }

        if (in_array($conf, $this->trainArray) === false) {
            $this->trainArray[] = $conf;
            $b->general()
                ->confirmation($m['conf']);
        }

        if (in_array($traveller, $this->paxArray) === false) {
            $b->general()
                ->traveller($traveller);
            $this->paxArray[] = $traveller;
        }

        $b->addTicketNumber($ticket, false, $traveller);

        $s = $b->addSegment();

        if (preg_match("/(?<date>\w+\,\s*\d+\s+\w+\s*\d{4})\n+\s+(?<depTime>\d+\:\d+)\s*(?<depName>.+)\b\s*(?:Barcode for scanning)?\n+\s+(?<arrTime>\d+\:\d+)\s+(?<arrName>.+)\n+/", $textPDF, $m)) {
            $s->departure()
                ->date(strtotime($m['date'] . ',' . $m['depTime']))
                ->name($m['depName'] . ', Europe');

            $arrDate = strtotime($m['date'] . ',' . $m['arrTime']);

            if ($arrDate < $s->getDepDate()) {
                $s->arrival()
                    ->date(strtotime('+1 day', $arrDate));
            } else {
                $s->arrival()
                    ->date($arrDate);
            }

            $s->arrival()
                ->name($m['arrName'] . ', Europe');

            if (preg_match("/\s+Bus\s*(?<number>.+)\s*No seat reservation\n*/", $textPDF, $m)) {
                $s->extra()
                    ->number($m['number']);
            } elseif (preg_match("/\s+Bus\s+(?<number>.+)\s+/", $textPDF, $m)) {
                $s->extra()
                    ->number($m['number']);
            }
        }

        $cabin = $this->re("/\s(\S+\s(?:class|klass))/iu", $textPDF);

        if (!empty($cabin)) {
            $s->setCabin($cabin);
        }
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        $textPDF = preg_replace("/[ ]+Ticket \d+ of \d+/", "", $textPDF);
        $textPDF = str_replace('Barcode for scanning', '', $textPDF);

        $segments = $this->splitText($textPDF, "/^(.+\–.+\n*[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]\n*(?:Member:.*\n)?Booking no:\s*[A-Z\d]{5,10}\n*Ticket no:\s*[A-Z\d\-]{10,})/mu", true);

        foreach ($segments as $segment) {
            if (stripos($segment, 'Train') !== false) {
                $this->ParseTrain($email, $segment);
            } elseif (stripos($segment, 'Bus') !== false) {
                $this->ParseBus($email, $segment);
            }
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
                if (strpos($body, $reBody[0]) !== false && strpos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Booking no:'], $words['Ticket no:'])) {
                if (strpos($body, $words["Booking no:"]) !== false && strpos($body, $words['Ticket no:']) !== false) {
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

    private function strposArray($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }
}
