<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Nelmio\ApiDocBundle\Render\RenderOpenApi;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The committed OpenAPI schema is the API as the code describes it today. The UI's TypeScript types are generated
 * from that file, so an endpoint, field or DTO that changes without it leaves the frontend typed against an API
 * that no longer exists. When the change is meant, regenerate both:
 *
 *   docker compose exec php php bin/console nelmio:apidoc:dump --format=json > backend/assets/types/openapi.json
 *   docker compose exec node npm run api:types
 */
final class OpenApiSnapshotTest extends KernelTestCase
{
    private const SNAPSHOT = __DIR__.'/../../assets/types/openapi.json';

    public function testTheCommittedSchemaIsTheApiTheCodeDescribes(): void
    {
        self::assertJsonStringEqualsJsonFile(
            self::SNAPSHOT,
            static::getContainer()->get('nelmio_api_doc.render_docs')->render(RenderOpenApi::JSON, 'default', ['no-pretty' => false]),
            'The API changed but assets/types/openapi.json did not: regenerate it and the TypeScript types (see this test\'s docblock).',
        );
    }
}
