<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Account;
use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Ajustes › Medios: images the consultant's pages show, resized to WebP, within the consultant's limits, public at
 * their consultant's address.
 */
final class MediaTest extends ApiTestCase
{
    private Account $account;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = $this->createAccount();
        $this->owner = $this->createOwner($this->account);
    }

    public function testAnImageIsStoredWithItsWebpCopies(): void
    {
        $this->actAs($this->owner);

        $image = $this->upload('/api/admin/media', ['altText' => 'Andrés en su oficina'], ['file' => $this->imageFile(1200, 800, 'oficina.jpg')]);

        self::assertSame(201, $this->responseStatus());
        self::assertSame(['oficina.jpg', 1200, 800, 'Andrés en su oficina', true], [$image['originalName'], $image['width'], $image['height'], $image['altText'], $image['active']]);
        self::assertSame('/finanzas-claras/media/'.$image['id'].'-480.webp', $image['thumbUrl']);
        self::assertSame('/finanzas-claras/media/'.$image['id'].'-1200.webp', $image['url'], 'never wider than the original');
        self::assertGreaterThan(0, $image['sizeBytes']);
        self::assertSame($image, $this->api('GET', '/api/admin/media/'.$image['id']));

        $this->signOut();
        $this->client->request('GET', $image['thumbUrl']);
        self::assertSame(200, $this->responseStatus());
        self::assertSame('image/webp', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('immutable', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $this->client->request('GET', '/finanzas-claras/media/'.$image['id'].'-1600.webp');
        self::assertSame(404, $this->responseStatus(), 'a width it does not have');
        $this->client->request('GET', '/otro-asesor/media/'.$image['id'].'-480.webp');
        self::assertSame(404, $this->responseStatus(), 'only at its own consultant\'s address');
    }

    public function testOnlyImagesAreAcceptedWhateverTheirName(): void
    {
        $this->actAs($this->owner);
        $path = (string) tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($path, 'no soy una imagen');

        $error = $this->upload('/api/admin/media', [], ['file' => new UploadedFile($path, 'foto.jpg', 'image/jpeg', null, true)]);
        self::assertSame(422, $this->responseStatus());
        self::assertSame('file', $error['violations'][0]['field']);

        $this->upload('/api/admin/media', [], []);
        self::assertSame(422, $this->responseStatus(), 'no file');
    }

    public function testTheStorageLimitIsKept(): void
    {
        $this->save($this->account->setLimits(10, 3, 0, 10));
        $this->actAs($this->owner);

        $error = $this->upload('/api/admin/media', [], ['file' => $this->imageFile()]);

        self::assertSame(409, $this->responseStatus());
        self::assertSame('storage_limit_reached', $error['error']);
        self::assertSame(0, $this->api('GET', '/api/admin/media')['total'], 'nothing kept');
    }

    public function testAnImageIsDescribedDisabledAndEnabled(): void
    {
        $this->actAs($this->owner);
        $image = $this->upload('/api/admin/media', [], ['file' => $this->imageFile()]);

        self::assertSame('Portada', $this->api('PATCH', '/api/admin/media/'.$image['id'], ['altText' => ' Portada '])['altText']);
        self::assertFalse($this->api('DELETE', '/api/admin/media/'.$image['id'])['active']);
        self::assertSame(0, $this->api('GET', '/api/admin/media')['total'], 'the library offers active images');
        self::assertSame(1, $this->api('GET', '/api/admin/media?includeInactive=1')['total']);
        self::assertTrue($this->api('POST', '/api/admin/media/'.$image['id'].'/enable')['active']);
    }

    public function testAPageShowsOnlyActiveImagesOfItsOwnLibrary(): void
    {
        $page = $this->createPage($this->account, published: false);
        $this->actAs($this->owner);
        $image = $this->upload('/api/admin/media', ['altText' => 'Retrato'], ['file' => $this->imageFile()]);
        $draft = $page->getDraft();
        $draft['sections'][0]['fields']['image'] = $image['id'];
        $draft['seo']['imageId'] = $image['id'];

        $this->api('PATCH', '/api/admin/pages/'.$page->getId(), ['draft' => $draft]);
        self::assertSame(200, $this->responseStatus());
        $this->api('POST', '/api/admin/pages/'.$page->getId().'/publish');
        $this->signOut();
        $crawler = $this->client->request('GET', '/finanzas-claras/diagnostico');
        $img = $crawler->filter('.hero img');
        self::assertSame('Retrato', $img->attr('alt'));
        self::assertStringContainsString('-480.webp 480w', (string) $img->attr('srcset'));
        self::assertSame('high', $img->attr('fetchpriority'), 'the first image loads first');
        self::assertStringEndsWith('-1200.webp', (string) $crawler->filter('meta[property="og:image"]')->attr('content'));

        $this->actAs($this->owner);
        $this->api('DELETE', '/api/admin/media/'.$image['id']);
        $this->api('PATCH', '/api/admin/pages/'.$page->getId(), ['draft' => $draft]);
        self::assertSame(422, $this->responseStatus(), 'a disabled image is not offered again');
    }

    public function testAnotherConsultantsImagesAreNotFound(): void
    {
        $theirs = $this->createOwner($this->createAccount('Plata Sana'), 'otro@demo.test');
        $this->actAs($theirs);
        $image = $this->upload('/api/admin/media', [], ['file' => $this->imageFile()]);
        $this->actAs($this->owner);

        $this->api('PATCH', '/api/admin/media/'.$image['id'], ['altText' => 'x']);
        self::assertSame(404, $this->responseStatus());
        $this->api('GET', '/api/admin/media/'.$image['id']);
        self::assertSame(404, $this->responseStatus());
        $this->api('DELETE', '/api/admin/media/'.$image['id']);
        self::assertSame(404, $this->responseStatus());
        self::assertSame(0, $this->api('GET', '/api/admin/media')['total']);
    }
}
