<?php


namespace AwardWallet\Common\Parsing\MitmProxy;


class Port
{

    public const EXTENSIONS_IMAGES_VIDEOS_AND_FONTS = ['jpg', 'png', 'jpeg', 'svg', 'gif', 'mp3', 'mp4', 'avi', 'woff2', 'ttf', 'ico', 'webp', 'webm', 'woff', 'eot', 'otf'];

    /**
     * @var string[]
     */
    private $cacheUrls = [];
    /**
     * @var string[]
     */
    private $banUrls = [];
    /**
     * @var string[]
     */
    private array $externalProxies = [];

    /**
     * @param string[] $extProxies example: ['1.1.1.2:3128', 'my_username:my_password@some.host.com:8080']
     */
    public function setExternalProxies(array $proxies): self
    {
        $this->externalProxies = $proxies;

        return $this;
    }

    public static function regexpFromExtensions(array $extensions): string
    {
        return "\\.(" . implode("|", $extensions) . ")($|\?)";
    }

    public static function allStaticRegexp() : string
    {
        return Port::regexpFromExtensions(array_merge(Port::EXTENSIONS_IMAGES_VIDEOS_AND_FONTS, ['js', 'css']));
    }

    public function banUrls(string $regexp): self
    {
        $this->banUrls[] = $regexp;

        return $this;
    }

    public function cacheUrls(string $regexp): self
    {
        $this->cacheUrls[] = $regexp;

        return $this;
    }

    public function getCacheUrls() : array
    {
        return $this->cacheUrls;
    }

    public function getBanUrls() : array
    {
        return $this->banUrls;
    }

    public function getExternalProxies() : array
    {
        return $this->externalProxies;
    }

}