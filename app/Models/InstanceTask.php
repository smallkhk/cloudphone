<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cloud_instance_id', 'vmos_task_id', 'type', 'status', 'result'])]
class InstanceTask extends Model
{
    use HasFactory;

    public const TYPE_RESTART = 'restart';

    public const TYPE_RESET = 'reset';

    public const TYPE_SCREENSHOT = 'screenshot';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'result' => 'array',
        ];
    }

    public function cloudInstance(): BelongsTo
    {
        return $this->belongsTo(CloudInstance::class);
    }
}
