<?php

declare(strict_types=1);

namespace App\Bots\Customer;

use App\Core\I18n;
use App\Core\State;
use App\Core\Telegram;
use App\Models\User;

/**
 * Bitta update'ni qayta ishlash konteksti: foydalanuvchi, til, javob yuborish yordamchilari.
 */
final class Context
{
    private bool $answered = false;

    /** @param array<string, mixed> $user */
    public function __construct(
        public readonly Telegram $tg,
        public readonly array $update,
        public array $user,
        private readonly State $state = new State('users'),
    ) {
    }

    public function userId(): int
    {
        return (int) $this->user['id'];
    }

    public function lang(): string
    {
        return I18n::normalize($this->user['lang'] ?? null);
    }

    public function t(string $key, array $params = []): string
    {
        return I18n::t($this->lang(), $key, $params);
    }

    public function money(int $amount): string
    {
        return I18n::money($this->lang(), $amount);
    }

    /** Lokalizatsiya qilingan nom: name_uz / name_ru. */
    public function localized(array $row, string $field): string
    {
        return (string) ($row[$field . '_' . $this->lang()] ?? $row[$field . '_uz'] ?? '');
    }

    public function message(): ?array
    {
        return $this->update['message'] ?? null;
    }

    public function callback(): ?array
    {
        return $this->update['callback_query'] ?? null;
    }

    public function text(): string
    {
        return trim((string) ($this->message()['text'] ?? ''));
    }

    public function chatId(): int
    {
        return (int) ($this->message()['chat']['id'] ?? $this->callback()['message']['chat']['id'] ?? $this->user['telegram_id']);
    }

    // --- Holat ---

    public function state(): ?string
    {
        return $this->user['state'] ?? null;
    }

    public function stateData(): array
    {
        return State::decode($this->user['state_data'] ?? null);
    }

    public function setState(string $state, array $data = []): void
    {
        $this->state->set($this->userId(), $state, $data);
        $this->user['state'] = $state;
        $this->user['state_data'] = $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;
    }

    public function mergeState(string $state, array $data): void
    {
        $this->setState($state, array_merge($this->stateData(), $data));
    }

    public function refreshUser(): void
    {
        $this->user = User::find($this->userId()) ?? $this->user;
    }

    // --- Javoblar ---

    public function reply(string $text, ?array $markup = null): mixed
    {
        return $this->tg->sendMessage($this->chatId(), $text, $markup);
    }

    /** Callback "soat" belgisini yopadi (bir martagina). */
    public function answer(?string $text = null, bool $alert = false): void
    {
        $callback = $this->callback();
        if ($callback === null || $this->answered) {
            return;
        }
        $this->answered = true;
        $params = ['callback_query_id' => $callback['id']];
        if ($text !== null) {
            $params['text'] = $text;
            $params['show_alert'] = $alert;
        }
        $this->tg->call('answerCallbackQuery', $params);
    }

    /**
     * Inline ekranni ko'rsatadi: callback bo'lsa — joriy xabarni tahrirlaydi (chat to'lib ketmasin),
     * xabar turi o'zgarsa (matn ↔ rasm) — o'chirib, yangisini yuboradi.
     */
    public function screen(string $text, ?array $markup, ?string $photo = null): void
    {
        $current = $this->callback()['message'] ?? null;
        if ($current !== null) {
            $base = ['chat_id' => $this->chatId(), 'message_id' => $current['message_id'], 'parse_mode' => 'HTML'];
            if ($markup !== null) {
                $base['reply_markup'] = $markup;
            }
            $hasPhoto = isset($current['photo']);
            if (!$hasPhoto && $photo === null) {
                $ok = $this->tg->call('editMessageText', $base + [
                    'text' => $text,
                    'link_preview_options' => ['is_disabled' => true],
                ]);
                if ($ok !== null) {
                    return;
                }
            } elseif ($hasPhoto && $photo !== null) {
                if ($this->tg->call('editMessageCaption', $base + ['caption' => $text]) !== null) {
                    return;
                }
            }
            $this->tg->call('deleteMessage', ['chat_id' => $this->chatId(), 'message_id' => $current['message_id']]);
        }

        if ($photo !== null) {
            $sent = $this->tg->call('sendPhoto', array_filter([
                'chat_id' => $this->chatId(),
                'photo' => $photo,
                'caption' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => $markup,
            ], static fn($v) => $v !== null));
            if ($sent !== null) {
                return;
            }
        }
        $this->reply($text, $markup);
    }
}
