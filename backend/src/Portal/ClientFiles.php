<?php

declare(strict_types=1);

namespace App\Portal;

use App\Api\ApiException;
use App\Api\ApiValidationException;
use App\Entity\Account;
use App\Entity\ClientFile;
use App\Entity\Contact;
use App\Entity\User;
use App\Media\MediaStorage;
use App\Media\StorageUsage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Uid\Uuid;

/**
 * Contacts' files (Archivos, and the portal's): type detected from content (PDF, images, spreadsheets, Word), within
 * the consultant's file-size and storage limits, stored outside the web root and streamed as a download.
 */
final class ClientFiles
{
    /** Content type (as detected) => what the person reads. */
    public const TYPES = [
        'application/pdf' => 'PDF',
        'image/jpeg' => 'JPG',
        'image/png' => 'PNG',
        'image/webp' => 'WebP',
        'text/csv' => 'CSV',
        'text/plain' => 'CSV',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'XLSX',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'DOCX',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MediaStorage $paths,
        private readonly StorageUsage $storage,
    ) {
    }

    public function upload(Account $account, Contact $contact, ?UploadedFile $file, User $by, bool $shared): ClientFile
    {
        if (null === $file || !$file->isValid()) {
            throw ApiValidationException::single('file', 'Choose a file to upload.');
        }
        $size = (int) $file->getSize();
        if ($size > $account->getMaxFileMb() * 1024 * 1024) {
            throw ApiValidationException::single('file', 'The file can be at most %max% MB.', ['%max%' => $account->getMaxFileMb()]);
        }
        $type = (string) (new \finfo(\FILEINFO_MIME_TYPE))->file($file->getPathname());
        $name = self::name((string) $file->getClientOriginalName());
        // A CSV is plain text: accepted as such only with its extension.
        if (!isset(self::TYPES[$type]) || ('text/plain' === $type && !str_ends_with(strtolower($name), '.csv'))) {
            throw ApiValidationException::single('file', 'Upload a PDF, an image (JPG, PNG, WebP), a spreadsheet (XLSX, CSV) or a Word document (DOCX).');
        }
        if ($this->storage->usedBytes() + $size > $account->getStorageMb() * 1024 * 1024) {
            throw ApiException::conflict('storage_limit_reached', sprintf('This consultant can store at most %d MB.', $account->getStorageMb()));
        }

        $id = Uuid::v7();
        $path = $this->paths->filePath($account->getId(), $id);
        $file->move(\dirname($path), basename($path));
        $clientFile = new ClientFile($contact, $id, $name, 'text/plain' === $type ? 'text/csv' : $type, $size, $by, $shared);
        $this->em->persist($clientFile);
        $this->em->flush();

        return $clientFile;
    }

    public function download(ClientFile $file): BinaryFileResponse
    {
        $account = $file->getAccount() ?? throw new \LogicException('A file belongs to an account.');
        $path = $this->paths->filePath($account->getId(), $file->getId());
        if (!is_file($path)) {
            throw ApiException::notFound();
        }
        $response = new BinaryFileResponse($path, 200, ['Content-Type' => $file->getContentType(), 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->getName(), self::ascii($file->getName()));

        return $response;
    }

    /** The name as the person gave it, without a path and at most 200 characters. */
    private static function name(string $original): string
    {
        $name = trim(basename(str_replace('\\', '/', $original)));

        return '' === $name ? 'archivo' : mb_substr($name, 0, 200);
    }

    private static function ascii(string $name): string
    {
        $ascii = (string) preg_replace('/[^\x20-\x7e]/', '_', $name);

        return str_replace(['/', '\\', '%'], '_', $ascii);
    }
}
