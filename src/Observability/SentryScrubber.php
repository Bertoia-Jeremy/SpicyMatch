<?php

declare(strict_types=1);

namespace App\Observability;

use Sentry\Event;

final class SentryScrubber
{
    private const string SENSITIVE_KEY_PATTERN = '/password|token|csrf|secret|api[_-]?key/i';

    public function __invoke(Event $event): Event
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
     * @return array<string, mixed>
     */
    private function scrubRequestPayload(array $request): array
    {
        if (isset($request['cookies'])) {
            $request['cookies'] = '[filtered]';
        }

        if (isset($request['headers']) && \is_array($request['headers'])) {
            foreach ($request['headers'] as $name => $_) {
                if (preg_match('/^(authorization|cookie|x-csrf-token)$/i', (string) $name)) {
                    $request['headers'][$name] = '[filtered]';
                }
            }
        }

        if (isset($request['data']) && \is_array($request['data'])) {
            $request['data'] = $this->scrubArray($request['data']);
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $data
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
