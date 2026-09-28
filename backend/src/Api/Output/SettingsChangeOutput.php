<?php

declare(strict_types=1);

namespace App\Api\Output;

/** One save of Configuración (Historial). */
final readonly class SettingsChangeOutput
{
    public function __construct(
        public string $id,
        /** ISO 8601. */
        public string $changedAt,
        public PersonOutput $changedBy,
        /** @var list<SettingChangeOutput> */
        public array $changes,
    ) {
    }
}
