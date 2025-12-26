<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "hertz/it-12.eml, hertz/it-13.eml, hertz/it-1963429.eml, hertz/it-23209054.eml, hertz/it-3977868.eml, hertz/it-62173564.eml";

    public $lang = '';
    public $reHeaders;
    public $bodyText;

    public static $dictionary = [
        'en' => [
            //'Customer Name:' => '',
            'Total' => ['APPROXIMATE RENTAL CHARGE:'],
        ],
    ];
    private $from = [
        'hertz.com',
    ];

    private $subject = [
        'en' => ['HERTZ RESERVATION'],
    ];

    private $body = [
        'en' => ["Thank you for placing your reservation with Hertz"],
    ];

    private $detectLang = [
        'en' => ['Renting'],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->reHeaders = $parser->getHeader('subject');
        $this->bodyText = $parser->getBodyStr();

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->alert("Can't determine a language!");

            return $email;
        }

        foreach ($this->detectLang as $lang => $detectLang) {
            foreach ($detectLang as $phrase) {
                if ($this->http->XPath->query("//text()[{$this->contains($phrase)}]")->length > 0) {
                    $this->parseRentalHTML($email);
                } elseif (!empty($this->re("/({$phrase})/", $this->bodyText))) {
                    $this->parseRentalTEXT($email, $this->bodyText);
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->from as $refrom) {
            if (stripos($from, $refrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $lang => $subjects) {
            foreach ($subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach ($this->body as $lang => $bodys) {
            foreach ($bodys as $body) {
                if ($this->http->XPath->query("//text()[{$this->starts($body)}]")->length > 0
                    || !empty($this->re("/({$body})/", $parser->getBodyStr()))) {
                    foreach (self::$dictionary as $lang => $words) {
                        foreach ($words['Total'] as $word) {
                            if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0
                                || !empty($this->re("/({$word})/", $parser->getBodyStr()))) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    private function parseRentalTEXT(Email $email, $bodyText)
    {
        $r = $email->add()->rental();
        $r->general()
            ->confirmation($this->re("/\n\s*Your (?:reservation|confirmation) number is\s*([A-Z\d\-]+)/", $bodyText), "reservation number")
            ->traveller($this->re("/\n\s*Customer Name\s*:\s*([^\n]+)/", $bodyText), true);

        //PickUp
        $city = $this->re("/\n\s*(?:Renting|Pickup)\s*\n\s*City\s*:\s*([^\n]+).*?\n\s*Return/ims", $bodyText);
        $location = $this->re("/\n\s*(?:Renting|Pickup)\s*\n.*?\n\s*Location\s*:\s*([^\n]+).*?\n\s*Return/ims", $bodyText);
        $address = $this->re("/\n\s*(?:Renting|Pickup)\s*\n.*?\n\s*Address\s*:\s*([^\n]+).*?\n\s*Return/ims", $bodyText);

        $locationFull = $city . ' ' . $location . ' ' . $address;

        $datePickUp = $this->re("/\n\s*(?:Renting|Pickup)\s*\n.*?\n\s*Date\/Time\s*:\s*([^\n]+).*?\n\s*Return/imsu", $bodyText);
        $datePickUp = preg_replace("/\s+$/", "", $datePickUp);
        $datePickUp = preg_replace('/(\D)(00[ ]*[:]+[ ]*\d{2})\s*[AaPp][Mm]$/', '$1$2', $datePickUp);

        $r->pickup()
            ->location(preg_replace("/\s+/", " ", $locationFull))
            ->date($this->normalizeDate($datePickUp))
            ->phone($this->re("/\n\s*(?:Renting|Pickup)\s*\n.*?\n\s*Phone Number\s*:\s*([^\n]+).*?\n\s*Return/ims", $bodyText))
            ->openingHours($this->re("/\n\s*(?:Renting|Pickup)\s*\n.*?\n\s*Location\s+Hours\s*:\s*([^\n]+).*?\n\s*Return\s*/ims", $bodyText));

        //DropOff
        $city = $this->re("/\n\s*Return\s*\n\s*City\s*:\s*([^\n]+).*?\n\s*Vehicle\s*:/ims", $bodyText);
        $location = $this->re("/\n\s*Return\s*\n.*?\n\s*Location\s*:\s*([^\n]+).*?\n\s*Vehicle\s*:/ims", $bodyText);
        $address = $this->re("/\n\s*Return\s*\n.*?\n\s*Address\s*:\s*([^\n]+).*?\n\s*Vehicle\s*:/ims", $bodyText);

        $locationFull = $city . ' ' . $location . ' ' . $address;

        $dateDropOff = $this->re("/\n\s*Return\s*\n.*?\n\s*Date\/Time\s*:\s*([^\n]+).*?\n\s*Vehicle\s*:/imsu", $bodyText);
        $dateDropOff = preg_replace("/\s+$/", "", $dateDropOff);
        $dateDropOff = preg_replace('/(\D)(00[ ]*[:]+[ ]*\d{2})\s*[AaPp][Mm]$/', '$1$2', $dateDropOff);

        $r->dropoff()
            ->location(preg_replace("/\s+/", " ", $locationFull))
            ->date($this->normalizeDate($dateDropOff))
            ->phone($this->re("/\n\s*Return\s*\n.*?\n\s*Phone Number\s*:\s*([^\n]+).*?\n\s*Vehicle\s*:/ims", $bodyText))
            ->openingHours($this->re("/\n\s*Return\s*\n.*?\n\s*Location\s+Hours\s*:\s*([^\n]+).*?\n\s*Vehicle\s*:/ims", $bodyText));

        //Car
        $r->car()
            ->type($this->re("/\n\s*Vehicle\s*:\s*([^\n]+)/", $bodyText))
            ->model($this->re("/\n\s*Vehicle\s*:\s*[^\n]+\n\s*([^\n]+? OR\s+SIMILAR|[ ]*[A-Z\d\- \(\)]+(?=\n))/", $bodyText));

        //Price
        $r->price()
            ->total($this->re("/\s+RENTAL CHARGE\s*:\s*([\d\.]+)/", $bodyText))
            ->currency($this->re("/\s+RENTAL CHARGE\s*:\s*[\d\.]+\s+([A-Z]{3})\s+/", $bodyText));
    }

    private function parseRentalHTML(Email $email)
    {
        $r = $email->add()->rental();
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your reservation number is ')]", null, true, "/([A-Z\d]{10,})/"), "reservation number")
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Customer Name:')]", null, true, "/\:\s*(\D+)/"), true);

        //PickUp
        $city = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Renting')]/following::text()[contains(normalize-space(), 'City')][1]", null, true, "/\:\s*(.+)/");
        $location = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Renting')]/following::text()[contains(normalize-space(), 'Location:')][1]", null, true, "/\:\s*(.+)/");
        $address = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Renting')]/following::text()[contains(normalize-space(), 'Address:')][1]", null, true, "/\:\s*(.+)/");

        $r->pickup()
            ->location($city . ' ' . $location . ' ' . $address)
            ->openingHours($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Renting')]/following::text()[contains(normalize-space(), 'Location Hours:')][1]", null, true, "/\:\s*(.+)/"))
            ->phone($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Renting')]/following::text()[contains(normalize-space(), 'Phone Number:')][1]", null, true, "/\:\s*(.+)/"))
            ->date(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Renting')]/following::text()[contains(normalize-space(), 'Date/Time:')][1]", null, true, "/\:\s*(.+)/")));

        //DropOff
        $city = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Return')]/following::text()[contains(normalize-space(), 'City')][1]", null, true, "/\:\s*(.+)/");
        $location = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Return')]/following::text()[contains(normalize-space(), 'Location:')][1]", null, true, "/\:\s*(.+)/");
        $address = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Return')]/following::text()[contains(normalize-space(), 'Address:')][1]", null, true, "/\:\s*(.+)/");

        $r->dropoff()
            ->location($city . ' ' . $location . ' ' . $address)
            ->openingHours($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Return')]/following::text()[contains(normalize-space(), 'Location Hours:')][1]", null, true, "/\:\s*(.+)/"))
            ->phone($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Return')]/following::text()[contains(normalize-space(), 'Phone Number:')][1]", null, true, "/\:\s*(.+)/"))
            ->date(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Return')]/following::text()[contains(normalize-space(), 'Date/Time:')][1]", null, true, "/\:\s*(.+)/")));

        //Car
        $r->car()
            ->model($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Vehicle:')]", null, true, "/\:\s*(.+)/"))
            ->type($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Vehicle:')]/following::text()[1]"));

        //Price
        $r->price()
            ->total($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'APPROXIMATE RENTAL CHARGE:')]", null, true, "/([\d\.\,]+)\s*[A-Z]{3}\s/"))
            ->currency($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'APPROXIMATE RENTAL CHARGE:')]", null, true, "/[\d\.\,]+\s*([A-Z]{3})\s/"));
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $detectLang) {
            foreach ($detectLang as $phrase) {
                if ($this->http->XPath->query("//text()[{$this->contains($phrase)}]")->length > 0
                    || !empty($this->re("/({$phrase})/", $this->bodyText))) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $this->logger->debug('IN ' . $str);
        $in = [
            "#^\w+\s*(\d+)\s*(\w+)\s*(\d{4})\s*([\d\:]+)\s*(a?p?m)$#ui",
        ];
        $out = [
            "$1 $2 $3, $4 $5",
        ];
        $str = preg_replace($in, $out, $str);
        $this->logger->debug('OUT ' . $str);

        return strtotime($str);
    }
}
