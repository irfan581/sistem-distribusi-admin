<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Sales;

class RehashPasswords extends Command
{
    protected $signature = 'auth:rehash-passwords {--dry-run}';

    protected $description = 'Detect and rehash unhashed passwords for User and Sales models';

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        $models = [
            ['class' => User::class, 'name' => 'users'],
            ['class' => Sales::class, 'name' => 'sales'],
        ];

        foreach ($models as $m) {
            $this->info("Checking {$m['name']} table...");
            $count = 0;
            $items = $m['class']::all();
            foreach ($items as $item) {
                $pw = $item->password;
                if (empty($pw)) continue;

                // Common bcrypt prefix is $2y$ or $2b$
                if (str_starts_with($pw, '$2y$') || str_starts_with($pw, '$2b$')) {
                    // already hashed
                    continue;
                }

                $count++;
                $this->line("Will rehash {$m['name']} id={$item->id}");
                if (!$dry) {
                    $item->password = Hash::make($pw);
                    $item->save();
                }
            }

            $this->info("Found {$count} unhashed passwords in {$m['name']}");
        }

        $this->info($dry ? 'Dry run completed.' : 'Rehash completed.');

        return 0;
    }
}
