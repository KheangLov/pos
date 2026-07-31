<?php

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Illuminate\Console\Command;

class EnsureMinioBucket extends Command
{
    protected $signature = 'app:ensure-minio-bucket';

    protected $description = 'Create the MinIO bucket for image uploads and make it publicly readable if it does not exist yet (safe to run on every startup)';

    public function handle(): int
    {
        $bucket = config('filesystems.disks.minio.bucket');

        $client = new S3Client([
            'version' => 'latest',
            'region' => config('filesystems.disks.minio.region'),
            'endpoint' => config('filesystems.disks.minio.endpoint'),
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => config('filesystems.disks.minio.key'),
                'secret' => config('filesystems.disks.minio.secret'),
            ],
        ]);

        if ($client->doesBucketExist($bucket)) {
            $this->info("Bucket \"{$bucket}\" already exists.");

            return self::SUCCESS;
        }

        $this->info("Creating bucket \"{$bucket}\"...");
        $client->createBucket(['Bucket' => $bucket]);
        $client->waitUntil('BucketExists', ['Bucket' => $bucket]);

        // Uploaded product/category/company images need to be readable by
        // customers' browsers (eMenu) without signed URLs.
        $client->putBucketPolicy([
            'Bucket' => $bucket,
            'Policy' => json_encode([
                'Version' => '2012-10-17',
                'Statement' => [[
                    'Effect' => 'Allow',
                    'Principal' => ['AWS' => ['*']],
                    'Action' => ['s3:GetObject'],
                    'Resource' => ["arn:aws:s3:::{$bucket}/*"],
                ]],
            ]),
        ]);

        $this->info('Bucket created and made publicly readable.');

        return self::SUCCESS;
    }
}
