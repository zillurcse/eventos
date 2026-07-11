<?php

namespace App\Support\Agora;

/**
 * Agora AccessToken2 ("007") builder for the RTC service.
 *
 * Agora's Web SDK will not join a channel without a token signed by the
 * project's App Certificate, and the certificate must never reach the browser —
 * so the API mints a short-lived, per-viewer token here. The privileges encode
 * the role: a session host may publish audio/video, an attendee may only join
 * and subscribe.
 *
 * Wire format (matches Agora's reference implementation):
 *
 *   token   = "007" + base64( deflate( packString(signature) + payload ) )
 *   payload = packString(appId) + uint32(issueTs) + uint32(expire)
 *             + uint32(salt) + uint16(serviceCount) + services…
 *   signing = HMAC(salt, HMAC(issueTs, appCertificate))
 *   signature = HMAC(payload, signing)
 *
 * All integers are little-endian; a "string" is a uint16 length followed by the
 * raw bytes.
 */
class AccessToken2
{
    public const VERSION = '007';

    /** RTC service. */
    public const SERVICE_RTC = 1;

    public const PRIVILEGE_JOIN_CHANNEL = 1;

    public const PRIVILEGE_PUBLISH_AUDIO = 2;

    public const PRIVILEGE_PUBLISH_VIDEO = 3;

    public const PRIVILEGE_PUBLISH_DATA = 4;

    private int $issueTs;

    private int $salt;

    /** @var array<int,int> privilege => unix expiry */
    private array $privileges = [];

    public function __construct(
        private string $appId,
        private string $appCertificate,
        private string $channelName,
        private string $uid,
        private int $expire,
    ) {
        $this->issueTs = time();
        $this->salt = random_int(1, 99999999);
    }

    /** Grant a privilege until $ttl seconds from now. */
    public function grant(int $privilege, int $ttl): self
    {
        $this->privileges[$privilege] = $this->issueTs + $ttl;

        return $this;
    }

    /** An App ID / certificate is a 32-char hex string; anything else is a typo. */
    public static function looksValid(?string $v): bool
    {
        return is_string($v) && preg_match('/^[0-9a-fA-F]{32}$/', $v) === 1;
    }

    public function build(): ?string
    {
        if (! self::looksValid($this->appId) || ! self::looksValid($this->appCertificate)) {
            return null;
        }

        // Two-step HMAC chain seeded from the certificate.
        $signing = hash_hmac('sha256', self::uint32($this->issueTs), $this->appCertificate, true);
        $signing = hash_hmac('sha256', self::uint32($this->salt), $signing, true);

        $payload = self::str($this->appId)
            .self::uint32($this->issueTs)
            .self::uint32($this->expire)
            .self::uint32($this->salt)
            .self::uint16(1)              // one service: RTC
            .$this->packRtcService();

        $signature = hash_hmac('sha256', $payload, $signing, true);

        $compressed = zlib_encode(self::str($signature).$payload, ZLIB_ENCODING_DEFLATE);
        if ($compressed === false) {
            return null;
        }

        return self::VERSION.base64_encode($compressed);
    }

    private function packRtcService(): string
    {
        ksort($this->privileges);

        $map = self::uint16(count($this->privileges));
        foreach ($this->privileges as $key => $expiry) {
            $map .= self::uint16($key).self::uint32($expiry);
        }

        return self::uint16(self::SERVICE_RTC)
            .$map
            .self::str($this->channelName)
            .self::str($this->uid);
    }

    private static function uint16(int $v): string
    {
        return pack('v', $v);
    }

    private static function uint32(int $v): string
    {
        return pack('V', $v);
    }

    private static function str(string $v): string
    {
        return self::uint16(strlen($v)).$v;
    }
}
