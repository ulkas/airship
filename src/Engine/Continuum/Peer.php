<?php
declare(strict_types=1);
namespace Airship\Engine\Continuum;

use \Airship\Engine\State;
use \ParagonIE\Halite\Asymmetric\SignaturePublicKey;

/**
 * Class Peer
 *
 * Represents a peer for a given channel
 *
 * @package Airship\Engine\Continuum
 */
class Peer
{
    private $name;
    private $onion = false;
    private $publicKey;
    private $urls = [];

    public function __construct(array $config = [])
    {
        $this->name = $config['name'];
        $this->publicKey = new SignaturePublicKey(
            \Sodium\hex2bin($config['public_key'])
        );
        $this->urls = $config['urls'];
        foreach ($this->urls as $url) {
            if (\strpos($url, '.onion') !== false) {
                $this->onion = true;
                break;
            }
        }
    }

    /**
     * Does this domain have a .onion address?
     *
     * @return bool
     */
    public function hasOnionAddress(): bool
    {
        return $this->onion;
    }

    /**
     * Get all URLs
     *
     * @param bool $doNotShuffle
     * @return string[]
     */
    public function getAllURLs(bool $doNotShuffle = false): array
    {
        $state = State::instance();
        $candidates = [];
        if ($state->universal['tor-only']) {
            // Prioritize Tor Hidden Services
            $after = [];
            foreach ($this->urls as $url) {
                if (\strpos($url, '.onion') !== false) {
                    $candidates[] = $url;
                } else {
                    $after[] = $url;
                }
            }

            if (!$doNotShuffle) {
                \Airship\secure_shuffle($candidates);
                \Airship\secure_shuffle($after);
            }

            foreach ($after as $url) {
                $candidates[] = $url;
            }
        } else {
            $candidates = $this->urls;
            if (!$doNotShuffle) {
                \Airship\secure_shuffle($candidates);
            }
        }
        return $candidates;
    }

    /**
     * @return SignaturePublicKey
     */
    public function getPublicKey(): SignaturePublicKey
    {
        return $this->publicKey;
    }
}
