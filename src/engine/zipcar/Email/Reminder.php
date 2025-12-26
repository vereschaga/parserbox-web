<?php

namespace AwardWallet\Engine\zipcar\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Reminder extends \TAccountChecker
{
    public $mailFiles = "zipcar/it-61299926.eml, zipcar/it-61299971.eml, zipcar/it-211858075.eml";
    public $lang = 'en';
    public static $dictionary = [
        'en' => [],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $email->setType('Reminder' . ucfirst($this->lang));

        $patterns = [
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp][Mm])?', // 4:19PM    |    3pm
        ];

        $r = $email->add()->rental();

        $model = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thanks for reserving')]", null, true, "/Thanks\s+for\s+reserving\s+(?:Zipcar\s+Logo\s+)?(.+)\,\s+/");
        $r->car()->model($model, false, true);

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hi'))}]", null, "/^{$this->opt($this->t('Hi'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }
        $r->general()->traveller($traveller, false);

        $location = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Location:')]", null, true, "/^location\:\s+(.+)$/i")
            ?? $this->http->FindSingleNode("//text()[contains(normalize-space(),'can be picked up from')]", null, true, "/can be picked up from\s+(.{3,80}?)[.!\s]*$/") // it-211858075.eml
        ;

        if (!empty($location)) {
            $r->pickup()
                ->location($location);
        }

        $phone = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Contact:')]", null, true, "/\:\s*([\d\s]+)\s*\|/");
        $r->pickup()->phone($phone, false, true);

        /*
            This is a reminder that you have a reservation with Zipcar Logo Golf Auto Caddy (GF19JNL) on Saturday, 7 September 2019, 09:30 - Sunday, 8 September 2019, 22:00.
        */

        /*
            This is a reminder that you have a reservation with Zipcar Logo Golf Auto Chic (GH19JRO) on Wednesday, 25 December 2019, 08:00 - 23:00.
        */

        /*
            You've got a trip coming up! This is your official reminder that you have a reservation with Focus Hatchback Sandoral (U43CWT) on Sunday, August 31, 2014, 11:30 PM - Monday, September 1, 2014, 1:30 AM.
        */

        $roots = $this->findRoot();

        if ($roots->length === 1) {
            $rootText = $this->http->FindSingleNode('.', $roots->item(0));
        } else {
            $this->logger->debug('Root node not found!');

            return $email;
        }

        // Wednesday, 25 December 2019    |    Monday, September 1, 2014
        $patterns['date'] = '[-[:alpha:]]+\s*,\s*(?:\d{1,2}\s+[[:alpha:]]{3,}|[[:alpha:]]{3,}\s+\d{1,2})[,\s]+\d{4}';

        // Mar 14, 10:41 AM
        $patterns['dateTime'] = "/^(?<date>.{3,}?)\s*,\s*(?<time>{$patterns['time']})$/";

        $datePickUp = $dateDropOff = $timePickUp = $timeDropOff = null;
        $datePickUpVal = $dateDropOffVal = null;

        if (preg_match("/.+\(\s*(?<confNo>[-A-Z\d]{5,})\s*\)\s+on\s+(?<date1>{$patterns['date']}.{3,}?)(?:\s+[A-Z]{3,})?\s+-\s+(?<date2>\b.*?{$patterns['time']})(?:\s+[A-Z]{3,})?[.\s]*$/u", $rootText, $matches)) {
            $r->general()->confirmation($matches['confNo']);

            if (preg_match($patterns['dateTime'], $matches['date1'], $m)) {
                $datePickUpVal = $m['date'];
                $timePickUp = $m['time'];
            }

            if (preg_match($patterns['dateTime'], $matches['date2'], $m)) {
                $dateDropOffVal = $m['date'];
                $timeDropOff = $m['time'];
            } elseif (preg_match("/^{$patterns['time']}$/", $matches['date2'])) {
                $dateDropOffVal = $datePickUpVal;
                $timeDropOff = $matches['date2'];
            }

            if ($timePickUp !== null && $timeDropOff !== null) {
                $pattern = '/\d([ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)$/'; // 3:28 AM

                if (!preg_match($pattern, $timePickUp) && preg_match($pattern, $timeDropOff, $m)) {
                    $timePickUp .= $m[1];
                } elseif (preg_match($pattern, $timePickUp, $m) && !preg_match($pattern, $timeDropOff)) {
                    $timeDropOff .= $m[1];
                }
            }
        }

        if ($datePickUpVal) {
            $datePickUp = strtotime($datePickUpVal);
        }

        if ($dateDropOffVal) {
            $dateDropOff = strtotime($dateDropOffVal);
        }

        $r->pickup()->date(strtotime($timePickUp, $datePickUp));
        $r->dropoff()->date(strtotime($timeDropOff, $dateDropOff));

        $r->dropoff()->same();

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(),'Zipcar')]")->length > 0
            && $this->findRoot()->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@]zipcar\.com/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["subject"], 'ZIPCAR: You reserved') !== false) {
            return true;
        }

        return false;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(),'This is a reminder') or contains(normalize-space(),'This is your official reminder')]");
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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
}
