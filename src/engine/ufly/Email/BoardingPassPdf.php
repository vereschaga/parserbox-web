<?php

namespace AwardWallet\Engine\ufly\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "ufly/it-102227644.eml";

    private $detectSubject = [
        // en
        'Sun Country Boarding Passes for Reservation',
    ];
    private $langDetectors = [
        'en' => ['  Boarding  '],
    ];
    private $lang = '';
    private static $dictionary = [
        'en' => [
            'DATE' => 'DATE',
            'FLIGHT #' => 'FLIGHT #',
        ],
    ];

    private $patterns = [
        'time'          => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?', // 4:19PM    |    2:00 p.m.    |    3pm
        'travellerName' => '[[:alpha:]][-.\'\/[:alpha:] ]*[[:alpha:]]', // Mr. RYBANSKY / MICHAEL
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@suncountry.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectSubject as $dSubject) {
            if ( ( stripos($headers['subject'], 'Sun Country') !== false || self::detectEmailFromProvider($headers['from']) === true)
                && stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            $mainText = $this->re("/^((.*\n+){0,10})/", $textPdf);
            foreach (self::$dictionary as $lang => $dict) {
                    if (isset($dict['DATE'], $dict['FLIGHT #']) && strpos(trim($mainText), $dict['DATE']) === 0
                        && preg_match("/".$this->opt($dict['FLIGHT #'])."\s*\n.+? {2,}(SY)\d{1,5}\s*\n/", $mainText)
                    ) {
                    $this->lang = $lang;
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            $mainText = $this->re("/^((.*\n+){0,10})/", $textPdf);
            foreach (self::$dictionary as $lang => $dict) {
                if (isset($dict['DATE'], $dict['FLIGHT #']) && strpos(trim($mainText), $dict['DATE']) === 0
                    && preg_match("/".$this->opt($dict['FLIGHT #'])."\s*\n.+? {2,}(SY)\d{1,5}\s*\n/", $mainText)
                ) {
                    $this->lang = $lang;
                    $pdfFileName = $this->getAttachmentName($parser, $pdf);
                    $this->parseEmailPdf($email, $textPdf, $pdfFileName);
                    continue 2;
                }
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

    private function parseEmailPdf(Email $email, $text = '', $fileName = '')
    {
//        $this->logger->debug('$text = '.print_r( $text,true));

        $f = $email->add()->flight();

        // General
        $conf = $this->re("/\s+{$this->opt($this->t('RESERVATION CODE'))}\s*\n.* {2,}([A-Z\d]{5,7})\s*\n/", $text);
        $traveller = $this->re("/\n\s*{$this->opt($this->t('PASSENGER'))} +.*\n\s+([[:alpha:]][-[:alpha:] ]*[[:alpha:]]) {2,}/", $text);
        $account = $this->re("/\s+{$this->opt($this->t('RESERVATION CODE'))}\s*\n\s*(\d{6,}) {2,}[A-Z\d]{5,7}\s*\n/", $text);

        $s = $f->addSegment();

        if (preg_match("/\n\s*{$this->opt($this->t('DEPARTURE'))} +.*\n\s*([A-Z]{3}) +([A-Z]{3})\s*\n/", $text, $m)) {
            $s->departure()
                ->code($m[1]);
            $s->arrival()
                ->code($m[2]);
        }

        $date = $this->re("/^\s*{$this->opt($this->t('DATE'))}\s*\n\s*(.*\b\d{4}\b.*)\s*\n/", $text);

        $dateRe = '\d{1,2}:\d{2}(?: ?[ap]m)?';
        $this->logger->debug('$date = '.print_r( "/.+ ".$this->opt($this->t('FLIGHT #'))."\s*\n.+?  +(?<dTime>{$dateRe})  +(?<aTime>{$dateRe})  +(?<al>SY)(?<fn>\d{1,5})\s*\n/",true));
        if (!empty($date) && preg_match("/.+ ".$this->opt($this->t('FLIGHT #'))."\s*\n.+?  +(?<dTime>{$dateRe})  +(?<aTime>{$dateRe})  +(?<al>SY)(?<fn>\d{1,5})\s*\n/", $text, $m)) {
            $s->airline()
                ->name($m['al'])
                ->number($m['fn'])
            ;

            $s->departure()
                ->date(strtotime($date . ', ' .  $m['dTime']));
            $s->arrival()
                ->date(strtotime($date . ', ' .  $m['aTime']));

        }

        if (preg_match("/\n\s*{$this->opt($this->t('PASSENGER'))} +.*\n\s+\S.+ {2,}(\d{1,3}[A-Z])\b/", $text, $m)) {
            $s->extra()
                ->seat($m[1]);
        }

        // Boarding Pass
        $bp = $email->createBoardingPass();

        $bp->setTraveller($traveller);
        $bp->setRecordLocator($conf);

        $bp->setAttachmentName($fileName);
        $bp->setDepCode($s->getDepCode());
        $bp->setFlightNumber($s->getAirlineName().' '.$s->getFlightNumber());
        $bp->setDepDate($s->getDepDate());

        $foundIt = false;
        $foundSegment = false;
        foreach ($email->getItineraries() as $it) {
            if ($it->getId() !== $f->getId()) {

                if (in_array($conf, array_column($it->getConfirmationNumbers(), 0))) {
                    $foundIt = true;
                }

                $segments = $it->getSegments();

                foreach ($segments as $segment) {
                    if (serialize(array_diff_key($segment->toArray(), ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))) {
                        $segment->extra()->seats(array_merge($segment->getSeats(), $s->getSeats()));
                        $f->removeSegment($s);

                        if (!in_array($conf, array_column($it->getConfirmationNumbers(), 0))) {
                            $it->general()
                                ->confirmation($conf);
                        }
                        if (!in_array($traveller, array_column($it->getTravellers(), 0))) {
                            $it->general()
                                ->traveller($traveller, true);
                        }

                        if (!empty($account) && !in_array($account, array_column($it->getAccountNumbers(), 0))) {
                            $it->program()
                                ->account($account, true);
                        }

                        $foundSegment = true;
                        $email->removeItinerary($f);

                        break;
                    }
                }

                if ($foundSegment == false && $foundIt == true) {
                    $it->addSegment()->fromArray($s->toArray());
                    $email->removeItinerary($f);
                }
            }
        }

        if ($foundSegment == false && $foundIt == false) {
            // General
            $f->general()
                ->confirmation($conf)
                ->traveller($traveller, true);
            if (!empty($account)) {
                $f->program()
                    ->account($account, false);
            }
        }

        return true;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function getAttachmentName(\PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $m)) {
            return $m[1];
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);
        if(isset($m[$c])) return $m[$c];
        return null;
    }
}
