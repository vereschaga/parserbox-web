<?php

namespace AwardWallet\Engine\easyjet\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parsers with similar formats: easyjet/ImportantChanges(object)

class YourBookingFlight extends \TAccountChecker
{
    public $mailFiles = "easyjet/it-66020760.eml, easyjet/it-66021573.eml, easyjet/it-821158347.eml, easyjet/it-821175250.eml, easyjet/it-831963746.eml";
    public $lang = '';
    public $year;

    public $subjects = [
        '/^Ihre Buchung [A-Z\d]{7}/',
        '/^Your booking [A-Z\d]{7}/',
        '/^Je boeking [A-Z\d]{7}/',
        '/^Votre réservation [A-Z\d]{7}/',
    ];

    public $detectLang = [
        'en' => ['YOUR TRIP DETAILS', 'YOUR FLIGHT'],
        'de' => 'IHRE FLUGDATEN',
        'nl' => 'TIJD OM IN TE CHECKEN',
        'it' => 'Informazioni sui voli',
        'fr' => ['C\'EST LE MOMENT DE VOUS ENREGISTRER', 'VOTRE VOL'],
    ];

    public static $dictionary = [
        "en" => [
            'The Countdown\'s on' => ['The Countdown\'s on', 'To get your journey off to a great start, here’s everything you need to know'],
            //'YOUR TRIP DETAILS' => '',
            //'YOUR FLIGHT' => '',
            'YOUR SEATS' => ['YOUR SEATS', 'Your Seats'],
            //'YOUR BAGS' => '',
            //'Depart' => '',
            //'Your booking' => '',
            //'Terminal' => '',
        ],

        "de" => [
            'The Countdown\'s on' => 'Es geht schon bald los',
            'YOUR TRIP DETAILS'   => 'IHRE FLUGDATEN',
            'YOUR FLIGHT'         => 'HINFLUG',
            'YOUR SEATS'          => 'IHRE SITZPLÄTZE',
            'YOUR BAGS'           => 'IHR GEPÄCK',
            'Depart'              => 'Abflug',
            'Your booking'        => 'Ihre Buchung',
            //'Terminal' => '',
        ],

        "nl" => [
            'The Countdown\'s on' => 'uw vlucht is nabij',
            'YOUR TRIP DETAILS'   => 'TIJD OM IN TE CHECKEN',
            'YOUR FLIGHT'         => 'VOTRE VOL',
            'YOUR SEATS'          => 'UW STOELEN',
            'YOUR BAGS'           => 'Uw Tassen',
            'Depart'              => 'Vertrekken',
            'Your booking'        => 'Je boeking',
            //'Terminal' => '',
        ],

        "fr" => [
            'The Countdown\'s on' => ['votre vol est pour bientôt', 'votre vol sur'],
            'YOUR TRIP DETAILS'   => 'C\'EST LE MOMENT DE VOUS ENREGISTRER',
            'YOUR FLIGHT'         => 'VOTRE VOL',
            'YOUR SEATS'          => 'VOS SIÈGES',
            'YOUR BAGS'           => ['Vos Sacs', 'VOS BAGAGES'],
            'Depart'              => 'Départ',
            'Your booking'        => 'Votre réservation',
            'Terminal'            => ['Terminal', 'T'],
        ],
        "it" => [
            'The Countdown\'s on' => 'Per iniziare il viaggio con il piede giusto, ecco tutto quello che devi sapere',
            //'YOUR TRIP DETAILS'   => '',
            'YOUR FLIGHT'         => 'IL TUO VOLO',
            'YOUR SEATS'          => 'IL TUO POSTO',
            'YOUR BAGS'           => 'I TUOI BAGAGLI',
            'Depart'              => 'Partenza',
            'Your booking'        => 'La tua prenotazione',
            'Terminal'            => ['Terminal', 'T'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@info.easyjet.com') !== false) {
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
        if ($this->detectLang() == true) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'easyJet')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('The Countdown\'s on'))}]")->length > 0
                && ($this->http->XPath->query("//text()[{$this->contains($this->t('YOUR TRIP DETAILS'))}]")->length > 0
                    || $this->http->XPath->query("//text()[{$this->contains($this->t('YOUR FLIGHT'))}]")->length > 0)
                && $this->http->XPath->query("//text()[{$this->contains($this->t('YOUR SEATS'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('YOUR BAGS'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]info\.easyjet\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        if ($this->detectLang() == false) {
            $this->logger->warning('Language not defined!!!');

            return false;
        }

        $f = $email->add()->flight();

        if (preg_match("/{$this->opt($this->t('Your booking'))}\s*(?<conf>[A-Z\d]{6,7})\:\s*(?<pax>\D+)\,/u", $parser->getSubject(), $m)) {
            $f->general()
                ->confirmation($m['conf'])
                ->traveller($m['pax']);
        } else {
            $f->general()
                ->noConfirmation();
        }

        $xpath = "//text()[{$this->eq($this->t('Depart'))}]/ancestor::table[2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            //Airline
            $aName = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root, true, "/^([A-Z]{3})\d{2,4}$/");

            if (empty($aName)) {
                $aName = 'U2';
            }

            $fNumber = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root, true, "/^[A-Z]{3}(\d{2,4})$/");

            if (empty($fNumber)) {
                $fNumber = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $root, true, "/^(\d{2,4})$/");
            }

            if (empty($fNumber)) {
                $fNumber = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Depart'))}][1]/preceding::text()[normalize-space()][1]", $root, true, "/^(\d{2,4})$/");
            }

            $s->airline()
                ->name($aName)
                ->number($fNumber);

            //Departure
            $timeDep = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), ':')][1]", $root);
            $dateDep = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), ':')][1]/preceding::text()[normalize-space()][1]", $root);

            $depName = $this->http->FindSingleNode("./descendant::table[4]", $root);

            if (empty($depName)) {
                $depName = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Depart'))}]/ancestor::tr[1]/following::tr[1]/descendant::td[1]/descendant::text()[normalize-space()][1]", $root);
            }

            $s->departure()
                ->noCode()
                ->name($depName)
                ->date($this->normalizeDate($dateDep . ', ' . $timeDep));

            $terminalDep = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), ':')][1]/ancestor::tr[1]/preceding::tr[1]/descendant::td[1]", $root, true, "/{$this->opt($this->t('Terminal'))}\s*(.+)/");

            if (empty($terminalDep)) {
                $terminalDep = $this->http->FindSingleNode("./descendant::table[contains(normalize-space(), ':')][1]/preceding::table[2]/preceding::table[2][not({$this->contains($this->t('YOUR TRIP DETAILS'))})]", $root);
            }

            if (!empty($terminalDep) && $terminalDep !== $s->getDepName() && strlen($terminalDep) < 30) {
                $s->departure()->terminal($terminalDep);
            }

            //Arrival
            $timeArr = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), ':')][2]", $root);
            $dateArr = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), ':')][2]/preceding::text()[normalize-space()][3]", $root);

            $arrName = $this->http->FindSingleNode("./descendant::table[6]", $root);

            if (empty($arrName)) {
                $arrName = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), ':')][1]/ancestor::tr[1]/preceding::tr[1]/descendant::td[3]/descendant::text()[normalize-space()][1]", $root);
            }

            $s->arrival()
                ->noCode()
                ->name($arrName)
                ->date($this->normalizeDate($dateArr . ', ' . $timeArr));

            $terminalArr = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), ':')][1]/ancestor::tr[1]/preceding::tr[1]/descendant::td[2]", $root, true, "/{$this->opt($this->t('Terminal'))}\s*(.+)/");

            if (empty($terminalArr)) {
                $terminalArr = $this->http->FindSingleNode("./descendant::table[contains(normalize-space(), ':')][1]/preceding::table[2]/preceding::table[1][not({$this->contains($this->t('The Countdown\'s on'))})]", $root);
            }

            if (!empty($terminalArr) && $terminalArr !== $s->getArrName() && strlen($terminalArr) < 30) {
                $s->arrival()->terminal($terminalArr);
            }

            //Extra
            $duration = $this->http->FindSingleNode("./descendant::table[2]", $root, true, "/^(\d+\s*(?:h|m).+)/");

            if (empty($duration)) {
                $duration = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Depart'))}]/following::text()[normalize-space()][1]", $root, true, "/^(\d+\s*(?:h|m).+)/");
            }
            $s->extra()
                ->duration($duration);

            $seatsText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your booking'))}]/following::text()[normalize-space()][1]");

            if (preg_match_all("/(\d{1,2}[A-Z])/", $seatsText, $match)) {
                $s->extra()
                    ->seats($match[1]);
            }
        }

        return $email;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);

        $in = [
            "#^\w+\.?\s*(\d+)\s*(\w+)\.?\,\s*([\d\:]+)$#", // Thu 17 Sep, 21:30
        ];
        $out = [
            "$1 $2 $year, $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function detectLang()
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectLang as $lang => $detect) {
            if (is_array($detect)) {
                foreach ($detect as $word) {
                    if (stripos($body, $word) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            } elseif (is_string($detect) && stripos($body, $detect) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }
}
