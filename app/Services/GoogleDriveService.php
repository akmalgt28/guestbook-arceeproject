<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use RuntimeException;

class GoogleDriveService
{
    /**
     * @return array<string, mixed>
     */
    public function upload(string $path, string $name, ?string $folderId = null): array
    {
        $mimeType = $this->validateFile($path);
        $fileMetadata = new DriveFile([
            'name' => $name,
            'parents' => [$folderId ?: config('services.google_drive.root_folder_id')],
        ]);
        $file = $this->drive()->files->create($fileMetadata, [
            'data' => $this->fileContents($path),
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id,name,mimeType,webViewLink,parents,size,createdTime,modifiedTime',
            'supportsAllDrives' => true,
        ]);

        return (array) $file->toSimpleObject();
    }

    /**
     * @return array<string, mixed>
     */
    public function replace(string $driveFileId, string $path, string $name): array
    {
        $mimeType = $this->validateFile($path);
        $fileMetadata = new DriveFile(['name' => $name]);
        $file = $this->drive()->files->update($driveFileId, $fileMetadata, [
            'data' => $this->fileContents($path),
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id,name,mimeType,webViewLink,parents,size,modifiedTime',
            'supportsAllDrives' => true,
        ]);

        return (array) $file->toSimpleObject();
    }

    public function delete(string $driveFileId): void
    {
        $this->drive()->files->delete($driveFileId, [
            'supportsAllDrives' => true,
        ]);
    }

    private function drive(): Drive
    {
        $credentialsPath = config('services.google_drive.credentials_path');

        if (! is_string($credentialsPath) || $credentialsPath === '' || ! is_file($credentialsPath)) {
            throw new RuntimeException('Google Drive credentials belum dikonfigurasi.');
        }

        $client = new Client;
        $client->setAuthConfig($credentialsPath);
        $client->addScope(Drive::DRIVE_FILE);
        $client->setAccessType('offline');

        return new Drive($client);
    }

    private function validateFile(string $path): string
    {
        if (! is_file($path)) {
            throw new RuntimeException('File media tidak ditemukan.');
        }

        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo === false ? false : finfo_file($fileInfo, $path);

        $allowedMimeTypes = config('services.google_drive.allowed_mime_types', []);
        $maxFileSize = (int) config('services.google_drive.max_file_size_mb', 20) * 1024 * 1024;

        if (! is_string($mimeType) || ! in_array($mimeType, $allowedMimeTypes, true)) {
            throw new RuntimeException('MIME type media tidak didukung.');
        }

        if (filesize($path) > $maxFileSize) {
            throw new RuntimeException('Ukuran media melebihi batas.');
        }

        return $mimeType;
    }

    private function fileContents(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Media gagal dibaca.');
        }

        return $contents;
    }
}
