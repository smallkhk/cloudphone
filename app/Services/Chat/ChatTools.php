<?php

namespace App\Services\Chat;

use App\Models\CloudInstance;
use App\Models\InstanceTask;
use App\Models\User;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Real actions the chat assistant is allowed to take on a signed-in
 * customer's own cloud phones, and their provider-neutral tool definitions.
 *
 * Deliberately narrow: only reversible, low-risk actions (restart, screenshot)
 * are exposed here. Destructive ones — factory reset, "one-key new device"
 * (wipes the identity), proxy/app changes, ADB — are NOT chat-actionable, on
 * purpose. A customer who wants those still uses the device control panel
 * directly, where they see an explicit confirmation dialog.
 */
class ChatTools
{
    /** @return list<array{name: string, description: string, parameters: array}> */
    public function definitions(): array
    {
        return [
            [
                'name' => 'restart_cloud_phone',
                'description' => "Restarts one of the signed-in customer's own cloud phones. Safe and reversible — "
                    .'use it when a device is reported stuck, offline or unresponsive, or the customer explicitly '
                    .'asks for a restart. Only use a pad_code already listed under "Their cloud phones" above.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'pad_code' => ['type' => 'string', 'description' => "The device's pad code."],
                    ],
                    'required' => ['pad_code'],
                ],
            ],
            [
                'name' => 'take_screenshot',
                'description' => "Takes a fresh screenshot of one of the signed-in customer's own cloud phones so "
                    .'you can describe its current screen to them, or confirm it\'s working. Read-only, no side effects.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'pad_code' => ['type' => 'string', 'description' => "The device's pad code."],
                    ],
                    'required' => ['pad_code'],
                ],
            ],
        ];
    }

    /** Runs a tool call and returns the text to feed back to the model. Never throws. */
    public function execute(?User $user, string $name, array $arguments): string
    {
        if (! $user) {
            return 'Error: no signed-in customer to act on behalf of. Tell them to log in first.';
        }

        $padCode = trim((string) ($arguments['pad_code'] ?? ''));

        if ($padCode === '') {
            return 'Error: pad_code is required.';
        }

        $instance = CloudInstance::where('user_id', $user->id)->where('pad_code', $padCode)->first();

        if (! $instance) {
            return "Error: no cloud phone with pad code '{$padCode}' belongs to this customer. ".
                'Only act on a pad_code already listed for this customer.';
        }

        return match ($name) {
            'restart_cloud_phone' => $this->restart($user, $instance),
            'take_screenshot' => $this->screenshot($user, $instance),
            default => "Error: unknown tool '{$name}'.",
        };
    }

    protected function restart(User $user, CloudInstance $instance): string
    {
        try {
            $response = app(VmosCloudPhoneService::class)->restart([$instance->pad_code]);
            $entry = $response['data'][0] ?? [];

            $instance->tasks()->create([
                'vmos_task_id' => $entry['taskId'] ?? null,
                'type' => InstanceTask::TYPE_RESTART,
                'status' => InstanceTask::STATUS_PENDING,
                'result' => $entry,
            ]);

            Log::info('chat.tool.restart', ['user_id' => $user->id, 'instance_id' => $instance->id, 'pad_code' => $instance->pad_code]);

            return "Restart requested for {$instance->pad_code}. It typically comes back online within a minute or two.";
        } catch (Throwable $e) {
            Log::error('chat.tool.restart_failed', ['user_id' => $user->id, 'instance_id' => $instance->id, 'error' => $e->getMessage()]);

            return "Error: could not restart the device right now ({$e->getMessage()}). ".
                'Tell the customer to try again from My cloud phones, or offer to pass it to a human.';
        }
    }

    protected function screenshot(User $user, CloudInstance $instance): string
    {
        try {
            $response = app(VmosCloudPhoneService::class)->screenshot([$instance->pad_code]);
            $url = $response['data'][0]['url'] ?? null;

            if (! $url) {
                return 'Error: did not get a screenshot back right now — the device may be offline or still provisioning.';
            }

            $instance->update(['screenshot_url' => $url]);

            Log::info('chat.tool.screenshot', ['user_id' => $user->id, 'instance_id' => $instance->id]);

            return "Screenshot refreshed for {$instance->pad_code}. URL: {$url}";
        } catch (Throwable $e) {
            Log::error('chat.tool.screenshot_failed', ['user_id' => $user->id, 'instance_id' => $instance->id, 'error' => $e->getMessage()]);

            return "Error: could not take a screenshot right now ({$e->getMessage()}).";
        }
    }
}
