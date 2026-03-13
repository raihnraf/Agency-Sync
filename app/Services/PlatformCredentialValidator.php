<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlatformCredentialValidator
{
    private ?string $lastError = null;

    /**
     * Validate platform API credentials.
     *
     * @param string $platform Platform type (shopify, shopware)
     * @param array $credentials API credentials (api_key, api_secret)
     * @param string $url Platform URL
     * @return bool True if credentials are valid, false otherwise
     */
    public function validate(string $platform, array $credentials, string $url): bool
    {
        $this->lastError = null;

        try {
            $response = Http::timeout(10)->post(
                $this->getValidationEndpoint($platform, $url),
                $this->buildValidationPayload($platform, $credentials)
            );

            if ($response->successful()) {
                return true;
            }

            // Store error message from platform
            $this->lastError = $this->extractErrorMessage($response);

            return false;
        } catch (\Exception $e) {
            // Log warning but don't block on network errors
            Log::warning('Platform credential validation failed', [
                'platform' => $platform,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            $this->lastError = 'Unable to validate credentials: ' . $e->getMessage();

            return false;
        }
    }

    /**
     * Get the last error message from validation.
     *
     * @return string|null
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Get the validation endpoint for a platform.
     *
     * @param string $platform
     * @param string $url
     * @return string
     */
    private function getValidationEndpoint(string $platform, string $url): string
    {
        // Placeholder URLs for Phase 3
        // Real platform-specific validation will be implemented in Phase 6
        return match ($platform) {
            'shopify' => 'https://api.shopify.com/validate',
            'shopware' => 'https://api.shopware.com/validate',
            default => $url . '/api/validate',
        };
    }

    /**
     * Build validation payload for platform API.
     *
     * @param string $platform
     * @param array $credentials
     * @return array
     */
    private function buildValidationPayload(string $platform, array $credentials): array
    {
        return match ($platform) {
            'shopify' => [
                'api_key' => $credentials['api_key'] ?? '',
                'api_secret' => $credentials['api_secret'] ?? '',
            ],
            'shopware' => [
                'access-key' => $credentials['api_key'] ?? '',
                'secret' => $credentials['api_secret'] ?? '',
            ],
            default => $credentials,
        };
    }

    /**
     * Extract error message from platform response.
     *
     * @param \Illuminate\Http\Client\Response $response
     * @return string
     */
    private function extractErrorMessage($response): string
    {
        $data = $response->json();

        if (isset($data['error'])) {
            return is_string($data['error'])
                ? $data['error']
                : json_encode($data['error']);
        }

        if (isset($data['message'])) {
            return $data['message'];
        }

        return 'Invalid API credentials';
    }
}
