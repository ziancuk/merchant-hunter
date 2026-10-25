<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Exceptions\BusinessRuleException;
use App\Models\Prospect;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Drafts a WhatsApp message through the Anthropic Messages API (D-60 exception: user-triggered). */
final class AiMessageService
{
    public function enabled(): bool
    {
        return filled(config('hunter.ai.key'));
    }

    public function generate(Prospect $prospect, string $senderName, string $tone = 'ramah'): string
    {
        $prospect->loadMissing('category');
        $style = __($tone === 'formal' ? 'api.ai.style_formal' : 'api.ai.style_friendly');

        $prompt = __('api.ai.prompt', ['style' => $style, 'sender' => $senderName])."\n\n"
            .__('api.ai.prompt_data', [
                'name' => json_encode((string) $prospect->name, JSON_UNESCAPED_UNICODE),
                'category' => json_encode((string) ($prospect->category?->name ?? ''), JSON_UNESCAPED_UNICODE),
            ]);

        try {
            $response = Http::withHeaders([
                'x-api-key' => (string) config('hunter.ai.key'),
                'anthropic-version' => '2023-06-01',
            ])->acceptJson()->timeout(20)->post('https://api.anthropic.com/v1/messages', [
                'model' => (string) config('hunter.ai.model'),
                'max_tokens' => 400,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);
        } catch (Throwable) {
            throw new BusinessRuleException(__('api.ai.unreachable'), 'ai_unavailable', 502);
        }

        if (! $response->successful()) {
            throw new BusinessRuleException(__('api.ai.error'), 'ai_unavailable', 502);
        }

        $text = trim((string) ($response->json('content.0.text') ?? ''));
        if ($text === '') {
            throw new BusinessRuleException(__('api.ai.empty'), 'ai_empty', 502);
        }

        return mb_substr($text, 0, 1000);
    }
}
