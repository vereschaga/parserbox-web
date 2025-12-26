<?php

namespace AwardWallet\Engine\marriott\Email;

use AwardWallet\Schema\Parser\Email\Email;

class D extends \TAccountChecker
{
    public $mailFiles = "marriott/it-2947696.eml, marriott/it-2949496.eml, marriott/it-2949497.eml, marriott/it-41569449.eml";

    private $lang = '';
    private $reFrom = ['@marriott.com', '@renaissancehotels.com', '@courtyard.com'];
    private $reProvider = ['Marriott Rewards'];
    private $reSubject = [
        'Marriott Reservation Confirmation',
        'The Westfields Marriott Washington Dulles',
        ' stay at the ',
    ];
    private $reBody = [
        'en' => [
            'Thank you for choosing the ',
        ],
    ];
    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        if (!$this->assignLang()) {
            $this->logger->error("Can't determine a language");
        }

        $this->parseHotel($email);
        //$class = explode('\\', __CLASS__);
        //$email->setType(end($class) . ucfirst($this->lang));
        $email->setType('reservation');

        return $email;
    }

    public function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();
        $h->general()->noConfirmation();
        $array = array_filter($this->http->FindNodes("//*[contains(text(), 'Hotel:')]/ancestor-or-self::td[1]/following-sibling::td[1]//text()"));
        $h->hotel()->name(array_shift($array));
        $h->hotel()->phone(array_pop($array));
        $h->hotel()->address(implode(", ", $array));
        $guestHtml = $this->http->FindHTMLByXpath("descendant::*[contains(text(),'Guest:')][1]/ancestor-or-self::td[1]/following-sibling::td[1]");
        $guestText = $this->htmlToText($guestHtml);

        if (preg_match("#^\s*([[:alpha:]][-.'/[:alpha:] ]*[[:alpha:]])[ ]*(?:[\r\n]|$)#u", $guestText, $m)) {
            $h->general()->traveller($m[1]);
        }
        $text = $this->http->FindSingleNode("//*[contains(text(), 'Dates of stay')]/ancestor-or-self::td[1]");

        if (preg_match("#Dates of stay:\s*(\w{3}\s+\d+,\s*\d+)\s*-\s*(\w{3}\s+\d+,\s*\d+)#i", $text, $m)) {
            $h->booked()->checkIn2($m[1]);
            $h->booked()->checkOut2($m[2]);
        }
        $h->program()->account($this->http->FindSingleNode("(//*[{$this->contains($this->t('Marriott Rewards number:'))}]/following-sibling::text())[1]"),
            true);

        if ($total = $this->http->FindSingleNode("//td[{$this->contains($this->t('Payment - '))}]/following-sibling::td[last()]")) {
            $h->price()->total($total);
        }

        $cur = $this->http->FindSingleNode("//td[{$this->contains($this->t('Total balance'))}]/following-sibling::td[last()]");

        if (preg_match("/^[,.\d\s']+\s*([A-Z]{3})/", $cur, $matches)) {
            $h->price()->currency($matches[1]);
        }
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang() && $this->http->FindSingleNode("//text()[{$this->eq($this->t('Summary of Your Stay'))}]")) {
            return true;
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
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

    private function htmlToText($s, $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z]+\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z]+\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
