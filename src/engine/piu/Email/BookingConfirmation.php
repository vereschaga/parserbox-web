<?php

namespace AwardWallet\Engine\piu\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "piu/it-15607218.eml, piu/it-33980258.eml";
    public $lang = '';
    public static $dict = [
        'en' => [],
    ];

    private $langDetectors = [
        'en' => ['Time of dep/arr', 'Time of dep / arr'],
    ];

    private $dateRelative = 0;

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@italotreno.it') !== false
            || stripos($from, '@mail.italotreno.it') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Italo - Booking Confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(normalize-space(),"With the new Italo free application") or contains(normalize-space(),"Have a nice journey Italo") or contains(.,"italotreno.it")]')->length === 0
            && $this->http->XPath->query('//a[contains(@href,"//www.italotreno.it") or contains(@href,"//biglietti.italotreno.it")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $this->dateRelative = EmailDateHelper::calculateOriginalDate($this, $parser);

        if (!$this->dateRelative) {
            $this->dateRelative = strtotime($parser->getHeader('date'));
        }
        $this->parseEmail($email);
        $email->setType('BookingConfirmation' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $t = $email->add()->train();

        // confirmationNumber
        $confirmationTitle = $this->http->FindSingleNode('//text()[normalize-space(.)="Ticket code:"]');
        $confirmation = $this->http->FindSingleNode('//text()[normalize-space(.)="Ticket code:"]/following::text()[normalize-space(.)][1]', null, true, '/^([A-Z\d]{5,})$/');

        if ($confirmation) {
            $t->general()->confirmation($confirmation, str_replace(':', '', $confirmationTitle));
        }

        // travellers
        $t->addTraveller($this->http->FindSingleNode('//td[starts-with(normalize-space(.),"Booking contact:")]', null, true, '/Booking contact:\s*(.+)/s'));

        // segments
        $segments = $this->http->XPath->query('//tr[ not(.//tr) and ./td[2][ ./descendant::text()[normalize-space(.)="Train N."] ] ]');

        foreach ($segments as $segment) {
            $s = $t->addSegment();

            $xpathFragment1 = 'descendant::text()[normalize-space(.)="Departure Date"]';

            $date = 0;
            $dateText = $this->http->FindSingleNode('./ancestor::table[' . $xpathFragment1 . '][1]/' . $xpathFragment1 . '/following::text()[normalize-space(.)][1]', $segment);

            if (!$dateText) {
                $this->logger->info('incorrect parse departure date!');

                return;
            }
            $dateNormal = $this->normalizeDate($dateText);

            if (preg_match('/\D\d{4}$/', $dateNormal)) {
                $date = strtotime($dateNormal);
            } elseif ($dateNormal && $this->dateRelative) {
                $date = EmailDateHelper::parseDateRelative($dateNormal, $this->dateRelative, true, '%D%/%Y%');
            }

            $xpathFragment2 = 'following::tr[normalize-space()][1]';

            $timesText = $this->http->FindSingleNode($xpathFragment2 . '/*[1]', $segment);
            $times = preg_split('/\s*>\s*/', $timesText);

            if (count($times) !== 2) {
                $this->logger->info('incorrect parse dep/arr times!');

                return;
            }

            // depDate
            // arrDate
            if ($date) {
                $s->departure()->date(strtotime($times[0], $date));
                $s->arrival()->date(strtotime($times[1], $date));
            }

            $xpathFragment3 = './ancestor::table[1]/preceding::table[normalize-space(.)][1]/descendant::*[name() = "a" or name() = "strong"][normalize-space(.)][1]';

            // depName
            // arrName
            $s->departure()->name($this->http->FindSingleNode($xpathFragment3, $segment));
            $s->arrival()->name($this->http->FindSingleNode($xpathFragment3 . '/following-sibling::*[name() = "a" or name() = "span"][normalize-space(.)][1]', $segment));

            // number
            if ($number = $this->http->FindSingleNode("self::*[ *[2][ descendant::text()[normalize-space()='Train N.'] ] ]/" . $xpathFragment2 . '/*[2]', $segment, true, '/^(\d+)$/')) {
                $s->extra()->number($number);
            } else {
                $s->extra()->number(FLIGHT_NUMBER_UNKNOWN);
            }

            // cabin
            $cabin = $this->http->FindSingleNode("self::*[ *[3][ descendant::text()[normalize-space()='Ambience'] ] ]/" . $xpathFragment2 . '/*[3]', $segment);
            $s->extra()->cabin($cabin, false, true);

            $xpathFragment4 = './ancestor::table[1]/following::table[normalize-space(.)][1]/descendant::tr[ ./td[2] ][1]/following::tr[normalize-space(.)][1]';

            // carNumber
            $s->extra()->car($this->http->FindSingleNode($xpathFragment4 . '/td[1]', $segment, true, '/^(\d+)$/'));

            // seats
            $seats = $this->http->FindSingleNode($xpathFragment4 . '/td[2]', $segment, true, '/.*\d.*/');

            if (1 < (count($ss = preg_split('/\s*,\s*/', $seats)))) {
                $s->extra()->seats($ss);
            } elseif ($seats) {
                $s->extra()->seat($seats);
            }

            if ($s->getDepName() && $s->getArrName()) {
                // for google help detect. don't set extended ', Euurope' (europe provider)
                // Roma, Europe - detected like
//                "addressLine": "41-43 Reinhardtstra\u00dfe",
//                "city": "Berlin",
//                "stateName": "Berlin",
//                "countryName": "Germany"
                // better for now is ', Italy'
                $s->departure()->name($s->getDepName() . ', Italy');
                $s->arrival()->name($s->getArrName() . ', Italy');
            }
        }
    }

    private function normalizeDate(string $string)
    {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $string, $matches)) { // 26/06/2018
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})$/', $string, $matches)) { // 26/06
            $day = $matches[1];
            $month = $matches[2];
            $year = '';
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
