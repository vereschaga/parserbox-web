<?php

namespace AwardWallet\Engine\germanwings\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "germanwings/it-201116533.eml, germanwings/it-4205401.eml, germanwings/it-4429615.eml, germanwings/it-4448807.eml, germanwings/it-4940229.eml, germanwings/it-4940244.eml, germanwings/it-4940250.eml, germanwings/it-4958850.eml";

    public $lang = 'de';
    public $pdfs;
    public $segUnique;
    public $flightsArray = [];

    public static $dictionary = [
        "de" => [
            "subjectsForRegExp" => [
                'Bordkarten für Ihren Eurowings Flug der Buchung',
                'Bordkarten für Ihren Germanwings Flug der Buchung',
                'Ihre Bordkarte(n) für Flug',
                'Your boarding pass(es) for flight',
            ],
            'Name:'      => ['Name:', 'NAME:'],
            'Flugnummer' => ['Flugnummer', 'FLUGNUMMER', 'FLIGHT NUMBER:'],
            'Sequenz:'   => ['Sequenz:', 'SEQUENZNUMMER', 'SEQUENCE NUMBER:'],
            'Datum'      => ['Datum', 'DATUM', 'DATE'],
            'Flug'       => ['Flug', 'FLUG', 'FLIGHT:'],
            'Sitzplatz'  => ['Sitzplatz', 'SITZPLATZ', 'SEAT:'],
            'Tarif'      => ['Tarif', 'TARIF', 'FARE:'],

            //			'Bordkarten für Ihren Eurowings Flug der Buchung' => '',
            //			'Name:' => '',
            //			'Flugnummer' => '',
            //			'Sequenz:' => '',
            //			'Datum' => '',
            //			'Flug' => '',
            //			'Sitzplatz' => '',
            //			'Tarif' => '',
        ],
        "en" => [
            "subjectsForRegExp" => [
                'Boarding passes for your Eurowings flight of booking',
                'Boarding passes for your Germanwings flight of booking',
            ],
            'Name:'      => ['Name:', 'NAME:'],
            'Flugnummer' => ['Flight No.', 'FLIGHT NUMBER:'],
            'Sequenz:'   => ['Sequence:', 'SEQUENCE NUMBER:'],
            'Datum'      => ['Date', 'DATE'],
            'Flug'       => ['Flight:', 'FLIGHT:'],
            'Sitzplatz'  => ['Seat', 'SEAT:'],
            'Tarif'      => ['Fare', 'FARE:'],
        ],
    ];

    protected $pdf;
    private $ParsePdf_3Fail = false;
    private $recLocSubj;
    private $langDetectors = [
        "en" => 'Flight No',
        "de" => 'Flugnummer',
    ];

    // hard-code
    private $airportNames = [
        'Rome - Fiumicino',
        'Berlin - Brandenburg',
        'Berlin - Tegel',
        'Cologne - Bonn',
        'Dusseldorf',
        'Barcelona',
        'Bucharest',
        'Hannover',
        'Hamburg',
        'Ancona',
        'Vienna',
        'Split',
    ];

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && (
                stripos($headers['subject'], 'Bordkarten') !== false
                || stripos($headers['subject'], 'Mobile Boarding Pass for your Germanwings') !== false
                || stripos($headers['subject'], 'Boarding passes for your Eurowings flight of booking') !== false
                || stripos($headers['subject'], 'Bordkarten für Ihren Eurowings Flug der Buchung') !== false
            );
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*eurowings_boardingpass.*pdf');

        $body = $this->http->Response['body'];

        return stripos($body, 'durchgeführt durch: Germanwings') !== false
            || stripos($body, 'Eurowings wünscht Ihnen einen angenehmen Flug') !== false
            || stripos($body, 'Im Anhang dieser E-Mail finden Sie Ihre Bordkarten') !== false
            || stripos($body, 'Your boarding pass documents are attachted ') !== false
            || (count($pdfs) > 0);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'germanwings.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $its = [];

        foreach ($this->langDetectors as $lang => $value) {
            if ($this->http->XPath->query("//text()[contains(normalize-space(), '{$value}')]")->length > 0) {
                $this->lang = $lang;
            }
        }
        $this->recLocSubj = $this->http->FindPreg("#{$this->opt($this->t('subjectsForRegExp'))} ([A-Z\d]{5,})#",
            false, $parser->getSubject());

        $this->pdfs = $parser->searchAttachmentByName('.*pdf');
        $this->ParseHTML($email, $its, $parser);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return ['de', 'en'];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function getBoardingPassFileName($traveller, $flightNumber, $recordLocator, $depName, PlancakeEmailParser $parser)
    {
        if (count($this->pdfs) > 0) {
            foreach ($this->pdfs as $pdf) {
                $fileName = $this->getAttachmentName($parser, $pdf);
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (preg_match("/{$recordLocator}/u", $fileName)) {
                    $travellerName = $this->re("/^\s*(\w+)/u", $traveller);
                    //if (stripos($fileName, $travellerName) !== false) { //Not always the name of the passenger in the file name
                    if (stripos($text, $flightNumber) !== false) {
                        if (preg_match("/{$this->opt($depName)}\s*\-\>/u", $text)) {
                            return $fileName;
                        }
                    }
                    //}
                }
            }
        }
    }

    protected function recordLocatorInArray($recordLocator, $array)
    {
        $result = false;

        foreach ($array as $key => $value) {
            if (in_array($recordLocator, $value)) {
                $result = $key;
            }
        }

        return $result;
    }

    private function NormalizeDate($d)
    {
        //09-02-2015 06:30
        $ret = $d;

        if (preg_match('#(?<Month>\d+)\-(?<Day>\d+)\-(?<Year>\d+)\s+(?<Time>\d+\:\d+)#', $d, $m)) {
            $ret = $m['Day'] . '.' . $m['Month'] . '.' . $m['Year'] . ' ' . $m['Time'];
        }

        return $ret;
    }

    private function ParseHTML(Email $email, $oldits, PlancakeEmailParser $parser)
    {
        $this->logger->notice(__METHOD__);

        $f = $email->add()->flight();

        $names = [];

        foreach ($oldits as $it) {
            foreach ($it['TripSegments'] as $segment) {
                $names[] = [$segment['DepName'], $segment['ArrName']];
            }
        }

        $pax = [];

        $xpath = "//text()[ {$this->starts($this->t('Flugnummer'))} and preceding::text()[normalize-space()][position()<3][{$this->starts($this->t('Name:'))}] ]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $nodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Flugnummer'))}]");
        }
        $this->logger->debug("Found {$nodes->length} itineraries");

        if ($nodes->length === 0) {
            $this->logger->debug('Error parse HTML!');

            return [];
        }

        foreach ($nodes as $root) {
            $text = '';
            $r2 = $this->http->XPath->query("preceding::text()[normalize-space()][1]", $root)->item(0);
            $i = 0;

            while (($r2 = $this->http->XPath->query("following::text()[normalize-space()][1]", $r2)->item(0)) && $i < 15) {
                $str = $this->http->FindSingleNode(".", $r2);
                $text .= "\n" . $str;
                $i++;

                if ($this->arrikey($str, (array) $this->t('Sequenz:')) !== false) {
                    break;
                }
            }

            $passenger = $this->http->FindSingleNode("preceding::text()[normalize-space()][2][{$this->eq($this->t('Name:'))}]/following::text()[normalize-space()][1]", $root, true, "/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u");

            if (!$passenger) {
                $passenger = $this->http->FindSingleNode("preceding::text()[normalize-space()][1][{$this->starts($this->t('Name:'))}]", $root, true, "/^{$this->opt($this->t('Name:'))}\s*([[:alpha:]][-.\'\/[:alpha:] ]*[[:alpha:]])$/u");
            }

            if (!$passenger) {
                $passenger = $this->http->FindSingleNode(".", $root, true, "/{$this->opt($this->t('Name:'))}\s*([[:alpha:]][-.\'\/[:alpha:] ]*)(?:MRS|MR|MS|MISS)\s+{$this->opt($this->t('Flugnummer'))}/u");
            }

            if ($passenger) {
                $pax[] = $passenger;
            }

            //----------------------------------------------
            $flightNumber = '';

            if (preg_match("#" . $this->opt($this->t("Flugnummer")) . "[\s:]+([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)#", $text, $m)) {
                $flightNumber = $m[2];
            } elseif (preg_match("#" . $this->opt($this->t("Flugnummer")) . "[\s:]+(\d{1,5})\D#", $text, $m)) {
                $flightNumber = $m[1];
            }

            if (!in_array($flightNumber, $this->flightsArray) || count($this->flightsArray) === 0) {
                $this->flightsArray[] = $flightNumber;
                $s = $f->addSegment();
                $this->segUnique = $s;
            }
            //----------------------------------------------

            if (preg_match("#" . $this->opt($this->t("Flugnummer")) . "[\s:]+([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)#", $text, $m)) {
                $this->segUnique->airline()
                    ->name($m[1])
                    ->number($m[2]);
            } elseif (preg_match("#" . $this->opt($this->t("Flugnummer")) . "[\s:]+(\d{1,5})\D#", $text, $m)) {
                $this->segUnique->airline()
                    ->noName()
                    ->number($m[1]);
            }
            $this->segUnique->departure()
                ->noCode();

            $this->segUnique->arrival()
                ->noCode();

            $depDate = strtotime($this->NormalizeDate($this->http->FindPreg("#" . $this->opt($this->t("Flugnummer")) . "[\s\S]*?" . $this->opt($this->t("Datum")) . "[\s:]+(\d+[\d \-]*?\d+:\d+)#", false, $text)));

            if (!empty($depDate)) {
                $this->segUnique->departure()
                    ->date($depDate);
            } elseif (empty($depDate)) {
                $depDate = $this->NormalizeDate($this->http->FindPreg("#" . $this->opt($this->t("Flugnummer")) . "[\s\S]*?" . $this->opt($this->t("Datum")) . "[\s:]+(\d+\w+\.?\d+)\s+#", false, $text));

                if (preg_match("/^(?<day>\d+)\s*(?<month>\D+)\s*(?<year>\d+)$/u", $depDate, $m)) {
                    $depDate = strtotime($m['day'] . ' ' . $m['month'] . ' 20' . $m['year']);
                    $s->departure()
                        ->day($depDate)
                        ->noDate();
                }
            }

            $this->segUnique->arrival()
                ->noDate();

            $namesrow = $this->http->FindPreg("#" . $this->opt($this->t("Flug")) . "[\s:]+(.+)#", false, $text);

            if (preg_match("/{$this->opt($this->t("Datum"))}/", $namesrow)) {
                if (preg_match("/^(.+){$this->opt($this->t("Datum"))}/", $namesrow, $m)) {
                    $namesrow = $m[1];
                }
            }

            if (substr_count($namesrow, "-") == 1 && preg_match("#(.+?)\s*->?\s*(.+)#", $namesrow, $m)) {
                $this->segUnique->departure()
                    ->name($m[1]);
                $this->segUnique->arrival()
                    ->name($m[2]);
            } elseif (preg_match("/^({$this->opt($this->airportNames)})[ ]+->?[ ]+({$this->opt($this->airportNames)})$/i", $namesrow, $m)) {
                $this->segUnique->departure()
                    ->name($m[1]);
                $this->segUnique->arrival()
                    ->name($m[2]);
            } else {
                foreach ($names as $name) {
                    if (strpos($namesrow, $name[0]) === 0 && strpos($namesrow, $name[1]) !== false) {
                        $this->segUnique->departure()
                            ->name($name[0]);
                        $this->segUnique->arrival()
                            ->name($name[1]);
                    }
                }
            }
            $seat = $this->http->FindPreg("#" . $this->opt($this->t("Sitzplatz")) . "[\s:]+(\d+[A-Z])\b#", false, $text);

            if ($seat) {
                $this->segUnique->extra()
                    ->seat($seat);
            }

            $this->segUnique->extra()
                ->cabin($this->http->FindPreg("#{$this->opt($this->t('Tarif'))}[\s:]+(.+){$this->opt($this->t('Sequenz:'))}\s*#s", false, $text));

            $fileName = $this->getBoardingPassFileName($passenger, $this->segUnique->getFlightNumber(), $this->recLocSubj, $this->segUnique->getDepName(), $parser);

            if (!empty($fileName)) {
                $bp = $email->add()->bpass();
                $depDate = !empty($s->getDepDate()) ? $s->getDepDate() : $s->getDepDay();
                $bp->setDepDate($depDate);
                $bp->setTraveller($passenger);
                $bp->setFlightNumber($s->getFlightNumber());
                $bp->setRecordLocator($this->recLocSubj);
                $bp->setAttachmentName($fileName);
            }
        }

        $f->general()
            ->confirmation($this->recLocSubj);

        if (count($pax)) {
            $f->general()
                ->travellers(array_values(array_unique(array_filter($pax))));
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $key;
            }
        }

        return false;
    }

    private function getAttachmentName(PlancakeEmailParser $parser, $pdf)
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

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
