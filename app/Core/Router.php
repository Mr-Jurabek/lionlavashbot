<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Telegram update'ni handlerga yo'naltiradi.
 * Xabarlar tartibi: buyruq → matn tugmasi → state → fallback.
 * Callback'lar: "prefix:arg1:arg2" → handler($ctx, 'arg1', 'arg2').
 */
final class Router
{
    /** @var array<string, callable> */
    private array $commands = [];
    /** @var array<string, callable> */
    private array $callbacks = [];
    /** @var list<array{callable, callable}> */
    private array $texts = [];
    /** @var array<string, callable> */
    private array $states = [];
    /** @var callable|null */
    private $fallback = null;
    /** @var callable|null */
    private $callbackFallback = null;

    public function command(string $command, callable $handler): self
    {
        $this->commands[ltrim($command, '/')] = $handler;
        return $this;
    }

    public function callback(string $prefix, callable $handler): self
    {
        $this->callbacks[$prefix] = $handler;
        return $this;
    }

    /** @param callable(string): bool $matcher */
    public function text(callable $matcher, callable $handler): self
    {
        $this->texts[] = [$matcher, $handler];
        return $this;
    }

    public function state(string $state, callable $handler): self
    {
        $this->states[$state] = $handler;
        return $this;
    }

    public function fallback(callable $handler): self
    {
        $this->fallback = $handler;
        return $this;
    }

    public function callbackFallback(callable $handler): self
    {
        $this->callbackFallback = $handler;
        return $this;
    }

    public function dispatch(array $update, ?string $state, mixed $ctx): void
    {
        if (isset($update['callback_query'])) {
            $parts = explode(':', (string) ($update['callback_query']['data'] ?? ''));
            $handler = $this->callbacks[$parts[0]] ?? $this->callbackFallback;
            if ($handler !== null) {
                $handler($ctx, ...array_slice($parts, 1));
            }
            return;
        }

        $message = $update['message'] ?? null;
        if ($message === null) {
            return;
        }
        $text = (string) ($message['text'] ?? '');

        if (str_starts_with($text, '/')) {
            $command = strtolower(explode('@', explode(' ', substr($text, 1), 2)[0])[0]);
            if (isset($this->commands[$command])) {
                ($this->commands[$command])($ctx);
                return;
            }
        }
        if ($text !== '') {
            foreach ($this->texts as [$matcher, $handler]) {
                if ($matcher($text)) {
                    $handler($ctx);
                    return;
                }
            }
        }
        if ($state !== null && isset($this->states[$state])) {
            ($this->states[$state])($ctx);
            return;
        }
        if ($this->fallback !== null) {
            ($this->fallback)($ctx);
        }
    }
}
