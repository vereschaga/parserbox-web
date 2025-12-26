<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

class MonthlyBroken extends \TAccountChecker
{
    // forwarded message that broke and every line starts with ">"

    protected $propertyRegs = [
        "Balance"       => "/^([\d\,]+)1 award miles$/",
        "PartialLogin"  => "/^MileagePlus\S+\s*\#\s*XXXXX(\d+)$/",
        "MemberStatus"  => "/^MileagePlus status: (.+)$/",
        "EliteMiles"    => "/^Premier qualifying miles2: ([\d\,]+)$/",
        "EliteSegments" => "/^Premier qualifying segments2: ([\d\.]+)$/",
        "EliteDollars"  => "/^Premier qualifying dollars2: (\\\$[\d\,\.]+)$/",
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();
        $props = [];

        if (stripos($body, "> MileagePlus") !== false) {
            $props = $this->ParseEmail($body);
        }

        return [
            "parsedData" => ["Properties" => $props],
            "emailType"  => "MonthlyBrokenStatement",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["subject"]) && preg_match("/fwd: \w+ monthly statement/i", $headers["subject"]);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $html = $parser->getHTMLBody();
        $plain = $parser->getPlainBody();

        return empty($html) && !empty($plain) && stripos($plain, "> MileagePlus");
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]united\.com/", $from);
    }

    protected function ParseEmail($body)
    {
        $props = [];
        $lines = array_values(array_filter(explode("\n", $body), function ($s) {
            $s = trim($s, " >");

            return !empty($s);
        }));

        for ($i = 0; ($i < count($lines)) && count($this->propertyRegs); $i++) {
            $line = trim($lines[$i], " >");

            if (strpos($line, "My account") === 0) {
                $nameIsNext = true;

                continue;
            }

            if (isset($nameIsNext)) {
                $props["Name"] = $line;
                unset($nameIsNext);

                continue;
            }

            foreach ($this->propertyRegs as $field => $regexp) {
                if (preg_match($regexp, $line, $m)) {
                    $props[$field] = $m[1];
                    unset($this->propertyRegs[$field]);

                    break;
                }
            }
        }

        if (isset($props["Balance"])) {
            $props["Balance"] = str_replace(",", "", $props["Balance"]);
        }

        if (isset($props["PartialLogin"])) {
            $props["PartialLogin"] .= "$";
        }

        return $props;
    }
}
