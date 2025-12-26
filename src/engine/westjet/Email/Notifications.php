<?php

namespace AwardWallet\Engine\westjet\Email;

// TODO: delete what not use
use AwardWallet\Schema\Parser\Email\Email;

class Notifications extends \TAccountChecker
{
    public $mailFiles = "westjet/it-142462913.eml, westjet/it-143874171.eml, westjet/it-145017732.eml, westjet/it-211112915.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'RESERVATION ' => ['Reservation ', 'RESERVATION '],
        ],
    ];

    private $detectFrom = "noreply@notifications.westjet.com";
    private $detectSubject = [
        // en
        'It\'s time to check in for your flight | ',
        'Make sure you’re travel ready | ',
        'Reservation cancellation | ',
        'You can travel with ease to ',
        'Upgrade your seat for more space | ',
        'Get travel ready for ',
        'Important: WestJet itinerary update - ',
    ];
    private $emailSubject;

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query("//img[contains(@src, '.westjet.com')][contains(@src, '/plane-icon.png') or contains(@src, '/plane-icon.jpg')]/ancestor::*[1][count(.//text()[normalize-space()]) = 2]")->length > 0) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->emailSubject = $parser->getSubject();

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

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()][" . $this->starts($this->t("RESERVATION ")) . " or preceding::text()[normalize-space()][1][" . $this->eq(preg_replace('/(^\s*|\s*$)/', '', $this->t("RESERVATION "))) . "]]",
                null, true, "/^\s*(?:" . $this->opt($this->t("RESERVATION ")) . ")?\s*([A-Z\d]{5,7})\s*$/"))
        ;
        $traveller = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hello ")) . "]",
            null, true, "/" . $this->opt($this->t("Hello ")) . "\s*([[:alpha:]][[:alpha:] \-]+),/");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[" . $this->eq(array_map('trim', (array) $this->t("Hello "))) . "]/following::text()[normalize-space()][1][following::text()[normalize-space()][1][starts-with(normalize-space(), ',')]]",
                null, true, "/^\s*([[:alpha:]][[:alpha:] \-]+)\s*$/");
        }

        if (!empty($traveller)) {
            $f->general()
                ->traveller($traveller, false);
        }

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("reservation has been cancelled")) . "])[1]"))
        || stripos($this->emailSubject, 'Reservation cancellation')) {
            $f->general()
                ->status('cancelled')
                ->cancelled();

            return false;
        }
        // Segments
        $xpath = "//img[contains(@src, '/plane-icon.png') or contains(@src, '/plane-icon.jpg')]/ancestor::*[1][count(.//text()[normalize-space()]) = 2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $routes = $this->http->FindNodes(".//text()[normalize-space()]", $root);

            if (count($routes) == 2) {
                $s->departure()
                    ->noCode()
                    ->name($routes[0]);
                $s->arrival()
                    ->noCode()
                    ->name($routes[1]);

                $info = $this->http->FindSingleNode("./following::text()[normalize-space()][not(ancestor::*[position() < 3][contains(@style, 'border:')])][1]/ancestor::*[position() < 3][contains(., '•')][1]", $root);

                if (preg_match("/^\s*(?<date>[^•]+)\s*•[^•]+•\s*(?<time>\d{1,2}:\d{2}(?:[ap]m)?)(?:\s*•\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,5}))?(?:\s*•\s*(?<aircraft>[^•]+))?$/", $info, $m)) {
                    // March 9, 2022 • 3 guests • 9:45 • Boeing 737-MAX8
                    // October 20, 2022  •  2 guests  •  17:15  •  WS 3609  •  De Havilland Dash8 Q400
                    // July 3, 2024 • 1 guest • 15:20 • WS 1775

                    if (!empty($m['al']) && !empty($m['fn'])) {
                        $s->airline()
                            ->name($m['al'])
                            ->number($m['fn']);
                    } else {
                        $s->airline()
                            ->name('WestJet')
                            ->noNumber();
                    }

                    $s->departure()
                        ->date(strtotime($m['date'] . ', ' . $m['time']));
                    $s->arrival()
                        ->noDate();

                    if (!empty($m['aircraft']) && !preg_match("/^\s*\d\s*stop/", $m['aircraft'])) {
                        $s->extra()
                            ->aircraft($m['aircraft'], true);
                    }
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

    // additional methods

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
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date) || empty($this->date)) {
            return null;
        }

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

        return $date;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
