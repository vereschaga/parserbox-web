<?php

namespace AwardWallet\Engine\norwegiancruise\Email;

// TODO: delete what not use
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ImportantInformation extends \TAccountChecker
{
    public $mailFiles = "norwegiancruise/it-469464774.eml, norwegiancruise/it-481140135.eml, norwegiancruise/it-484340997.eml, norwegiancruise/it-572499343.eml";

    public $lang;
    // "'s sailing on "        => ["'s sailing on ", "'s sailings from", 'upcoming sailing on'],
    // "information regarding" => ["information regarding", "result of a full ship charter,", 'upcoming sailing onboard'],
    public static $dictionary = [
        'en' => [
            'Revised'     => 'Revised',
            'Destination' => 'Destination',
            // information regarding {shipName} 's sailing on {date}
            "'s sailing on "           => ["'s sailing on ", "'s sailings from", 'upcoming sailing on', 'upcoming sailing onboard'],
            "upcoming sailing onboard" => ['upcoming sailing onboard'],
            "ShipName"                 => [
                ["information regarding", "'s sailing on "],
                ["information regarding", "'s sailings from "],
                ["result of a full ship charter,", "'s sailing on "],
                ["chosen to sail with us onboard", "and thank you for"],
                ["upcoming sailing onboard", " on "],
                // ["", ""],
            ],
            "has been canceled" => ["has been canceled", "ITINERARY CANCELATION", "has been cancelled"],
        ],
    ];

    private $detectFrom = "donotreply@ncl.com";
    private $detectSubject = [
        // en
        'Important Information from Norwegian Cruise Line:',
    ];
    private $detectBody = [
        'en' => [
            'share this information with impacted guests',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]ncl\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && stripos($headers["subject"], 'Norwegian Cruise Line') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['www.ncl.com/'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Norwegian Cruise Line'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Revised"], $dict["Destination"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Revised'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Destination'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }

            if (isset($dict["has been canceled"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['has been canceled'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $c = $email->add()->cruise();

        // General
        $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation:'))}]",
            null, true, "/^\s*{$this->opt($this->t('Reservation:'))}\s*(\d{5,})\s*$/");

        if (!empty($conf)) {
            $c->general()
                ->confirmation($conf);
        } elseif (empty($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Revised'))}]/preceding::text()[{$this->starts('Reservation')}])"))) {
            $c->general()
                ->noConfirmation();
        } else {
            $c->general()
                ->confirmation($conf);
        }

        $ship = '';

        foreach ((array) $this->t("ShipName") as $dict) {
            if (is_array($dict) && count($dict) == 2) {
                $ship = $this->http->FindSingleNode("//text()[{$this->contains($dict[0])}]",
                    null, true, "/{$this->opt($dict[0])}\s+([A-Z][A-z ]+)\s*{$this->opt($dict[1])}/");

                if (!empty($ship)) {
                    break;
                }
            }
        }

        $c->details()
            ->ship($ship);

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('has been canceled'))}]")->length > 0) {
            $c->general()
                ->status('canceled')
                ->cancelled();

            return true;
        }
        $startDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->contains($this->t("'s sailing on "))}]",
            null, true, "/{$this->opt($this->t("'s sailing on "))}\s*(\w+[,. \\/]{1,3}\w+[,. \\/]{1,3}\w+[,. \\/]{0,3})(?:\.| through )/"));

        if (empty($startDate)) {
            $startDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->contains($this->t("upcoming sailing onboard"))}]", null, true,
                "/{$this->opt($this->t("upcoming sailing onboard"))} .*? on (\w+[,. \\/]{1,3}\w+[,. \\/]{1,3}\w+[,. \\/]{0,3})(?:\.| through |, )/"));
        }

        if (empty($startDate)) {
            $this->logger->debug('empty dates');

            return false;
        }

        $xpath = "(//tr/*[descendant::text()[normalize-space()][1][{$this->eq($this->t('Revised'))}]][{$this->contains($this->t('Destination'))}])[1]"
            . "/descendant::tr[*[2][{$this->contains($this->t('Destination'))}]]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));

        $segDate = $startDate;

        foreach ($nodes as $i => $root) {
            $row = $this->http->FindNodes('*', $root);

            if (count($row) !== 5 || empty($row[0]) || empty($row[1])) {
                $c->addSegment();
                $this->logger->debug('error in table row. Segment: ' . print_r($row, true));

                break;
            }

            if ($i == 0 || $row[0] === $this->http->FindSingleNode('*[1]', $nodes->item($i - 1))) {
            } else {
                $segDate = strtotime("+1 day", $segDate);
            }

            if ((int) date("w", $segDate) !== (WeekTranslate::number1(WeekTranslate::translate($row[0])) % 7)) {
                $c->addSegment();
                $this->logger->debug('error in dates. Segment: ' . print_r($row, true));

                break;
            }

            if (empty($row[2]) && empty($row[3])) {
                continue;
            }
            $name = $row[1];
            $name = preg_replace("/^\s*OVERNIGHT IN\s+/", '', $name);
            $row[2] = preg_replace("/^\s*Overnight\s+$/", '', $row[2]);
            $row[3] = preg_replace("/^\s*Overnight\s+$/", '', $row[3]);

            $ashore = strtotime($row[2], $segDate);
            $aboard = strtotime($row[3], $segDate);

            if (!empty($aboard) && !empty($ashore)) {
                $s = $c->addSegment();
                $s
                    ->setAboard($aboard)
                    ->setAshore($ashore)
                    ->setName($name)
                ;
            } elseif (isset($s) && !empty($s->getAshore()) && empty($s->getAboard()) && $s->getName() === $name && empty($ashore) && !empty($aboard)) {
                $s
                    ->setAboard($aboard);
            } else {
                $s = $c->addSegment();

                $s->setName($name);

                if (!empty($ashore)) {
                    $s
                        ->setAshore($ashore);
                }

                if (!empty($aboard)) {
                    $s
                        ->setAboard($aboard);
                }
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1 :43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return preg_quote($s, $delimiter);
        }, $field)) . ')';
    }
}
