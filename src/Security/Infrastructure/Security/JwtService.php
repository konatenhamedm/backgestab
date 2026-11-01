<?php

namespace App\Security\Infrastructure\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class JwtService
{
    private string $secret;
    private int $ttl;
    private CacheItemPoolInterface $cache;

    public function __construct(ParameterBagInterface $params, ?CacheItemPoolInterface $cache = null)
    {
        $rawSecret = $params->get('jwt.secret');
        if (empty($rawSecret)) {
            throw new \RuntimeException('JWT secret must be configured');
        }

        // Firebase JWT HS256 strictly requires at least 256 bits (32 bytes)
        $this->secret = strlen($rawSecret) < 32 ? hash('sha256', $rawSecret) : $rawSecret;
        $this->ttl = $params->get('jwt.ttl');
        $this->cache = $cache ?? new FilesystemAdapter();
    }

    public function generateToken(array $payload): string
    {
        $payload = array_merge([
            'iat' => time(),
            'exp' => time() + $this->ttl,
            'iss' => 'GESTAD',
            'jti' => bin2hex(random_bytes(16)) // JWT ID unique
        ], $payload);

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function validateToken(string $token): ?array
    {
        try {
            if ($this->isTokenBlacklisted($token)) {
                return null;
            }

            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            return (array) $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function invalidateToken(string $token): bool
    {
        try {
            $payload = $this->decodeTokenWithoutValidation($token);

            if (!$payload) {
                return false;
            }

            $jti = $payload['jti'] ?? $this->generateTokenId($token);
            $expiration = $payload['exp'] ?? null;

            if ($expiration) {
                $timeToLive = $expiration - time();

                if ($timeToLive <= 0) {
                    return true;
                }
                $cacheKey = $this->getBlacklistCacheKey($jti);
                $cacheItem = $this->cache->getItem($cacheKey);
                $cacheItem->set([
                    'token' => $token,
                    'invalidated_at' => time(),
                    'expires_at' => $expiration,
                    'jti' => $jti
                ]);
                $cacheItem->expiresAfter($timeToLive);

                return $this->cache->save($cacheItem);
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function isTokenBlacklisted(string $token): bool
    {
        try {
            $payload = $this->decodeTokenWithoutValidation($token);

            if (!$payload) {
                return true; 
            }

            $jti = $payload['jti'] ?? $this->generateTokenId($token);
            $cacheKey = $this->getBlacklistCacheKey($jti);

            return $this->cache->hasItem($cacheKey);
        } catch (\Exception $e) {
            return true;
        }
    }

    private function decodeTokenWithoutValidation(string $token): ?array
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }

            $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
            return json_decode($payload, true);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function generateTokenId(string $token): string
    {
        return hash('sha256', $token);
    }

    private function getBlacklistCacheKey(string $jti): string
    {
        return 'jwt_blacklist_' . $jti;
    }

    public function getBlacklistedTokenInfo(string $token): ?array
    {
        try {
            $payload = $this->decodeTokenWithoutValidation($token);

            if (!$payload) {
                return null;
            }

            $jti = $payload['jti'] ?? $this->generateTokenId($token);
            $cacheKey = $this->getBlacklistCacheKey($jti);

            if ($this->cache->hasItem($cacheKey)) {
                return $this->cache->getItem($cacheKey)->get();
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function clearBlacklist(): bool
    {
        return $this->cache->clear();
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }
}
