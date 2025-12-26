<?php

namespace AwardWallet\Engine\travelodge\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "travelodge/it-62316111.eml, travelodge/it-62480548.eml";
    public $reFrom = ["travelodge.co.uk"];
    public $reBody = [
        'en' => ['Your Confirmation number:', 'Staying in a:', 'Room Cost:', 'Your Confirmation Number:'],
    ];
    public $reSubject = [
        'your travelodge booking confirmation',
        'YOUR BOOKING CONFIRMATION',
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'travelodge.co.uk')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(),'Travelodge Hotels Ltd')]")->length > 0
        ) {
            foreach ($this->reBody as $lang => $rebody) {
                if ($this->http->XPath->query("//text()[" . $this->contains($rebody) . "]")->length == 0) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    private function parseEmail(Email $email)
    {
        $xpath = "//text()[" . $this->starts(['Your Confirmation number', 'Your Confirmation Number']) . "]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $h->general()
                ->confirmation($this->http->FindSingleNode(".", $root, true, "/\:\s*(\d+)$/"), "Confirmation number")
                ->traveller($this->http->FindSingleNode("./following::text()[" . $this->starts(['Guest name', 'Guest Name']) . "][1]/following::text()[normalize-space()][1]", $root), true);

            $h->hotel()
                ->name($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Hotel')][1]/following::text()[normalize-space()][1]", $root));

            $phone = $this->http->FindSingleNode("./following::text()[contains(normalize-space(), 'please call')][1]/ancestor::*[1]", $root, true, "/please\s*call\s*([+]?[\d\s]+)/");

            if (!empty($phone)) {
                $h->hotel()
                    ->phone($phone);
            }

            $addressURL = $this->http->FindSingleNode("./following::a[starts-with(normalize-space(), 'View hotel information')][1]/@href", $root);

            if (!empty($addressURL)) {
                $http = clone $this->http;
                $http->GetURL($addressURL);
                $infoHotel = $http->FindSingleNode("//text()[starts-with(normalize-space(), 'Tel:')]/ancestor::*[1]");

                if (preg_match("/^(.+)\s+Sat\s*nav\s*postcode\:\s*[\s\D\d]+\s+Tel\:\s*[\d\s]+$/", $infoHotel, $m)) {
                    $h->hotel()
                        ->address($m[1]);
                } else {
                    $infoHotel = implode(' ', $http->FindNodes("(//text()[starts-with(normalize-space(), 'Tel:')])[1]/ancestor::*[not(starts-with(normalize-space(), 'Tel:'))][1][count(.//text()[normalize-space()]) < 5]//text()[normalize-space()]"));

                    if (preg_match("/^\s*([^:]+)\s+Tel\:\s*[\d\s]+$/", $infoHotel, $m)) {
                        $h->hotel()
                            ->address($m[1]);
                    }
                }
            }

            $checkIn = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Check in')][1]/following::text()[normalize-space()][1]", $root);
            //03 Aug 2016
            if (preg_match("/^\d+\s+\w+\s+\d{4}$/", $checkIn)) {
                $checkInTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'About check in and check out:')]/following::text()[starts-with(normalize-space(), 'Check in')]", null, true, "/(\d+\s*a?p?m)/i");

                if (!empty($checkInTime)) {
                    $checkIn = $checkIn . '' . $checkInTime;
                }
            }

            $checkOut = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Check out')][1]/following::text()[normalize-space()][1]", $root);
            //03 Aug 2016
            if (preg_match("/^\d+\s+\w+\s+\d{4}$/", $checkOut)) {
                $checkOutTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'About check in and check out:')]/following::text()[starts-with(normalize-space(), 'Check out')]", null, true, "/(\d+\s*noon)/i");

                if (!empty($checkOutTime)) {
                    $checkOut = $checkOut . ' at ' . $checkOutTime;
                }
            }

            $h->booked()
                ->checkIn($this->normalizeDate($checkIn))
                ->checkOut($this->normalizeDate($checkOut));

            $rateType = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Rate:')][1]/following::text()[normalize-space()][1]", $root);
            $roomDescriprion = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Staying in a:')][1]/following::text()[normalize-space()][1]", $root);

            if (!empty($rateType) || !empty($roomDescriprion)) {
                $room = $h->addRoom();

                if (!empty($rateType)) {
                    $room->setRateType($rateType);
                }

                if (!empty($roomDescriprion)) {
                    $room->setDescription($roomDescriprion);
                }
            }

            $cost = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Room Cost:')][1]/following::text()[normalize-space()][1]", $root);
            $this->logger->warning($cost);

            if (!empty($cost)) {
                $h->price()
                    ->cost($this->re("/^\S{1}([\d\.]+)\*?$/u", $cost))
                    ->currency($this->normalizeCurrency($this->re("/^(\S{1})[\d\.]+\*?$/u", $cost)));
            }

            $discount = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Discount:')][1]/following::text()[normalize-space()][1]", $root, true, "/^\-\S{1}([\d\.]+)$/u");

            if (!empty($discount)) {
                $h->price()
                    ->discount($discount);
            }

            $total = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'The total cost for your booking is:')][1]", $root, true, "/\:\s*(\S{1}[\d\.]+)/u");

            if (!empty($total)) {
                $h->price()
                    ->total($this->re("/^\S{1}([\d\.]+)$/u", $total))
                    ->currency($this->normalizeCurrency($this->re("/^(\S{1})[\d\.]+$/u", $total)));
            }
        }

        if (count($nodes) > 1) {
            $total = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'The total cost is:')][1]/following::text()[normalize-space()][1]");

            if (!empty($total)) {
                $email->price()
                    ->total($this->re("/^\S{1}([\d\.]+)$/u", $total))
                    ->currency($this->normalizeCurrency($this->re("/^(\S{1})[\d\.]+$/u", $total)));
            }
        }
    }

    private function normalizeDate($str)
    {
        $in = [
            //30 Aug 2020 at 3pm
            "/^(\d+\s*\w+\s*\d{4})\s*at\s*(\d+a?p?m)$/",
            //02 Sep 2020 at 12 noon
            "/^(\d+\s*\w+\s*\d{4})\s*at\s*(\d+a?p?m?)\s*(?:noon)$/",
        ];
        $out = [
            "$1, $2",
            "$1, $2:00",
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.', 'Rs'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }
}
