<?php

namespace AwardWallet\Engine\travelocity\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReviewItinerary extends \TAccountChecker
{
    public $mailFiles = "travelocity/it-117916175.eml, travelocity/it-118035807.eml, travelocity/it-118176778.eml";

    private $detectFrom = ['.travelocity.com'];
    private $detectSubject = [
        'Ready for your trip tomorrow? Review your itinerary',
    ];
    private $reBody = [
        'en' => [
            'Here is a summary of your travel plans',
        ],
    ];
    private $lang = '';
    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();


        $this->parseHotel($email);
        $this->parseRental($email);
        // flight the last
        $this->parseFlight($email);

        if ($email->getIsJunk() !== true) {
            $confsOta = array_unique($this->http->FindNodes("//text()[{$this->starts($this->t('Itinerary #:'))}]",
                null, '/^\s*' . $this->opt($this->t('Itinerary #:')) . '\s*(\d{5,})\s*$/'));
            foreach ($confsOta as $conf) {
                $email->ota()->confirmation($conf);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->containsText($headers['subject'], $this->detectSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->containsText($from, $this->detectFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.travelocity.com')]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseHotel(Email $email)
    {
        $xpath = "//tr[*[1][".$this->starts($this->t("Check-in"))."] and *[2][".$this->starts($this->t("Check-out"))."]]";
        $this->logger->debug($xpath);
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $h = $email->add()->hotel();

            // General
            $h->general()
                ->noConfirmation();

            // Hotel
            $h->hotel()
                ->name($this->http->FindSingleNode("./preceding::tr[1][".$this->starts($this->t('Itinerary #:'))."][preceding::tr[not(.//tr)][".$this->eq($this->t('Your Hotel Information'))."]]/preceding::tr[not(.//tr)][2][.//a]", $root))
                ->address($this->http->FindSingleNode("./preceding::tr[1][".$this->starts($this->t('Itinerary #:'))."][preceding::tr[not(.//tr)][".$this->eq($this->t('Your Hotel Information'))."]]/preceding::tr[not(.//tr)][1][not(.//a)]", $root))
            ;

            // Booked
            $h->booked()
                ->checkIn(strtotime($this->http->FindSingleNode(".//td[not(.//td)][".$this->starts($this->t("Check-in"))."]/following::td[not(.//td)][1]", $root)))
                ->checkOut(strtotime($this->http->FindSingleNode(".//td[not(.//td)][".$this->starts($this->t("Check-out"))."]/following::td[not(.//td)][1]", $root)))
            ;
        }
    }

    private function parseRental(Email $email)
    {
        $xpath = "//tr[*[1][".$this->starts($this->t("Pick-Up"))."] and *[2][".$this->starts($this->t("Drop-Off"))."]]";
        $this->logger->debug($xpath);
        $segments = $this->http->XPath->query($xpath);
        foreach ($segments as $root) {
            $r = $email->add()->rental();

            // General
            $r->general()
                ->noConfirmation();

            $location = $this->http->FindSingleNode("./preceding::tr[1][".$this->starts($this->t('Itinerary #:'))."][preceding::tr[not(.//tr)][3][".$this->eq($this->t('Your Car Rental Information'))."]]/preceding::tr[not(.//tr)][1]", $root);
            if (preg_match("/Pick-Up Location:\s*(.+?)\s*Drop-Off Location:\s*(.+?)\s*$/", $location, $m)) {
                if (preg_match("/^[A-Z]{3}$/", $m[1])) {
                    $m[1] = 'Airport '.$m[1];
                }
                $r->pickup()->location($m[1]);

                if (preg_match("/^[A-Z]{3}$/", $m[2])) {
                    $m[2] = 'Airport '.$m[2];
                }
                $r->dropoff()->location($m[2]);
            }
            // Booked
            $r->pickup()
                ->date(strtotime($this->http->FindSingleNode(".//td[not(.//td)][".$this->starts($this->t("Pick-Up"))."]/following::td[not(.//td)][1]", $root)));
            $r->dropoff()
                ->date(strtotime($this->http->FindSingleNode(".//td[not(.//td)][".$this->starts($this->t("Drop-Off"))."]/following::td[not(.//td)][1]", $root)));

            // Extra
            $company = $this->http->FindSingleNode("./preceding::tr[1][".$this->starts($this->t('Itinerary #:'))."][preceding::tr[not(.//tr)][3][".$this->eq($this->t('Your Car Rental Information'))."]]/preceding::tr[not(.//tr)][2]", $root);
            $r->extra()
                ->company($company);
            $companies = [
                'hertz' => ['Hertz'],
            ];
            foreach ($companies as $code => $names) {
                foreach ($names as $name) {
                    if ($name === $company) {
                        $r->setProviderCode($code);
                    }
                }
            }
        }
    }

    private function parseFlight(Email $email)
    {
        if (count($email->getItineraries()) > 0) {
            return ;
        }
        $xpath = "//tr[*[1][".$this->starts($this->t("Depart"))."] and *[2][".$this->starts($this->t("Return"))."]]";
        $this->logger->debug($xpath);
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length > 0 && !empty($this->http->FindSingleNode("./preceding::tr[1][".$this->starts($this->t('Itinerary #:'))."][preceding::tr[not(.//tr)][3][".$this->eq($this->t('Your Flight Information'))."]]", $segments->item(0)))) {
            $email->setIsJunk(true);
        }
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $value) {
            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $this->t($field);

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
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
