<?php

declare(strict_types=1);

namespace App\Observability;

use Sentry\Event;
use Sentry\EventHint;

/**
 * Strip sensitive payload from Sentry events before they leave the process.
 * Called as `before_send` hook — see config/packages/sentry.yaml.
 *
 * Scrubbing policy:
 *   - POST/PUT body fields matching /password|token|csrf|secret/i → `[filtered]`
 *   - Cookies stripped entirely
 *   - Authorization headers stripped entirely
 *   - Session ID not forwarded (set_data() omits it)
 */
final class SentryScrubber
{
    private const SENSITIVE_KEY_PATTERN = '/password|token|csrf|secret|api[_-]?key/i';

    public function __invoke(Event $event, ?EventHint $hint = null): Event
    {
        $request = $event->getRequest();
        if ($request !== []) {
            $request = $this->scrubRequestPayload($request);
            $event->setRequest($request);
        }

        $extra = $event->getExtra();
        if ($extra !== []) {
            $event->setExtra($this->scrubArray($extra));
        }

        return $event;
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function scrubRequestPayload(array $request): array
    {
        // Cookies: strip entirely — session id + CSRF tokens should never hit the ingest.
        if (isset($request['cookies'])) {
            $request['cookies'] = '[filtered]';
        }

        // Headers: remove Authorization / Cookie, keep useful ones (User-Agent, etc).
        if (isset($request['headers']) && \is_array($request['headers'])) {
            foreach ($request['headers'] as $name => $_) {
                if (preg_match('/^(authorization|cookie|x-csrf-token)$/i', (string) $name)) {
                    $request['headers'][$name] = '[filtered]';
                }
            }
        }

        // POST / form data: scrub keys matching sensitive pattern.
        if (isset($request['data']) && \is_array($request['data'])) {
            $request['data'] = $this->scrubArray($request['data']);
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function scrubArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (preg_match(self::SENSITIVE_KEY_PATTERN, (string) $key)) {
                $data[$key] = '[filtered]';

                continue;
            }
            if (\is_array($value)) {
                $data[$key] = $this->scrubArray($value);
            }
        }

        return $data;
    }
}
