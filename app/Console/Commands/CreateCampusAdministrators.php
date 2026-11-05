<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CreateCampusAdministrators extends Command
{
    protected $signature = 'campuses:create-admins';

    protected $description = 'Create missing campus administrator accounts and save new credentials privately';

    public function handle(): int
    {
        $path = storage_path('app/private/campus-admin-credentials-'.now()->format('Ymd-His').'-'.Str::random(6).'.csv');
        $file = fopen($path, 'x');
        if (! $file) {
            $this->error('Could not create the private credentials file.');

            return self::FAILURE;
        }
        $created = 0;
        try {
            DB::transaction(function () use ($file, &$created): void {
                fputcsv($file, ['Campus', 'Login email', 'Password'], ',', '"', '');
                foreach (Device::CAMPUSES as $campus) {
                    if (User::where('campus_name', $campus)->where('role', 'campus_admin')->exists()) {
                        continue;
                    }
                    $email = strtolower(substr($campus, 5)).'.admin@phishblock.local';
                    if (User::where('email', $email)->exists()) {
                        throw new RuntimeException('A proposed campus login already exists; no accounts were changed.');
                    }
                    $password = Str::password(20);
                    User::create(['name' => $campus.' Administrator', 'email' => $email,
                        'password' => $password, 'role' => 'campus_admin', 'campus_name' => $campus, 'is_active' => true]);
                    if (fputcsv($file, [$campus, $email, $password], ',', '"', '') === false) {
                        throw new RuntimeException('Could not save credentials.');
                    }
                    $created++;
                }
            });
            fclose($file);
        } catch (Throwable $error) {
            fclose($file);
            unlink($path);
            throw $error;
        }
        if ($created === 0) {
            unlink($path);
        }
        $this->info('Created '.$created.' campus administrator accounts. Existing accounts were preserved.');
        if ($created > 0) {
            $this->line('Private credentials: '.$path);
        }

        return self::SUCCESS;
    }
}
