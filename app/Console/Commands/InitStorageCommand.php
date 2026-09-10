<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Illuminate\Console\Command;

class InitStorageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:init {--force : Force bucket creation even if it exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize storage buckets (RustFS/S3)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Initializing storage buckets...');

        $bucket = config('filesystems.disks.rustfs.bucket');

        if (! $bucket) {
            $this->error('Bucket name not configured.');

            return self::FAILURE;
        }

        try {
            // Create S3 client directly from config
            $client = new S3Client([
                'version' => 'latest',
                'region' => config('filesystems.disks.rustfs.region', 'us-east-1'),
                'endpoint' => config('filesystems.disks.rustfs.endpoint'),
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key' => config('filesystems.disks.rustfs.key'),
                    'secret' => config('filesystems.disks.rustfs.secret'),
                ],
            ]);

            // Check if bucket exists
            $exists = $client->doesBucketExist($bucket);

            if ($exists && ! $this->option('force')) {
                $this->info("Bucket '{$bucket}' already exists.");

                return self::SUCCESS;
            }

            if (! $exists) {
                $this->info("Creating bucket '{$bucket}'...");
                $client->createBucket([
                    'Bucket' => $bucket,
                ]);
                $this->info("Bucket '{$bucket}' created successfully.");
            } elseif ($this->option('force')) {
                $this->info("Bucket '{$bucket}' already exists (force mode).");
            }

            $this->info('Storage initialization completed successfully.');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to initialize storage: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
