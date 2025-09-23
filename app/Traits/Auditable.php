<?php

namespace App\Traits;

use App\Models\Audit;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->storeAudit('created', [], $model->getAuditNewValues());
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();

            if (empty($changes)) {
                return;
            }

            $oldValues = [];
            $newValues = [];

            foreach (array_keys($changes) as $attribute) {
                $oldValues[$attribute] = $model->getOriginal($attribute);
                $newValues[$attribute] = $model->getAttribute($attribute);
            }

            $model->storeAudit('updated', $oldValues, $newValues);
        });

        static::deleted(function ($model) {
            $model->storeAudit('deleted', $model->getAuditOldValues(), []);
        });
    }

    protected function storeAudit(string $event, array $oldValues, array $newValues): void
    {
        if (empty($oldValues) && empty($newValues)) {
            return;
        }

        Audit::create([
            'auditable_type' => static::class,
            'auditable_id' => (string) $this->getKey(),
            'user_id' => Auth::id(),
            'event' => $event,
            'old_values' => $this->prepareAuditValues($oldValues),
            'new_values' => $this->prepareAuditValues($newValues),
        ]);
    }

    protected function prepareAuditValues(array $values): array
    {
        $prepared = [];

        foreach ($values as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $prepared[$key] = $value->format(DateTimeInterface::ATOM);
            } else {
                $prepared[$key] = $value;
            }
        }

        return $prepared;
    }

    protected function getAuditNewValues(): array
    {
        return $this->prepareAuditValues($this->getAttributes());
    }

    protected function getAuditOldValues(): array
    {
        return $this->prepareAuditValues($this->getOriginal());
    }
}
