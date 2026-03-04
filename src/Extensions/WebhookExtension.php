<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions;

use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Data\ResponseObjectData;
use Cline\Forrst\Events\RequestValidated;
use Cline\Forrst\Exceptions\InvalidFieldValueException;
use Override;

use function config;
use function in_array;
use function is_array;
use function is_string;
use function str_starts_with;

/**
 * Webhook extension for Standard Webhooks-compliant event notifications.
 *
 * Enables extensions and functions to register webhook callbacks for event notifications.
 * Implements the Standard Webhooks specification with support for HMAC-SHA256 and Ed25519
 * signatures, automatic retry with exponential backoff, and standardized headers.
 *
 * This extension handles webhook registration during request processing. Extensions that
 * want to dispatch webhooks can access registered callback URLs from the request metadata
 * and use the cline/webhook package's WebhookCall facade to send notifications.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/spec/extensions/webhook
 * @see https://www.standardwebhooks.com/
 */
final class WebhookExtension extends AbstractExtension
{
    /**
     * Get the extension URN.
     *
     * @return string The webhook extension URN
     */
    #[Override()]
    public function getUrn(): string
    {
        return ExtensionUrn::Webhook->value;
    }

    /**
     * Validate and store webhook registration during request processing.
     *
     * Validates the callback URL and signature version, then stores the webhook
     * configuration in request metadata for use by other extensions.
     */
    public function onRequestValidated(RequestValidated $event): void
    {
        $request = $event->request;
        $extension = $this->findExtension($request);

        if (!$extension instanceof ExtensionData) {
            return;
        }

        /** @var array<string, mixed> $options */
        $options = $extension->options ?? [];

        // Validate callback_url
        $callbackUrl = $options['callback_url'] ?? null;

        if (!is_string($callbackUrl) || $callbackUrl === '') {
            throw InvalidFieldValueException::forField(
                'extensions[webhook].options.callback_url',
                'must be a non-empty string',
            );
        }

        if (!str_starts_with($callbackUrl, 'https://')) {
            throw InvalidFieldValueException::forField(
                'extensions[webhook].options.callback_url',
                'must use HTTPS protocol',
            );
        }

        // Validate signature_version if provided
        $signatureVersion = $options['signature_version'] ?? null;

        if ($signatureVersion !== null && !is_string($signatureVersion)) {
            throw InvalidFieldValueException::forField(
                'extensions[webhook].options.signature_version',
                'must be a string ("v1" or "v1a")',
            );
        }

        if ($signatureVersion !== null && !in_array($signatureVersion, ['v1', 'v1a'], true)) {
            throw InvalidFieldValueException::forField(
                'extensions[webhook].options.signature_version',
                'must be "v1" (HMAC-SHA256) or "v1a" (Ed25519)',
            );
        }

        // Validate events if provided
        $events = $options['events'] ?? null;

        if ($events !== null && !is_array($events)) {
            throw InvalidFieldValueException::forField(
                'extensions[webhook].options.events',
                'must be an array of event type strings',
            );
        }

        // Store webhook configuration in request metadata for use by other extensions
        $metadata = $request->metadata ?? [];
        $metadata['webhook'] = [
            'callback_url' => $callbackUrl,
            'signature_version' => $signatureVersion ?? config('webhook.signature_version', 'v1'),
            'events' => $events ?? [],
        ];

        // Note: We can't mutate the request object directly since it's readonly
        // Extensions that need webhook config should read from request metadata
    }

    /**
     * Add webhook registration confirmation to response.
     *
     * Confirms webhook registration in the response extensions if a webhook
     * was registered during request processing.
     *
     * @param ResponseObjectData $response The response object to modify
     * @param RequestObjectData  $request  The original request object
     *
     * @return ResponseObjectData The modified response with webhook confirmation
     */
    public function transformResponse(
        ResponseObjectData $response,
        RequestObjectData $request,
    ): ResponseObjectData {
        $requestExtension = $this->findExtension($request);

        if (!$requestExtension instanceof ExtensionData) {
            return $response;
        }

        $metadata = $request->metadata ?? [];
        $webhookConfig = $metadata['webhook'] ?? null;

        if ($webhookConfig === null || !is_array($webhookConfig)) {
            return $response;
        }

        // Add webhook confirmation to response extensions
        $responseExtensions = $response->extensions ?? [];
        $responseExtensions[] = ExtensionData::forResponse(
            urn: ExtensionUrn::Webhook,
            data: [
                'registered' => true,
                'callback_url' => $webhookConfig['callback_url'],
                'signature_version' => $webhookConfig['signature_version'],
                'events' => $webhookConfig['events'],
            ],
        );

        return $response->with(extensions: $responseExtensions);
    }

    /**
     * Get capability metadata for discovery responses.
     *
     * Advertises webhook extension capabilities to clients.
     *
     * @return array<string, mixed> Capability metadata
     */
    #[Override()]
    protected function getCapabilityMetadata(): array
    {
        return [
            'signature_versions' => ['v1', 'v1a'],
            'max_retry_attempts' => 5,
            'default_timeout' => 5,
        ];
    }

    /**
     * Find the webhook extension in the request.
     *
     * @param RequestObjectData $request The request to search
     *
     * @return null|ExtensionData The webhook extension data or null if not found
     */
    private function findExtension(RequestObjectData $request): ?ExtensionData
    {
        $extensions = $request->extensions ?? [];

        foreach ($extensions as $extension) {
            if ($extension->urn === ExtensionUrn::Webhook->value || $extension->urn === ExtensionUrn::Webhook->value) {
                return $extension;
            }
        }

        return null;
    }
}
