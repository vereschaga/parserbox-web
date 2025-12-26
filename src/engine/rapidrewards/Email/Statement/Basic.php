<?php

namespace AwardWallet\Engine\rapidrewards\Email\Statement;

class Basic extends \TAccountChecker
{
    public $mailFiles = "";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (substr_count($body, "=3D") > 10) {
            $chars = [];
            $replace = [];

            for ($i = 32; $i < 128; $i++) {
                $chars[] = "=" . strtoupper(dechex($i));
                $replace[] = chr($i);
            }
            $chars[] = "=" . chr(13) . chr(10);
            $replace[] = "";
            $chars[] = "=\n";
            $replace[] = "";
            $body = str_replace($chars, $replace, $body);
        }

        if (preg_match('/urn:schemas-microsoft-com/ims', $body)) {
            // invalid html by microsoft
            $this->http->FilterHTML = false;
            // removing head can help
            $start = stripos($body, '<head>');
            $end = stripos($body, '</head>');

            if ($start && $end) {
                $body = substr($body, 0, $start) . substr($body, $end + 7);
            }
        }
        $this->http->SetBody($body);
        $emailType = $this->getEmailType();

        switch ($emailType) {
            case 'Statement':
                $props = [];
                // looking up account number
                $props["Number"] = $this->http->FindSingleNode("//span[contains(text(), 'Rapid Rewards #')]", null, true, "/\#(\d+)/");

                if (!$props["Number"]) {
                    preg_match("/Rapid Rewards \#(\d+)/", $parser->getPlainBody(), $matches);

                    if ($matches) {
                        $props["Number"] = $matches[1];
                    }
                }

                if (!$props["Number"]) {
                    $props["Number"] = $this->http->FindSingleNode("//td[contains(text()[2], '#')]/text()[2]", null, true, "/\#(\d+)/");
                }

                if ($props["Number"]) {
                    $props["Login"] = $props["Number"];
                }
                $props["Name"] = $this->http->FindSingleNode("//text()[contains(., 'Hello,')]", null, true, "/Hello, (.*)/");

                $props["Balance"] = $this->http->FindSingleNode("//td[contains(normalize-space(text()), 'Available Points')]/following-sibling::td[1]");

                if (!$props["Balance"]) {
                    $nodes = $this->http->XPath->query("//*[contains(normalize-space(text()), 'Available Points')]");

                    if ($nodes->length == 0) {
                        $result = 'Statement parsing error: no balance';

                        break;
                    }
                    $props["Balance"] = $this->http->FindSingleNode("following-sibling::td", $this->findParentNode($nodes->item(0)));
                }
                $props["TierFlights"] = $this->http->FindSingleNode("(//td[contains(normalize-space(text()), 'Qualifying Flights')])[1]/following-sibling::td");

                if (!$props["TierFlights"]) {
                    $nodes = $this->http->XPath->query("(//*[contains(normalize-space(text()), 'Qualifying Flights')])[1]");

                    if ($nodes->length > 0) {
                        $props["TierFlights"] = $this->http->FindSingleNode("following-sibling::td", $this->findParentNode($nodes->item(0)));
                    }
                }
                $props["TierPoints"] = $this->http->FindSingleNode("(//td[contains(normalize-space(text()), 'Qualifying Points')])[1]/following-sibling::td");

                if (!$props["TierPoints"]) {
                    $nodes = $this->http->XPath->query("(//*[contains(normalize-space(text()), 'Qualifying Points')])[1]");

                    if ($nodes->length > 0) {
                        $props["TierPoints"] = $this->http->FindSingleNode("following-sibling::td", $this->findParentNode($nodes->item(0)));
                    }
                }
                $props["CPFlights"] = $this->http->FindSingleNode("(//td[contains(normalize-space(text()), 'Qualifying Flights')])[2]/following-sibling::td");

                if (!$props["CPFlights"]) {
                    $nodes = $this->http->XPath->query("(//*[contains(normalize-space(text()), 'Qualifying Flights')])[1]");

                    if ($nodes->length > 0) {
                        $props["CPFlights"] = $this->http->FindSingleNode("following-sibling::td", $this->findParentNode($nodes->item(0)));
                    }
                }
                $props["CPPoints"] = $this->http->FindSingleNode("(//td[contains(normalize-space(text()), 'Qualifying Points')])[2]/following-sibling::td");

                if (!$props["CPPoints"]) {
                    $nodes = $this->http->XPath->query("(//*[contains(normalize-space(text()), 'Qualifying Points')])[1]");

                    if ($nodes->length > 0) {
                        $props["CPPoints"] = $this->http->FindSingleNode("following-sibling::td", $this->findParentNode($nodes->item(0)));
                    }
                }

                if (stripos($props['Balance'], 'pending') !== false) {
                    unset($props['Balance']);
                }
                $result = ["Properties" => $props];

                break;

            default:
                $result = 'Undefined email type';
        }

        return [
            'parsedData' => $result,
            'emailType'  => $emailType,
        ];
    }

    public function getEmailType()
    {
        if ($this->http->XPath->query("//td[contains(normalize-space(text()), 'Available Points:')]")->length > 0) {
            return 'Statement';
        }

        if ($this->http->XPath->query("//span[contains(normalize-space(text()), 'Available Points:')]")->length > 0) {
            return 'Statement';
        }

        if ($this->http->XPath->query("//*[contains(normalize-space(text()), 'Available Points:')]")->length > 0) {
            return 'Statement';
        }

        return 'Undefined';
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match("/southwest\.com$/", $headers['from'])
        || isset($headers['subject'])
        && (stripos($headers['subject'], 'Rapid Rewards') !== false
            || stripos($headers['subject'], 'Southwest Airlines') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, '@luv.southwest.com') !== false
        || stripos($body, 'Rapid rewards') !== false
        || stripos($body, 'Southwest Airlines') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "southwest.com") !== false;
    }

    /**
     * @param \DOMNode $node
     * @param string $nodeName
     * @param int $limit
     *
     * @return \DOMNode | null
     */
    private function findParentNode($node, $nodeName = "td", $limit = 5)
    {
        while (strtolower($node->nodeName) != $nodeName && $limit > 0) {
            $nodes = $this->http->XPath->query("parent::*", $node);

            if ($nodes->length == 0) {
                $limit = 0;
            } else {
                $node = $nodes->item(0);
            }
            $limit--;
        }

        if (strtolower($node->nodeName) != $nodeName) {
            return null;
        }

        return $node;
    }
}
